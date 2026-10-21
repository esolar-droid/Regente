<?php
/**
 * BitacoraController — v17 «Bitácora del profesorado» (rol destinatario: regente)
 *
 * Reglas de negocio pactadas:
 *   - "Educador" = usuario con rol profesor/docente (tabla usuarios + roles).
 *   - Si el registro se crea SIN fecha, se asume por defecto la fecha actual
 *     (hoy). Estos registros NO están restringidos por el calendario académico
 *     ni por feriados (el calendario es solo para estudiantes/asistencia).
 *   - Búsqueda: UN input de texto libre que busca por nombre del profesor Y
 *     detalle/título del evento (Bitacora::filter, parámetro `q`).
 *   - Exportación a PDF: HTML imprimible simple que el navegador convierte a
 *     PDF (mismo patrón probado en v16.3 para el reporte anual: plantilla
 *     standalone + token _k + archivo cacheado en sys_get_temp_dir()).
 *
 * Rutas:
 *   GET  /bitacora                 Listado + formulario de alta (filtro q, fechas, tipo)
 *   POST /bitacora/store           Crea un registro (fecha vacía => hoy)
 *   POST /bitacora/{id}/update     Edita un registro
 *   POST /bitacora/{id}/delete     Elimina un registro
 *   GET  /bitacora/export/pdf      Genera la hoja imprimible con los filtros actuales
 *   GET  /bitacora/export/pdf.html Sirve la hoja cacheada (token _k)
 */

namespace App\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use App\Middleware\CsrfMiddleware;

class BitacoraController extends Controller
{
    const LOGO_URL = 'https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png';

    /** Acceso al módulo: admin o regente (profesor queda fuera del registro). */
    private function guard()
    {
        $this->requireAuth();
        if (!isAdmin() && !isRegente()) {
            $_SESSION['flash']['error'] = 'No tienes permiso para usar la Bitácora.';
            $this->redirect('/403');
        }
    }

    /** Profesores (usuarios con rol profesor/docente) como arrays simples. */
    private function teachers()
    {
        $out = [];
        try {
            // Patrón doble: la BD de producción usa la columna de texto `rol`
            // (igual que ApiController::suggest); si existe roles.name se usa
            // vía COALESCE en sistemas más recientes con role_id.
            $rows = User::query(
                "SELECT u.id, u.nombre, u.apellido FROM usuarios u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE COALESCE(u.rol, r.name) IN ('profesor','docente')
                    OR COALESCE(r.name, u.rol) IN ('profesor','docente')
                 ORDER BY u.apellido, u.nombre"
            );
            foreach ($rows as $u) {
                $id  = (int)($u->id ?? 0);
                $nom = trim(($u->nombre ?? '') . ' ' . ($u->apellido ?? ''));
                if ($id && $nom !== '') {
                    $out[] = ['id' => $id, 'nombre' => $nom];
                }
            }
        } catch (\Throwable $e) {
            error_log('BitacoraController.teachers(): ' . $e->getMessage());
        }
        return $out;
    }

    /** Filtros desde la query string (sanos). */
    private function filters(array $src)
    {
        $f = [
            'q'           => trim((string)($src['q'] ?? '')),
            'desde'       => (string)($src['desde'] ?? ''),
            'hasta'       => (string)($src['hasta'] ?? ''),
            'tipo'        => (string)($src['tipo'] ?? ''),
            'id_profesor' => (int)($src['profesor'] ?? 0),
        ];
        foreach (['desde', 'hasta'] as $k) {
            if ($f[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) $f[$k] = '';
        }
        if (!Bitacora::isValidTipo($f['tipo'])) $f['tipo'] = '';
        return $f;
    }

    // ====================================================================
    // LISTADO + ALTA   GET /bitacora
    // ====================================================================
    public function index()
    {
        $this->guard();

        $q     = $this->getQueryData();
        $f     = $this->filters($q);
        $rows  = Bitacora::filter($f);

        $ok    = isset($q['saved']);
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $this->view('bitacora/index', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'rows'       => $rows,
            'teachers'   => $this->teachers(),
            'f'          => $f,
            'total'      => Bitacora::countAll(),
            'hoy'        => date('Y-m-d'),
            'ok'         => $ok,
            'flash'      => $flash,
            'tipos'      => Bitacora::TIPOS,
        ]);
    }

    // ====================================================================
    // ALTA   POST /bitacora/store
    // ====================================================================
    public function store()
    {
        $this->guard();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido. Recarga la página.';
            $this->redirect('/bitacora');
        }

        $p       = $this->getPostData();
        $err     = $this->validatePayload($p);
        $backQ   = http_build_query(array_filter([
            'q' => $p['fq'] ?? '', 'desde' => $p['fdesde'] ?? '',
            'hasta' => $p['fhasta'] ?? '', 'tipo' => $p['ftipo'] ?? '',
        ]));

        if ($err) {
            $_SESSION['flash']['error'] = implode(' ', $err);
            $this->redirect('/bitacora' . ($backQ ? "?$backQ" : ''));
        }

        $id = Bitacora::create([
            'id_profesor'         => (int)$p['id_profesor'],
            'tipo'                => $p['tipo'] ?: 'actividad',
            'titulo'              => mb_substr(trim((string)$p['titulo']), 0, 160),
            'detalle'             => trim((string)($p['detalle'] ?? '')),
            // REGLA: si no se especifica fecha => hoy (normalizeDate lo aplica
            // también dentro del modelo, defensa en dos capas).
            'fecha'               => Bitacora::normalizeDate($p['fecha'] ?? ''),
            'id_usuario_registro' => userId(),
        ]);

        if (!$id) {
            $_SESSION['flash']['error'] = 'No se pudo guardar el registro.';
        } else {
            $_SESSION['flash']['ok'] = 'Registro guardado en la Bitácora.';
        }
        $this->redirect('/bitacora?saved=1' . ($backQ ? "&$backQ" : ''));
    }

    // ====================================================================
    // EDICIÓN   POST /bitacora/{id}/update
    // ====================================================================
    public function update($id)
    {
        $this->guard();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/bitacora');
        }
        $reg = Bitacora::find((int)$id);
        if (!$reg) { $_SESSION['flash']['error'] = 'Registro inexistente.'; $this->redirect('/bitacora'); }

        $p    = $this->getPostData();
        $err  = $this->validatePayload($p);
        if ($err) {
            $_SESSION['flash']['error'] = implode(' ', $err);
            $this->redirect('/bitacora');
        }
        Bitacora::change((int)$id, [
            'id_profesor' => (int)$p['id_profesor'],
            'tipo'        => $p['tipo'] ?: 'actividad',
            'titulo'      => mb_substr(trim((string)$p['titulo']), 0, 160),
            'detalle'     => trim((string)($p['detalle'] ?? '')),
            'fecha'       => Bitacora::normalizeDate($p['fecha'] ?? ''),
        ]);
        $_SESSION['flash']['ok'] = 'Registro actualizado.';
        $this->redirect('/bitacora?saved=1');
    }

    // ====================================================================
    // BORRADO   POST /bitacora/{id}/delete
    // ====================================================================
    public function destroy($id)
    {
        $this->guard();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/bitacora');
        }
        Bitacora::remove((int)$id);
        $_SESSION['flash']['ok'] = 'Registro eliminado.';
        $this->redirect('/bitacora');
    }

    /** Validación común de alta/edición. */
    private function validatePayload(array $p)
    {
        $err = [];
        if (empty($p['id_profesor']) || !is_numeric($p['id_profesor'])) {
            $err[] = 'Selecciona un profesor.';
        } elseif ($p['id_profesor'] > 0) {
            $t = null;
            foreach ($this->teachers() as $x) if ($x['id'] === (int)$p['id_profesor']) { $t = $x; break; }
            if (!$t) $err[] = 'El usuario seleccionado no tiene rol de profesor.';
        }
        if (trim((string)($p['titulo'] ?? '')) === '') $err[] = 'El título es obligatorio.';
        if (!empty($p['tipo']) && !Bitacora::isValidTipo($p['tipo'])) $err[] = 'Tipo no válido.';
        // La fecha es OPCIONAL por diseño: vacía => hoy (no es error).
        return $err;
    }

    // ====================================================================
    // EXPORT PDF (HTML imprimible → «Imprimir» del navegador)
    // Mismo mecanismo robusto probado en ReportController::exportPdf v16.3.
    // ====================================================================
    public function exportPdf()
    {
        // Ruta pública /bitacora/export/pdf.html -> solo sirve el archivo
        // cacheado si el token _k coincide.
        if (!empty($_GET['_p'])) {
            $key  = preg_replace('/[^a-f0-9]/', '', (string)($_GET['_k'] ?? ''));
            $file = sys_get_temp_dir() . '/bitepdf-' . preg_replace('/[^a-f0-9]/', '', md5($key)) . '.html';
            if (!is_file($file)) {
                http_response_code(404);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<p>La bitácora expiró. Vuelve a Bitácora y genera el PDF otra vez.</p>';
                exit;
            }
            if (!isAdmin() && !isRegente()) { $this->requireAuth(); $this->redirect('/403'); }
            header('Content-Type: text/html; charset=UTF-8');
            readfile($file);
            exit;
        }

        $this->guard();

        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
        ob_start();
        register_shutdown_function(function () use (&$obLevel) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
                while (ob_get_level() > $obLevel) { ob_end_clean(); }
                http_response_code(200);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Error al generar PDF</title></head>'
                   . '<body style="font-family:Arial;padding:30px">'
                   . '<h2 style="color:#c0392b">No se pudo generar la bitácora imprimible</h2><pre style="background:#faf3f0;padding:12px;border-radius:6px;white-space:pre-wrap">'
                   . htmlspecialchars($err['message'] . ' @ ' . $err['file'] . ':' . $err['line'])
                   . '</pre></body></html>';
            }
        });
        $obLevel = ob_get_level();

        try {
            $f    = $this->filters($_GET);
            $rows = Bitacora::filter($f);
            $data = [
                'rows'      => $rows,
                'f'         => $f,
                'logoUrl'   => self::LOGO_URL,
                'generated' => date('d/m/Y H:i'),
                'h'         => fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'),
                'tipos'     => Bitacora::TIPOS,
            ];
            extract($data);
            require APP_ROOT . '/app/views/bitacora/printable.php';
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) { ob_end_clean(); }
            http_response_code(200);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial;padding:30px">'
               . '<h2 style="color:#c0392b">Error al generar la bitácora</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre></body></html>';
            return;
        }

        $html = ob_get_clean();

        // Guardar copia estática autocontenida (sin sesión) y abrir esa URL,
        // igual que en Reportes (fix v16.1/v16.3 contra ventana en blanco).
        if (!empty($html) && strpos($html, '</html>') !== false) {
            $key  = bin2hex(random_bytes(8));
            $file = sys_get_temp_dir() . '/bitepdf-' . preg_replace('/[^a-f0-9]/', '', md5($key)) . '.html';
            if (@file_put_contents($file, $html) !== false) {
                $base = strtok((string)($_SERVER['REQUEST_URI'] ?? '/bitacora/export/pdf'), '?');
                $url  = dirname($base) . '/pdf.html?' . http_build_query([
                    '_p' => 1, '_k' => $key,
                    'q' => $f['q'] ?? '', 'desde' => $f['desde'] ?? '', 'hasta' => $f['hasta'] ?? '',
                ]);
                $js = '<script>window.location.href=' . json_encode($url) . ';</script>';
                if (($pos = strrpos($html, '</body>')) !== false) {
                    $html = substr($html, 0, $pos) . $js . substr($html, $pos);
                } else {
                    $html .= $js;
                }
                foreach (glob(sys_get_temp_dir() . '/bitepdf-*.html') ?: [] as $old) {
                    if (filemtime($old) < time() - 86400) { @unlink($old); }
                }
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Length: ' . strlen($html));
        echo $html;
        exit;
    }
}
