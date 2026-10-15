<?php
/**
 * MessageController — «Hoja de mensajería» (WhatsApp)
 *
 * Página con DOS pestañas:
 *   Pestaña 1  Enviar mensaje (solo admin/regente con send_whatsapp):
 *              - Columna 1: composición del texto con botones de formato
 *                estilo WhatsApp (*negrita*, _cursiva_, ~tachado~) y
 *                botones de datos {{estudiante}}, {{evento}}, {{justificacion}},
 *                {{total_alf}}, {{fecha_hoy}}. Vista previa en vivo.
 *              - Columna 2: filtro Curso+Fecha → listado de estudiantes con
 *                A/L/F ese día (color de fondo), justificación y botones
 *                verde (papá) / rosa (mamá) que abren wa.me con el mensaje
 *                ya interpolado y REGISTRAN el envío en `mensajes_enviados`.
 *   Pestaña 2  Historial (todos los roles, paginado por mes/curso):
 *              - Profesor: SOLO mensajes de los cursos donde dicta clases
 *                (tabla `horarios`; si está vacía, fallback a sus estudiantes).
 *              - Admin: puede ELIMINAR (no editar) — pensado para limpiar
 *                registros de prueba.
 *
 * Rutas:
 *   GET  /messages                              (con ?origen=asistencia&curso=&fecha= preselecciona filtros)
 *   POST /messages/log     → registra envío (AJAX, devuelve id)
 *   POST /messages/{id}/delete → elimina (solo administrador)
 */

namespace App\Controllers;

use App\Models\Message;
use App\Models\Student;
use App\Models\Course;
use App\Models\Event;
use App\Models\Horario;
use App\Middleware\CsrfMiddleware;

class MessageController extends Controller {

    /** Código de país por defecto para normalizar teléfonos a E.164 (Bolivia). */
    const DEFAULT_COUNTRY = '591';

    private function canSend() {
        return isAdmin() || isRegente() || can('send_whatsapp');
    }

    // ------------------------------------------------------------------
    // PÁGINA PRINCIPAL  GET /messages?tab=enviar|historial
    // ------------------------------------------------------------------
    public function index() {
        $this->requireAuth();

        $q   = $this->getQueryData();
        $tab = ($q['tab'] ?? '') === 'historial' ? 'historial' : 'enviar';

        // El profesor NO ve la pestaña de envío: se va directo al historial.
        if (!$this->canSend()) $tab = 'historial';

        // ---------- PESTAÑA 1: preparación del envío ----------
        $courseId = (int)($q['curso'] ?? 0);
        $date     = (string)($q['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $filtered = !empty($q['filtrar']);

        $courses = $this->availableCourses();
        $incidentes = [];
        if ($filtered && $courseId > 0) {
            $incidentes = $this->incidentsFor($courseId, $date);
        }

        // ---------- PESTAÑA 2: historial filtrado + paginación ----------
        $mes    = preg_match('/^\d{4}-\d{2}$/', (string)($q['mes'] ?? '')) ? $q['mes'] : date('Y-m');
        $hCurso = (int)($q['hcurso'] ?? 0);
        $page   = max(1, (int)($q['p'] ?? 1));
        $hist   = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        if ($this->canViewHistory()) {
            $hist = $this->historyQuery($mes, $hCurso, $page);
        }

        $this->view('messages/index', [
            'csrf_token'   => CsrfMiddleware::generateToken(),
            'tab'          => $tab,
            'canSend'      => $this->canSend(),
            'courses'      => $courses,
            'courseId'     => $courseId,
            'date'         => $date,
            'filtered'     => $filtered,
            'course'       => $this->findCourse($courses, $courseId),
            'incidentes'   => $incidentes,
            'defaultMsg'   => $this->defaultTemplate(),
            'mes'          => $mes,
            'hCurso'       => $hCurso,
            'hist'         => $hist,
            'flashOk'      => $_SESSION['flash']['ok'] ?? null,
            'flashErr'     => $_SESSION['flash']['error'] ?? null,
        ]);
        unset($_SESSION['flash']['ok'], $_SESSION['flash']['error']);
    }

    // ------------------------------------------------------------------
    // REGISTRO DE ENVÍO  POST /messages/log  (AJAX al pulsar el botón wa.me)
    // ------------------------------------------------------------------
    public function logSend() {
        $this->requireAuth();
        if (!$this->canSend()) {
            $this->json(['ok' => false, 'error' => 'Sin permiso para enviar mensajes.'], 403);
        }
        if (!CsrfMiddleware::validateToken()) {
            $this->json(['ok' => false, 'error' => 'Token CSRF inválido.'], 403);
        }

        $p        = $this->getPostData();
        $sid      = (int)($p['id_estudiante'] ?? 0);
        $dest     = strtolower(trim((string)($p['destinatario'] ?? '')));
        $template = (string)($p['mensaje'] ?? '');
        $fecha    = (string)($p['fecha_evento'] ?? '');

        $student = $sid > 0 ? Student::find($sid) : null;
        if (!$student)                 $this->json(['ok' => false, 'error' => 'Estudiante no válido.'], 422);
        if (!in_array($dest, ['papa', 'mama'], true)) $this->json(['ok' => false, 'error' => 'Destinatario inválido.'], 422);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $this->json(['ok' => false, 'error' => 'Fecha inválida.'], 422);
        if (trim(strip_tags($template)) === '') $this->json(['ok' => false, 'error' => 'Mensaje vacío.'], 422);

        // Evento REAL de ese día (único por estudiante+fecha) → snapshot
        $marks = Event::dayMarks([$sid], $fecha);
        $tipo  = $marks[$sid]['tipo'] ?? '';
        $just  = $marks[$sid]['just'] ?? '';
        if (!$tipo) $this->json(['ok' => false, 'error' => 'No hay evento A/L/F registrado para esa fecha.'], 422);

        $telRaw  = $dest === 'papa' ? $student->telefono_papa : $student->telefono_mama;
        $nombreD = $dest === 'papa' ? 'Papá' : 'Mamá';
        $e164    = self::toE164((string)$telRaw);
        if (!$e164) $this->json(['ok' => false, 'error' => "El {$nombreD} no tiene teléfono registrado."], 422);

        $rendered = self::renderTemplate($template, [
            'estudiante'    => (string)$student->nombre_completo,
            'curso'         => $this->courseName((int)$student->id_curso),
            'evento'        => $tipo,
            'evento_nombre' => Event::shortToTypeLabel($tipo),
            'justificacion' => $just !== '' ? $just : 'Sin justificación',
            'total_alf'     => $this->alfTotals($sid),
            'fecha_hoy'     => self::longDate(date('Y-m-d')),
            'fecha_evento'  => self::longDate($fecha),
        ]);

        $msg = new Message([
            'id_estudiante'       => $sid,
            'id_curso'            => (int)$student->id_curso,
            'fecha_evento'        => $fecha,
            'tipo_evento'         => $tipo,
            'justificacion'       => $just !== '' ? $just : null,
            'destinatario'        => $dest,
            'nombre_destinatario' => $nombreD . ' de ' . $student->nombre_completo,
            'telefono'            => $e164,
            'mensaje'             => $rendered,
            'id_usuario_envio'    => userId(),
            'fecha_envio'         => date('Y-m-d H:i:s'),
        ]);
        $id = $msg->save();
        if (!$id) $this->json(['ok' => false, 'error' => 'No se pudo registrar el mensaje.'], 500);

        $this->json([
            'ok'    => true,
            'id'    => (int)$id,
            'wa_url' => 'https://wa.me/' . $e164 . '?text=' . rawurlencode($rendered),
        ]);
    }

    // ------------------------------------------------------------------
    // ELIMINAR (solo administrador)  POST /messages/{id}/delete
    // ------------------------------------------------------------------
    public function destroy($id) {
        $this->requireAuth();
        if (!isAdmin()) {
            $_SESSION['flash']['error'] = 'Solo el administrador puede eliminar mensajes.';
            $this->redirect('/messages?tab=historial');
        }
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/messages?tab=historial');
        }
        $msg = Message::find((int)$id);
        if ($msg) {
            $msg->delete();
            $_SESSION['flash']['ok'] = "Mensaje #$id eliminado (limpieza de pruebas).";
        } else {
            $_SESSION['flash']['error'] = 'Mensaje no encontrado.';
        }
        $this->redirect('/messages?tab=historial');
    }

    // ================= helpers de negocio =================

    /** Cursos disponibles: todos para admin/regente; solo los suyos para profesor. */
    private function availableCourses() {
        if ($this->canSend()) {
            return Course::orderBy('curso');
        }
        $mine = Horario::coursesForProfessor(userId());
        if (!$mine) {   // horarios aún sin cargar: fallback curso(s) de sus estudiantes no aplica → lista vacía orientativa
            return [];
        }
        return $mine;
    }

    private function findCourse(array $courses, int $id) {
        foreach ($courses as $c) if ((int)$c['id'] === $id) return $c;
        return null;
    }

    private function canViewHistory() {
        return can('view_messages');
    }

    /** Estudiantes del curso con incidente (A/L/F) en la fecha dada. */
    private function incidentsFor($courseId, $date) {
        $roster = Event::rosterByCourse((int)$courseId);
        if (!$roster) return [];
        $ids   = array_column($roster, 'id');
        $marks = Event::dayMarks($ids, $date);
        $out   = [];
        foreach ($roster as $st) {
            $sid = (int)$st['id'];
            if (!isset($marks[$sid]) || $marks[$sid]['tipo'] === '') continue;
            $full = Student::find($sid);   // para teléfonos (nómina trae 3 columnas)
            $out[] = [
                'id'          => $sid,
                'nombre'      => $st['nombre_completo'],
                'tipo'        => $marks[$sid]['tipo'],
                'justificacion' => $marks[$sid]['just'],
                'tel_papa'    => self::toE164((string)($full->telefono_papa ?? '')),
                'tel_mama'    => self::toE164((string)($full->telefono_mama ?? '')),
            ];
        }
        return $out;
    }

    /** Totales acumulados A/L/F del estudiante (para {{total_alf}}). */
    private function alfTotals($studentId) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT tipo_evento, COUNT(*) n FROM eventos WHERE id_estudiante = ? GROUP BY tipo_evento"
            );
            $stmt->execute([(int)$studentId]);
            $t = ['A' => 0, 'L' => 0, 'F' => 0];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $s = Event::typeToShort($r['tipo_evento']);
                if ($s) $t[$s] += (int)$r['n'];
            }
            return $t;
        } catch (\Throwable $e) {
            error_log('MessageController.alfTotals(): ' . $e->getMessage());
            return ['A' => 0, 'L' => 0, 'F' => 0];
        }
    }

    /** Historial paginado: 25 por página, filtrado por mes y curso. */
    private function historyQuery($mes, $courseId, $page) {
        $perPage = 25;
        $where = ["DATE_FORMAT(m.fecha_envio, '%Y-%m') = ?"];
        $params = [$mes];

        if ($courseId > 0) {
            $where[] = "m.id_curso = ?";
            $params[] = $courseId;
        }

        // Profesor: solo cursos donde dicta clases (horarios)
        if (!$this->canSend()) {
            $mine = Horario::courseIdsForProfessor(userId());
            if (!$mine) {
                return ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'sin_horarios' => true];
            }
            $ph = implode(',', array_fill(0, count($mine), '?'));
            $where[] = "m.id_curso IN ($ph)";
            $params = array_merge($params, $mine);
        }

        $w = implode(' AND ', $where);
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM mensajes_enviados m JOIN estudiantes e ON e.id = m.id_estudiante WHERE $w");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            $pages = max(1, (int)ceil($total / $perPage));
            $page  = min(max(1, $page), $pages);

            $sql = "SELECT m.*, e.nombre_completo AS estudiante_nombre, c.curso AS curso_nombre
                    FROM mensajes_enviados m
                    JOIN estudiantes e ON e.id = m.id_estudiante
                    LEFT JOIN cursos c ON c.id = m.id_curso
                    WHERE $w
                    ORDER BY m.fecha_envio DESC
                    LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
        } catch (\Throwable $e) {
            error_log('MessageController.historyQuery(): ' . $e->getMessage());
            return ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        }
    }

    // ================= plantilla / placeholders =================

    /** Mensaje por defecto pactado (con placeholders; formato WhatsApp). */
    private function defaultTemplate() {
        return "Estimado Padre de Familia, le informamos que su hijo/a *{{estudiante}}*:\n"
             . "HOY ha registrado {{evento}} {{evento_nombre}}.\n"
             . "Hasta la fecha ({{fecha_hoy}}), en total tiene:\n"
             . "- Atrasos: {{atrasos}}\n"
             . "- Licencias: {{licencias}}\n"
             . "- Faltas: {{faltas}}\n"
             . "Justificación: {{justificacion}}";
    }

    /**
     * Interpola placeholders. $vars incluye 'total_alf' => ['A'=>n,'L'=>n,'F'=>n].
     * Desconocidos se dejan visibles como {{clave}} para detectar errores.
     */
    public static function renderTemplate($tpl, array $vars) {
        $alf = $vars['total_alf'] ?? ['A' => 0, 'L' => 0, 'F' => 0];
        $map = [
            '{{estudiante}}'      => $vars['estudiante'] ?? '',
            '{{curso}}'           => $vars['curso'] ?? '',
            '{{evento}}'          => $vars['evento'] ?? '',
            '{{evento_nombre}}'   => $vars['evento_nombre'] ?? '',
            '{{justificacion}}'   => $vars['justificacion'] ?? '',
            '{{fecha_hoy}}'       => $vars['fecha_hoy'] ?? '',
            '{{fecha_evento}}'    => $vars['fecha_evento'] ?? '',
            '{{total_alf}}'       => sprintf('%d/%d/%d', $alf['A'], $alf['L'], $alf['F']),
            '{{atrasos}}'         => (string)$alf['A'],
            '{{licencias}}'       => (string)$alf['L'],
            '{{faltas}}'          => (string)$alf['F'],
        ];
        return strtr($tpl, $map);
    }

    /** ddd, dd de mmm, del yyyy en español (sin depender de setlocale). */
    public static function longDate($date) {
        $dias = ['Sunday'=>'domingo','Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles',
                 'Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado'];
        $meses = [1=>'ene','2'=>'feb','3'=>'mar','4'=>'abr','5'=>'may','6'=>'jun',
                  7=>'jul','8'=>'ago','9'=>'sep','10'=>'oct','11'=>'nov','12'=>'dic'];
        $ts = strtotime((string)$date);
        if ($ts === false) return (string)$date;
        return sprintf('%s, %02d de %s, del %d',
            $dias[date('l', $ts)], (int)date('d', $ts), $meses[(int)date('n', $ts)], (int)date('Y', $ts));
    }

    /** Normaliza un teléfono boliviano a E.164 sin '+': 591XXXXXXXXX. */
    public static function toE164($raw) {
        $d = preg_replace('/\D+/', '', (string)$raw);
        if ($d === '') return '';
        if (strpos($d, '591') === 0) return $d;                       // ya con código país
        $d = ltrim($d, '0');                                          // 071234567 -> 71234567
        if (strlen($d) >= 7 && strlen($d) <= 9) return self::DEFAULT_COUNTRY . $d;
        return strlen($d) >= 10 ? $d : '';                            // raro: dejar largo plausible
    }

    private function courseName($courseId) {
        if ($courseId <= 0) return '';
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT curso FROM cursos WHERE id = ?");
            $stmt->execute([(int)$courseId]);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
