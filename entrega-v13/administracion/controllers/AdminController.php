<?php
/**
 * AdminController — Página única de Administración con 4 pestañas
 *
 *   1. Usuarios y permisos : crear usuario, cambiar rol, matriz de
 *      permisos por rol (crear roles/permisos nuevos incluidos).
 *   2. Horarios y profesores: carga masiva CSV "texto plano" (nombres
 *      reales -> se traducen a ids), listado, edición por modal,
 *      descarga de plantilla CSV.
 *   3. Gestión escolar     : migración de año (P1A->P2A, S6 se elimina
 *      tras respaldo) y "cambio de ropa" backup/restaurar entre
 *      snapshots internos de la tabla `snapshots`.
 *   4. Diagnóstico         : estado del sistema, integridad de datos y
 *      seguridad (CSRF, bcrypt, sesiones, permisos huérfanos...).
 *
 * ACCESO EXCLUSIVO: rol administrador (doble candado: requireAdmin()
 * dentro del controlador + AuthMiddleware en la ruta).
 */

namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Course;
use App\Models\Student;
use App\Models\Management;
use App\Models\Horario;
use App\Models\Feriado;
use App\Models\Event;
use App\Models\Message;
use App\Models\Snapshot;
use App\Middleware\CsrfMiddleware;

class AdminController extends Controller {

    /** Columnas reales de una tabla (para validar antes de insertar/update). */
    private function tableColumns($table) {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $db = \Database::getConnection();
                $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
                $stmt->execute([$table]);
                $cache[$table] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Throwable $e) {
                error_log('AdminController.tableColumns: ' . $e->getMessage());
                $cache[$table] = [];
            }
        }
        return $cache[$table];
    }

    /** Auditoría simple: quién, qué, cuándo. */
    private function log($accion, $detalle = null) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("INSERT INTO admin_log (usuario_id, accion, detalle, ip) VALUES (?,?,?,?)");
            $stmt->execute([
                $this->userId(), $accion, mb_substr((string)$detalle, 0, 250),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) { /* silencioso: no debe romper la acción */ }
    }

    private function flash($type, $msg) {
        $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
    }

    // ============================================================
    // GET /admin — pantalla con las 4 pestañas
    // ============================================================
    public function index() {
        $this->requireAuth();
        $this->requireAdmin();

        $q = $this->getQueryData();
        $tab = $q['tab'] ?? 'usuarios';
        if (!in_array($tab, ['usuarios', 'horarios', 'gestion', 'diagnostico'])) $tab = 'usuarios';

        $data = ['csrf_token' => CsrfMiddleware::generateToken(), 'tab' => $tab];

        if ($tab === 'usuarios') {
            $roles = Role::all();
            $perms = Permission::query("SELECT * FROM permisos ORDER BY name");
            // Matriz rol x permiso como set "roleId_permId" para checkboxes rápidos
            $matrix = [];
            foreach ($perms as $p) {
                $rows = Permission::query("SELECT role_id FROM role_permissions WHERE permission_id = ?", [$p->id]);
                foreach ($rows as $r) $matrix[(int)$r->role_id][] = (int)$p->id;
            }
            $users = User::query(
                "SELECT u.*, r.name AS rol_nombre FROM usuarios u
                 LEFT JOIN roles r ON r.id = u.role_id
                 ORDER BY u.apellido, u.nombre"
            );
            $data += compact('roles', 'perms', 'matrix', 'users');
        }

        if ($tab === 'horarios') {
            $year = (int)($q['anio'] ?? date('Y'));
            if ($year < 2000 || $year > 2100) $year = (int)date('Y');
            $data['anioSel'] = $year;
            $data['courses'] = Course::query("SELECT * FROM cursos ORDER BY nivel, grado, paralelo");
            $data['profesores'] = User::query(
                "SELECT u.* FROM usuarios u JOIN roles r ON r.id = u.role_id
                 WHERE r.name IN ('profesor','docente') ORDER BY u.apellido, u.nombre"
            );
            $data['subjects'] = Horario::distinctSubjects($year);
            $data['horarios'] = Horario::query(
                "SELECT h.*, c.curso AS curso_code,
                        CONCAT(u.nombre,' ',u.apellido) AS profesor_nombre
                 FROM horarios h
                 LEFT JOIN cursos c ON c.id = h.id_curso
                 LEFT JOIN usuarios u ON u.id = h.id_profesor
                 WHERE h.anio = ?
                 ORDER BY FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes'), h.hora_inicio",
                [$year]
            );
            // Para el modal de edición
            $editId = (int)($q['editar'] ?? 0);
            $data['editRow'] = $editId ? Horario::find($editId) : null;
        }

        if ($tab === 'gestion') {
            $data['gestiones'] = Management::all();
            $data['activa'] = Management::getActive();
            $data['snapshots'] = Snapshot::allList();
            $data['nextAnio'] = (int)date('Y') + 1;
        }

        if ($tab === 'diagnostico') {
            $data['diag'] = $this->runDiagnostics();
        }

        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);
        $data['flash'] = $flash;

        $this->view('admin/index', $data);
    }

    // ============================================================
    // PESTAÑA 1 — Usuarios y permisos
    // ============================================================
    public function storeUser() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $nombre = trim($p['nombre'] ?? ''); $apellido = trim($p['apellido'] ?? '');
        $usuario = trim($p['usuario'] ?? ''); $pass = (string)($_POST['contrasena'] ?? '');
        $rolId = (int)($p['role_id'] ?? 0);

        $err = '';
        if ($nombre === '' || $apellido === '' || $usuario === '') $err = 'Nombre, apellido y usuario son obligatorios.';
        elseif (strlen($pass) < 6) $err = 'La contraseña debe tener al menos 6 caracteres.';
        elseif (!$rolId) $err = 'Selecciona un rol.';
        else {
            $dup = User::firstWhere('usuario', '=', $usuario);
            if ($dup) $err = 'Ese nombre de usuario ya existe.';
        }
        if (!$err) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $cols = $this->tableColumns('usuarios');
            $ins = ['nombre' => $nombre, 'apellido' => $apellido, 'usuario' => $usuario,
                    'contrasena' => $hash, 'estado' => 'activo'];
            if (in_array('rol', $cols)) {
                $role = Role::find($rolId);
                $ins['rol'] = $role ? $role->name : 'profesor';
            }
            if (in_array('role_id', $cols)) $ins['role_id'] = $rolId;
            $ph = implode(',', array_fill(0, count($ins), '?'));
            User::execute("INSERT INTO usuarios (`" . implode('`,`', array_keys($ins)) . "`) VALUES ($ph)",
                          array_values($ins));
            $this->log('user_create', "Usuario '$usuario' rol #" . $rolId);
            $this->flash('is-success', "Usuario «$usuario» creado correctamente.");
        } else {
            $this->flash('is-danger', $err);
        }
        $this->redirect('/admin?tab=usuarios');
    }

    public function updateUserRole() {
        $this->requireAuth(); $this->requireAdmin();
        $id = (int)($this->getPostData()['id'] ?? 0);
        $rolId = (int)($this->getPostData()['role_id'] ?? 0);
        $u = User::find($id); $role = Role::find($rolId);
        if (!$u || !$role) { $this->flash('is-danger', 'Usuario o rol inexistente.'); $this->redirect('/admin?tab=usuarios'); }
        if ($id === $this->userId()) {
            // (d) autoprotección: el admin no puede quitarse su propio rol
            $this->flash('is-warning', 'No puedes cambiar tu propio rol (quedas fuera del sistema).');
            $this->redirect('/admin?tab=usuarios');
        }
        $sets = ['role_id = ?']; $vals = [$rolId];
        if (in_array('rol', $this->tableColumns('usuarios'))) { $sets[] = 'rol = ?'; $vals[] = $role->name; }
        $vals[] = $id;
        User::execute("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = ?", $vals);
        $this->log('user_role', "#$id -> {$role->name}");
        $this->flash('is-success', "Rol de «{$u->usuario}» actualizado a {$role->name}.");
        $this->redirect('/admin?tab=usuarios');
    }

    public function savePermissions() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $roleId = (int)($p['role_id'] ?? 0);
        $role = Role::find($roleId);
        if (!$role) { $this->flash('is-danger', 'Rol inexistente.'); $this->redirect('/admin?tab=usuarios'); }
        $checked = array_map('intval', (array)($p['permisos'] ?? []));
        $all = Permission::query("SELECT id FROM permisos");
        $allIds = array_map(fn($x) => (int)$x->id, $all);
        $toRemove = array_diff($allIds, $checked);
        foreach ($toRemove as $pid) Permission::execute("DELETE FROM role_permissions WHERE role_id=? AND permission_id=?", [$roleId, $pid]);
        foreach ($checked as $pid) Permission::execute("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$roleId, $pid]);
        $this->log('permits_save', "Rol {$role->name}: " . count($checked) . " permisos");
        $this->flash('is-success', "Permisos del rol «{$role->name}» guardados (" . count($checked) . ").");
        $this->redirect('/admin?tab=usuarios');
    }

    public function createRole() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $name = trim($p['name'] ?? ''); $desc = trim($p['description'] ?? '');
        if ($name === '') { $this->flash('is-danger', 'El rol necesita nombre.'); $this->redirect('/admin?tab=usuarios'); }
        if (Role::findByName($name)) { $this->flash('is-warning', 'Ese rol ya existe.'); $this->redirect('/admin?tab=usuarios'); }
        Role::execute("INSERT INTO roles (name, description) VALUES (?,?)", [$name, $desc ?: null]);
        $this->log('role_create', $name);
        $this->flash('is-success', "Rol «$name» creado. Asígnale permisos en la matriz.");
        $this->redirect('/admin?tab=usuarios');
    }

    public function createPermission() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $name = trim($p['name'] ?? ''); $desc = trim($p['description'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_]{2,60}$/', $name)) {
            $this->flash('is-danger', 'Nombre inválido: usa minúsculas, inicia con letra (ej. view_reports).');
            $this->redirect('/admin?tab=usuarios');
        }
        if (Permission::findByName($name)) { $this->flash('is-warning', 'Ese permiso ya existe.'); $this->redirect('/admin?tab=usuarios'); }
        Permission::execute("INSERT INTO permisos (name, description) VALUES (?,?)", [$name, $desc ?: null]);
        $this->log('perm_create', $name);
        $this->flash('is-success', "Permiso «$name» creado. Now asígnalo a los roles que quieras desde la matriz.");
        $this->redirect('/admin?tab=usuarios');
    }

    // ============================================================
    // PESTAÑA 2 — Horarios (CSV plano + edición)
    // ============================================================
    const DIA_MAP = [
        'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles',
        'miércoles' => 'Miércoles', 'jueves' => 'Jueves', 'viernes' => 'Viernes',
        'l' => 'Lunes', 'm' => 'Martes', 'x' => 'Miércoles', 'j' => 'Jueves', 'v' => 'Viernes',
    ];

    private function csvTemplate() {
        return "curso,profesor,dia,hora_inicio,hora_fin,asignatura,anio\n"
             . "P1A,Juan Pérez,Lunes,07:30,09:00,Matemática,2026\n"
             . "S3B,María López,Miércoles,10:00,11:30,Física-Química,2026\n";
    }

    public function downloadTemplate() {
        $this->requireAuth(); $this->requireAdmin();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="plantilla_horarios.csv"');
        echo "\xEF\xBB\xBF"; // BOM para Excel
        echo $this->csvTemplate();
        exit;
    }

    /** Traduce curso/profesor por NOMBRE a ids; devuelve [filaValidada|null, error|null]. */
    private function resolveCsvRow(array $r, $lineNo, array &$courseCache, array &$profCache) {
        $curso = trim($r['curso'] ?? ''); $prof = trim($r['profesor'] ?? '');
        $dia = trim($r['dia'] ?? ''); $hi = trim($r['hora_inicio'] ?? ''); $hf = trim($r['hora_fin'] ?? '');
        $asig = trim($r['asignatura'] ?? ''); $anio = (int)trim($r['anio'] ?? date('Y'));
        if ($curso === '' && $prof === '' && $dia === '') return [null, null]; // línea vacía

        $fail = fn($m) => "Línea $lineNo: $m";
        if (!isset($dia)) return [null, $fail('falta el día.')];
        $diaKey = mb_strtolower($dia, 'UTF-8');
        if (!isset(self::DIA_MAP[$diaKey])) return [null, $fail("día «$dia» inválido (Lunes..Viernes).")];
        if (!preg_match('/^\d{1,2}:\d{2}$/', $hi) || !preg_match('/^\d{1,2}:\d{2}$/', $hf))
            return [null, $fail('horas deben ser HH:MM.')];
        if (strtotime("2000-01-01 $hi") >= strtotime("2000-01-01 $hf"))
            return [null, $fail('hora_inicio debe ser menor que hora_fin.')];
        if ($asig === '') return [null, $fail('falta la asignatura.')];
        if ($anio < 2000 || $anio > 2100) return [null, $fail('año fuera de rango.')];

        if (!array_key_exists($curso, $courseCache)) {
            $found = Course::query("SELECT id, curso FROM cursos WHERE curso = ? OR curso = ? LIMIT 1",
                                   [strtoupper($curso), $curso]);
            $courseCache[$curso] = $found ? (int)$found[0]->id : 0;
        }
        if (!$courseCache[$curso]) return [null, $fail("el curso «$curso» no existe en la tabla cursos.")];

        if (!array_key_exists($prof, $profCache)) {
            if ($prof === '') { $profCache[$prof] = 0; }
            else {
                $parts = preg_split('/\s+/', $prof, 2);
                $found = User::query(
                    "SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.role_id
                     WHERE r.name IN ('profesor','docente')
                       AND (TRIM(CONCAT(u.nombre,' ',u.apellido)) = ?
                            OR TRIM(CONCAT(u.apellido,' ',u.nombre)) = ?) LIMIT 1",
                    [$prof, $prof]
                );
                if (!$found && count($parts) === 2) {
                    $found = User::query(
                        "SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.role_id
                         WHERE r.name IN ('profesor','docente') AND u.nombre = ? AND u.apellido = ? LIMIT 1",
                        [$parts[0], $parts[1]]
                    );
                }
                $profCache[$prof] = $found ? (int)$found[0]->id : 0;
            }
        }
        if ($prof !== '' && !$profCache[$prof])
            return [null, $fail("el profesor «$prof» no existe (o no tiene rol docente).")];

        return [[
            'id_curso' => $courseCache[$curso],
            'id_profesor' => $profCache[$prof] ?: null,
            'dia' => self::DIA_MAP[$diaKey],
            'hora_inicio' => strlen($hi) === 5 ? '0' . $hi : $hi,
            'hora_fin' => strlen($hf) === 5 ? '0' . $hf : $hf,
            'asignatura' => $asig,
            'anio' => $anio,
        ], null];
    }

    public function importHorarios() {
        $this->requireAuth(); $this->requireAdmin();
        $text = (string)($_POST['csv'] ?? '');
        $back = '/admin?tab=horarios&anio=' . (int)($this->getPostData()['anio'] ?? date('Y'));
        if (trim($text) === '') { $this->flash('is-warning', 'Pega el contenido del CSV primero.'); $this->redirect($back); }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $hasHeader = false;
        $courseCache = []; $profCache = [];
        $ok = 0; $errors = []; $warns = [];
        foreach ($lines as $i => $line) {
            $lineNo = $i + 1;
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $cells = str_getcsv($line, ',');
            $low = array_map(fn($c) => trim(mb_strtolower($c, 'UTF-8')), $cells);
            if (!$hasHeader && in_array('curso', $low, true)) { $hasHeader = true; continue; }
            if (count($cells) < 6) { $errors[] = "Línea $lineNo: se esperaban 7 columnas (hay " . count($cells) . ")."; continue; }
            $r = array_combine(['curso','profesor','dia','hora_inicio','hora_fin','asignatura','anio'],
                               array_pad($cells, 7, ''));
            [$row, $err] = $this->resolveCsvRow($r, $lineNo, $courseCache, $profCache);
            if ($err) { $errors[] = $err; continue; }
            if (!$row) continue;
            // Choque de horario del mismo profesor (advertencia, no bloquea)
            $clash = [];
            if ($row['id_profesor']) {
                $clash = Horario::query(
                    "SELECT id FROM horarios WHERE anio=? AND id_profesor=? AND dia=?
                     AND hora_inicio < ? AND hora_fin > ?",
                    [$row['anio'], $row['id_profesor'], $row['dia'], $row['hora_fin'], $row['hora_inicio']]
                );
            }
            if ($clash) $warns[] = "Línea $lineNo: el profesor ya tiene hora ese día en franja superpuesta.";
            try {
                Horario::execute(
                    "INSERT INTO horarios (id_curso,id_profesor,dia,hora_inicio,hora_fin,asignatura,anio)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE id_profesor=VALUES(id_profesor), asignatura=VALUES(asignatura), hora_fin=VALUES(hora_fin)",
                    [$row['id_curso'], $row['id_profesor'], $row['dia'], $row['hora_inicio'], $row['hora_fin'], $row['asignatura'], $row['anio']]
                );
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = "Línea $lineNo: error SQL " . $e->getMessage();
            }
        }
        $this->log('horarios_csv', "importadas $ok, errores " . count($errors));
        if ($errors) {
            $_SESSION['admin_errors'] = $errors;
            $this->flash(count($ok) ? 'is-warning' : 'is-danger',
                         "Importación: $ok fila(s) guardadas, " . count($errors) . " con error (revísalo abajo).");
        } else {
            $this->flash('is-success', "$ok fila(s) de horario guardadas correctamente." .
                       ($warns ? ' (' . count($warns) . ' advertencia(s) de choque horario)' : ''));
        }
        if ($warns) $_SESSION['admin_warns'] = $warns;
        $this->redirect($back);
    }

    public function updateHorario() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $id = (int)($p['id'] ?? 0);
        $h = Horario::find($id);
        $back = '/admin?tab=horarios&anio=' . (int)($p['anio'] ?? date('Y'));
        if (!$h) { $this->flash('is-danger', 'Fila inexistente.'); $this->redirect($back); }
        $dia = self::DIA_MAP[mb_strtolower(trim($p['dia'] ?? ''), 'UTF-8')] ?? null;
        $hi = trim($p['hora_inicio'] ?? ''); $hf = trim($p['hora_fin'] ?? '');
        if (!$dia || !preg_match('/^\d{1,2}:\d{2}$/', $hi) || !preg_match('/^\d{1,2}:\d{2}$/', $hf)
            || strtotime("2000-01-01 $hi") >= strtotime("2000-01-01 $hf")) {
            $this->flash('is-danger', 'Datos de horario inválidos (día/horas).'); $this->redirect($back);
        }
        Horario::execute(
            "UPDATE horarios SET id_curso=?, id_profesor=?, dia=?, hora_inicio=?, hora_fin=?, asignatura=?, anio=? WHERE id=?",
            [(int)$p['id_curso'], (int)$p['id_profesor'] ?: null, $dia, $hi, $hf, trim($p['asignatura'] ?? ''), (int)$p['anio'], $id]
        );
        $this->log('horario_update', "#$id");
        $this->flash('is-success', 'Horario actualizado.');
        $this->redirect($back);
    }

    public function deleteHorario() {
        $this->requireAuth(); $this->requireAdmin();
        $id = (int)($this->getPostData()['id'] ?? 0);
        Horario::execute("DELETE FROM horarios WHERE id = ?", [$id]);
        $this->log('horario_delete', "#$id");
        $this->flash('is-success', 'Horario eliminado.');
        $this->redirect('/admin?tab=horarios');
    }

    // ============================================================
    // PESTAÑA 3 — Gestión escolar (backup / restaurar / migrar)
    // ============================================================
    public function backupGestion() {
        $this->requireAuth(); $this->requireAdmin();
        $anio = (int)($this->getPostData()['anio'] ?? 0);
        if ($anio < 2000) $anio = (int)date('Y');
        $res = Snapshot::capture($anio, $this->userId());
        if ($res['ok']) {
            $this->log('snapshot_backup', "Gestión $anio (#{$res['id']})");
            $sz = 0;
            try {
                $db = \Database::getConnection();
                $st = $db->prepare("SELECT tamano_bytes FROM snapshots WHERE id=?");
                $st->execute([(int)$res['id']]);
                $sz = (int)$st->fetchColumn();
            } catch (\Throwable $e) {}
            $this->flash('is-success', "Backup «Gestión $anio» guardado (#" . $res['id'] .
                         ", " . number_format($sz) . " bytes).");
        } else {
            $this->flash('is-danger', 'Error al respaldar: ' . $res['error']);
        }
        $this->redirect('/admin?tab=gestion');
    }

    public function restoreGestion() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $id = (int)($p['snapshot_id'] ?? 0);
        $confirm = trim($p['confirm'] ?? '');
        $sn = null;
        foreach (Snapshot::allList() as $s) if ((int)$s->id === $id) $sn = $s;
        if (!$sn || $confirm !== 'RESTAURAR ' . $sn->anio) {
            $this->flash('is-danger', 'Confirmación inválida. Escribe exactamente «RESTAURAR ' . ($sn ? $sn->anio : 'AAAA') . '».');
            $this->redirect('/admin?tab=gestion');
        }
        $res = Snapshot::restore($id);
        if ($res['ok']) {
            $this->log('snapshot_restore', "#$id ({$sn->anio})");
            $this->flash('is-success', "Gestión {$sn->anio} restaurada. Se reemplazaron todos los datos actuales por el respaldo.");
        } else {
            $this->flash('is-danger', 'Error al restaurar: ' . $res['error']);
        }
        $this->redirect('/admin?tab=gestion');
    }

    public function downloadSnapshot() {
        $this->requireAuth(); $this->requireAdmin();
        $id = (int)($this->getQueryData()['id'] ?? 0);
        $raw = Snapshot::raw($id);
        if (!$raw) { $this->flash('is-danger', 'Snapshot no encontrado.'); $this->redirect('/admin?tab=gestion'); }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="gestion-' . (int)$raw['anio'] . '.json.gz"');
        echo $raw['data'];
        exit;
    }

    public function deleteSnapshot() {
        $this->requireAuth(); $this->requireAdmin();
        $id = (int)($this->getPostData()['id'] ?? 0);
        Snapshot::remove($id);
        $this->log('snapshot_delete', "#$id");
        $this->flash('is-success', 'Snapshot eliminado.');
        $this->redirect('/admin?tab=gestion');
    }

    /** Promociona a todos los estudiantes al siguiente grado (P1A->P2A ... S5A->S6A). */
    public function migrateGestion() {
        $this->requireAuth(); $this->requireAdmin();
        $p = $this->getPostData();
        $fromYear = (int)($p['from_anio'] ?? 0);
        $toYear = (int)($p['to_anio'] ?? 0);
        $confirm = trim($p['confirm'] ?? '');
        $back = '/admin?tab=gestion';

        if ($toYear <= $fromYear || $fromYear < 2000 || $toYear > 2100) {
            $this->flash('is-danger', 'Años inválidos: destino debe ser mayor al origen.'); $this->redirect($back);
        }
        if ($confirm !== "MIGRAR $toYear") {
            $this->flash('is-danger', "Confirmación inválida. Escribe exactamente «MIGRAR $toYear»."); $this->redirect($back);
        }
        $gFrom = Management::firstWhere('anio', '=', $fromYear);
        if (!$gFrom) { $this->flash('is-danger', "No existe la gestión $fromYear."); $this->redirect($back); }

        try {
            $db = \Database::getConnection();
            // Salvaguarda automática: sin snapshot previo de $fromYear NO se migra.
            $hasSnap = $db->prepare("SELECT COUNT(*) FROM snapshots WHERE anio = ?");
            $hasSnap->execute([$fromYear]);
            if ((int)$hasSnap->fetchColumn() === 0) {
                $this->flash('is-danger', "No hay backup de la Gestión $fromYear. Hazlo primero (botón «Hacer backup») — es obligatorio para poder revertir.");
                $this->redirect($back);
            }

            $summary = [];
            $db->exec("SET FOREIGN_KEY_CHECKS=0");
            try {
                $db->beginTransaction();

                // 1) Crear la nueva gestión y activarla
                $exists = Management::firstWhere('anio', '=', $toYear);
                if (!$exists) {
                    Management::execute("INSERT INTO gestion (anio, estado) VALUES (?, 'activa')", [$toYear]);
                    $newG = Management::firstWhere('anio', '=', $toYear);
                } else {
                    $newG = $exists;
                }
                Management::execute("UPDATE gestion SET estado='receso' WHERE id<>?", [$newG->id]);
                Management::execute("UPDATE gestion SET estado='activa' WHERE id=?", [$newG->id]);
                $summary[] = "Gestión $toYear creada/activada.";

                // 2) Clonar cursos con grado+1 (P1A->P2A ... S5A->S6A); S6 no se clona
                $courses = Course::query("SELECT * FROM cursos");
                $map = []; // oldId => newId
                $gradMap = ['P1'=>'P2','P2'=>'P3','P3'=>'P4','P4'=>'P5','P5'=>'P6',
                            '1'=>'2','2'=>'3','3'=>'4','4'=>'5','5'=>'6'];
                foreach ($courses as $c) {
                    $grado = (string)$c->grado; $par = (string)$c->paralelo;
                    $isSecundariaFinal = ($c->nivel === 'secundaria' && ($grado === '5' || $grado === 'S5'));
                    if ($isSecundariaFinal) continue; // los S6 terminan: quedan solo en el backup
                    $ng = $gradMap[$grado] ?? null;
                    if ($ng === null) continue; // curso desconocido: no se toca
                    $newCursoCode = $c->curso;
                    // Reemplaza el tramo del grado dentro del código (P1A->P2A, 1A->2A...)
                    $pos = strpos($c->curso, $grado);
                    if ($pos !== false) $newCursoCode = substr_replace($c->curso, $ng, $pos, strlen($grado));
                    $dup = Course::firstWhere('curso', '=', $newCursoCode);
                    if ($dup) { $map[(int)$c->id] = (int)$dup->id; continue; }
                    Course::execute("INSERT INTO cursos (nivel, grado, paralelo, curso) VALUES (?,?,?,?)",
                                    [$c->nivel, $ng, $par, $newCursoCode]);
                    $nc = Course::firstWhere('curso', '=', $newCursoCode);
                    $map[(int)$c->id] = (int)$nc->id;
                }
                $summary[] = count($map) . " curso(s) promocionado(s) al grado siguiente.";

                // 3) Estudiantes: subir de curso; S6 quedan fuera (ya están en el backup)
                $st = $db->prepare("SELECT id, id_curso FROM estudiantes WHERE id_gestion = ?");
                $st->execute([(int)$gFrom->id]);
                $students = $st->fetchAll(\PDO::FETCH_ASSOC);
                $moved = 0; $dropped = 0;
                $upd = $db->prepare("UPDATE estudiantes SET id_curso = ?, id_gestion = ? WHERE id = ?");
                foreach ($students as $s) {
                    if (isset($map[(int)$s['id_curso']])) {
                        $upd->execute([$map[(int)$s['id_curso']], (int)$newG->id, (int)$s['id']]);
                        $moved++;
                    } else {
                        $dropped++; // S6 u otro: NO se copian a la nueva gestión (historial queda en el backup)
                    }
                }
                $summary[] = "$moved estudiante(s) promovieron de grado; $dropped (S6/otros) quedaron fuera (conservados en el backup #$fromYear).";

                // 4) Feriados del nuevo año: se arrancan limpios (no se clonan)
                $summary[] = "Horarios y feriados de $toYear inician vacíos (los de $fromYear están en el backup).";

                // 5) Limpieza de tablas operativas para el nuevo ciclo
                $db->exec("DELETE FROM horarios WHERE anio < $toYear");
                $summary[] = "Horarios de gestiones anteriores depurados.";

                $db->commit();
            } catch (\Throwable $ex) {
                if ($db->inTransaction()) $db->rollBack();
                throw $ex;
            } finally {
                $db->exec("SET FOREIGN_KEY_CHECKS=1");
            }
            $this->log('migracion', "$fromYear -> $toYear: " . implode(' ', $summary));
            $this->flash('is-success', 'Migración completada: ' . implode(' ', $summary));
        } catch (\Throwable $e) {
            error_log('migrateGestion: ' . $e->getMessage());
            $this->flash('is-danger', 'Error durante la migración (se revirtió todo): ' . $e->getMessage());
        }
        $this->redirect($back);
    }

    // ============================================================
    // PESTAÑA 4 — Diagnóstico (incluye seguridad)
    // ============================================================
    private function runDiagnostics() {
        $out = ['env' => [], 'tables' => [], 'integrity' => [], 'security' => [], 'checks' => []];
        try {
            $db = \Database::getConnection();
            $q = fn($sql, $params = []) => (function () use ($db, $sql, $params) {
                $s = $db->prepare($sql); $s->execute($params); return $s->fetchAll(\PDO::FETCH_ASSOC);
            })();

            // --- Entorno
            $out['env'][] = ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warn'];
            $out['env'][] = ['MySQL', $db->getAttribute(\PDO::ATTR_SERVER_VERSION), 'ok'];
            $out['env'][] = ['Zona horaria', date_default_timezone_get(), in_array(date_default_timezone_get(), ['America/La_Paz','America/Lima','America/Bogota']) ? 'ok' : 'warn'];
            $out['env'][] = ['gd_compress', function_exists('gzencode') ? 'disponible' : 'NO disponible', function_exists('gzencode') ? 'ok' : 'warn'];
            $out['env'][] = ['upload_max_filesize', ini_get('upload_max_filesize'), 'info'];
            $out['env'][] = ['post_max_size', ini_get('post_max_size'), 'info'];

            // --- Tablas y conteos
            $wanted = ['usuarios','roles','permisos','role_permissions','cursos','estudiantes',
                       'eventos','mensajes_enviados','feriados','horarios','gestion',
                       'estudiante_estados','snapshots','admin_log'];
            foreach ($wanted as $t) {
                $cnt = $q("SELECT COUNT(*) c FROM `$t`")[0]['c'] ?? null;
                $out['tables'][] = ['tabla' => $t, 'filas' => $cnt === null ? 'NO EXISTE' : (int)$cnt];
            }

            // --- Integridad referencial (huérfanos y duplicados)
            $orph = $q("SELECT COUNT(*) c FROM eventos e LEFT JOIN estudiantes s ON s.id=e.id_estudiante WHERE s.id IS NULL")[0]['c'] ?? 0;
            $out['integrity'][] = ['Eventos sin estudiante', (int)$orph, $orph ? 'bad' : 'ok'];
            $orph2 = $q("SELECT COUNT(*) c FROM horarios h LEFT JOIN cursos c ON c.id=h.id_curso WHERE c.id IS NULL")[0]['c'] ?? 0;
            $out['integrity'][] = ['Horarios sin curso', (int)$orph2, $orph2 ? 'bad' : 'ok'];
            $orph3 = $q("SELECT COUNT(*) c FROM horarios h LEFT JOIN usuarios u ON u.id=h.id_profesor WHERE h.id_profesor IS NOT NULL AND u.id IS NULL")[0]['c'] ?? 0;
            $out['integrity'][] = ['Horarios con profesor inexistente', (int)$orph3, $orph3 ? 'bad' : 'ok'];
            $dup = $q("SELECT COUNT(*) c FROM (SELECT id_estudiante, fecha_evento FROM eventos GROUP BY id_estudiante, fecha_evento HAVING COUNT(*)>1) t")[0]['c'] ?? 0;
            $out['integrity'][] = ['Duplicados A/L/F mismo día', (int)$dup, $dup ? 'bad' : 'ok'];
            $nogestion = $q("SELECT COUNT(*) c FROM estudiantes s LEFT JOIN gestion g ON g.id=s.id_gestion WHERE g.id IS NULL")[0]['c'] ?? 0;
            $out['integrity'][] = ['Estudiantes sin gestión', (int)$nogestion, $nogestion ? 'bad' : 'ok'];

            // --- Checklist post-migración
            $activa = Management::firstWhere('estado', '=', 'activa');
            if ($activa) {
                $sinCurso = $q("SELECT COUNT(*) c FROM estudiantes WHERE id_gestion=? AND (id_curso IS NULL OR id_curso=0)", [(int)$activa->id])[0]['c'] ?? 0;
                $out['checks'][] = ['¿Todos los alumnos de la gestión ' . $activa->anio . ' tienen curso?', !$sinCurso, "$sinCurso sin curso"];
                $hoy = $q("SELECT COUNT(*) c FROM horarios WHERE anio=?", [(int)$activa->anio])[0]['c'] ?? 0;
                $out['checks'][] = ['¿Horarios cargados para ' . $activa->anio . '?', $hoy > 0, "$hoy franjas"];
            } else {
                $out['checks'][] = ['¿Existe una gestión activa?', false, 'ninguna'];
            }

            // --- Seguridad
            $plain = 0;
            foreach (User::query("SELECT contrasena FROM usuarios") as $u) {
                $c = (string)$u->contrasena;
                if ($c !== '' && !preg_match('/^\$2y\$|\$2b\$|\$argon2/', $c)) $plain++;
            }
            $out['security'][] = ['Contraseñas en texto plano', $plain ? "$plain cuenta(s)" : '0 (todas con hash)', $plain ? 'bad' : 'ok'];
            $out['security'][] = ['CSRF habilitado', defined('CSRF_TOKEN_NAME') ? 'sí' : 'no', defined('CSRF_TOKEN_NAME') ? 'ok' : 'bad'];
            $https = (($_SERVER['HTTPS'] ?? '') !== '' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $out['security'][] = ['Conexión HTTPS', $https ? 'activa' : 'NO detectada', $https ? 'ok' : 'warn'];
            $sidGen = session_id() ? 'sesión iniciada' : 'sin sesión';
            $out['security'][] = ['Gestión de sesiones', $sidGen, 'ok'];
            $cookieSecure = ini_get('session.cookie_httponly') ? 'HttpOnly sí' : 'HttpOnly NO';
            $out['security'][] = ['Cookies de sesión', $cookieSecure, ini_get('session.cookie_httponly') ? 'ok' : 'warn'];
            $adminCount = $q("SELECT COUNT(*) c FROM usuarios u JOIN roles r ON r.id=u.role_id WHERE r.name='administrador'")[0]['c'] ?? 0;
            $out['security'][] = ['Cuentas administrador', (int)$adminCount, $adminCount > 2 ? 'warn' : 'ok'];
            $orphanPerms = $q("SELECT COUNT(*) c FROM permisos p LEFT JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.permission_id IS NULL")[0]['c'] ?? 0;
            $out['security'][] = ['Permisos sin asignar a ningún rol', (int)$orphanPerms, $orphanPerms ? 'warn' : 'ok'];
        } catch (\Throwable $e) {
            $out['integrity'][] = ['Error de diagnóstico', $e->getMessage(), 'bad'];
        }
        return $out;
    }
}
