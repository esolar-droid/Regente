<?php
/**
 * EventController — Módulo Asistencia (v11)
 *
 * Rutas:
 *   GET  /events            Historial de eventos con filtros
 *   GET  /attendance        Registro masivo por curso y semana (grilla P/A/L/F)
 *   POST /attendance/save   Guarda la grilla (solo A/L/F; P es implícito)
 *   GET  /events/create     Alta individual (estudiante + rango + tipo + justificación)
 *   POST /events/store      Guarda el rango; antes hace preview de conflictos
 *   POST /events/{id}/delete Elimina un evento (admin o dueño del registro)
 *
 * Reglas de negocio pactadas:
 *   - Pueden registrar: administrador y regente. Profesor NO tiene acceso.
 *   - El regente ve sus eventos y los del administrador, pero solo edita/borra
 *     los propios. El administrador puede con todos.
 *   - Solo se guardan atrasos/faltas/licencias; presente = sin registro.
 *   - Feriados (tabla `feriados`) bloquean el día en la grilla y se saltan
 *     en los rangos individuales.
 *   - Cursos "P..." (Primaria): L-V. Cursos "S..." (Secundaria): L-S.
 *   - Sobrescribir está permitido, pero SIEMPRE se avisa antes (preview).
 */

namespace App\Controllers;

use App\Models\Event;
use App\Models\Course;
use App\Models\Feriado;
use App\Models\Student;
use App\Middleware\CsrfMiddleware;

class EventController extends Controller {

    /** Acceso al módulo: admin o regente (profesor queda fuera). */
    private function canRegister() {
        return isAdmin() || isRegente();
    }

    private function requireRegister() {
        if (!$this->canRegister()) {
            $_SESSION['flash']['error'] = 'No tienes permiso para registrar asistencia.';
            $this->redirect('/403');
        }
    }

    /** Un usuario puede modificar este evento? Admin todo; regente solo suyos. */
    private function canManage($idUsuarioRegistro) {
        return isAdmin() || (int)$idUsuarioRegistro === userId();
    }

    /** Semana (lunes..viernes o sábado) que contiene $date. */
    private function weekDates($date, $hasSaturday) {
        $ts = strtotime((string)$date ?: 'now');
        $dow = (int)date('N', $ts);              // 1=Lun .. 7=Dom
        $monday = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
        $days = [];
        for ($i = 0; $i < ($hasSaturday ? 6 : 5); $i++) {
            $days[] = date('Y-m-d', strtotime($monday . " +$i days"));
        }
        return $days;
    }

    /** Etiquetas L,M,X,J,V,S con día numérico. Miércoles = X (por diseño). */
    private static $DAY_LABELS = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S'];

    // ------------------------------------------------------------------
    // HISTORIAL  GET /events
    // ------------------------------------------------------------------
    public function index() {
        $this->requireAuth();
        $this->requireRegister();

        $q       = $this->getQueryData();
        $page    = max(1, (int)($q['page'] ?? 1));
        $perPage = 45;
        $where   = [];
        $params  = [];

        if (!empty($q['tipo']) && in_array($q['tipo'], ['atraso', 'falta', 'licencia'], true)) {
            $where[]  = "(e.tipo_evento = ? OR (?='falta' AND e.tipo_evento='ausencia'))";
            $params[] = $q['tipo']; $params[] = $q['tipo'];
        }
        if (!empty($q['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['desde'])) {
            $where[] = "e.fecha_evento >= ?"; $params[] = $q['desde'];
        }
        if (!empty($q['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['hasta'])) {
            $where[] = "e.fecha_evento <= ?"; $params[] = $q['hasta'];
        }
        if (!empty($q['curso'])) {
            $where[] = "s.id_curso = ?"; $params[] = (int)$q['curso'];
        }
        if (!empty($q['estudiante'])) {
            $where[] = "e.id_estudiante = ?"; $params[] = (int)$q['estudiante'];
        }
        // Regente: solo sus registros y los del administrador (role_id 1)
        if (isRegente() && !isAdmin()) {
            $where[] = "(e.id_usuario_registro = ? OR e.id_usuario_registro IN (SELECT id FROM usuarios WHERE role_id = 1))";
            $params[] = userId();
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = 0; $rows = [];
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM eventos e JOIN estudiantes s ON s.id=e.id_estudiante $sqlWhere");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT e.*, s.nombre_completo, s.codigo_bnb, c.curso AS curso_nombre,
                        u.nombre AS reg_nome, u.apellido AS reg_ape
                 FROM eventos e
                 JOIN estudiantes s ON s.id = e.id_estudiante
                 LEFT JOIN cursos c ON c.id = s.id_curso
                 LEFT JOIN usuarios u ON u.id = e.id_usuario_registro
                 $sqlWhere
                 ORDER BY e.fecha_evento DESC, s.nombre_completo ASC
                 LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ex) {
            error_log('EventController.index: ' . $ex->getMessage());
        }

        $this->view('events/index', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'pages'      => max(1, (int)ceil($total / $perPage)),
            'filters'    => array_merge(['tipo' => '', 'desde' => '', 'hasta' => '', 'curso' => '', 'estudiante' => ''], $q),
            'courses'    => Course::orderBy('curso'),
            'canManage'  => fn($uid) => $this->canManage($uid),
        ]);
    }

    // ------------------------------------------------------------------
    // REGISTRO MASIVO  GET /attendance?curso=&fecha=
    // ------------------------------------------------------------------
    public function attendance() {
        $this->requireAuth();
        $this->requireRegister();

        $q = $this->getQueryData();
        $courses = array_map(function ($c) {
            $n = (string)($c['curso'] ?? '');
            $c['es_secundaria'] = stripos($n, 'S') === 0 || stripos((string)($c['nivel'] ?? ''), 'secundaria') !== false;
            return $c;
        }, Course::orderBy('curso'));

        $courseId = (int)($q['curso'] ?? 0);
        $date     = (string)($q['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $course = null;
        foreach ($courses as $c) if ((int)$c['id'] === $courseId) { $course = $c; break; }

        $week = []; $roster = []; $marks = []; $feriados = [];
        if ($course) {
            $week = $this->weekDates($date, stripos((string)$course['curso'], 'S') === 0);
            $roster = Event::rosterByCourse($courseId);
            $ids = array_column($roster, 'id');
            $feriados = Feriado::mapForDates($week);
            foreach ($week as $d) {
                $marks[$d] = Event::dayMarks($ids, $d);
            }
        }

        $this->view('attendance/index', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'courses'    => $courses,
            'course'     => $course,
            'courseId'   => $courseId,
            'date'       => $date,
            'week'       => $week,
            'dayLabels'  => self::$DAY_LABELS,
            'roster'     => $roster,
            'marks'      => $marks,
            'feriados'   => $feriados,
            'today'      => date('Y-m-d'),
            'stats'      => Event::getStatistics(),
            'saved'      => !empty($q['saved']),
        ]);
    }

    // ------------------------------------------------------------------
    // GUARDAR GRILLA  POST /attendance/save
    // ------------------------------------------------------------------
    public function saveAttendance() {
        $this->requireAuth();
        $this->requireRegister();

        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.';
            $this->redirect('/attendance');
        }

        $p        = $this->getPostData();
        $courseId = (int)($p['curso'] ?? 0);
        $date     = (string)($p['fecha'] ?? '');
        $entries  = $p['marcas'] ?? [];   // [id_estudiante] => ['t'=>'A|L|F|P','j'=>'texto']

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $courseId <= 0) {
            $_SESSION['flash']['error'] = 'Curso/fecha inválidos.';
            $this->redirect('/attendance');
        }
        if (Feriado::exists($date)) {
            $_SESSION['flash']['error'] = 'La fecha seleccionada es feriado: no se puede registrar asistencia.';
            $this->redirect("/attendance?curso=$courseId&fecha=$date");
        }

        // Validar propiedad: solo ids reales de la nómina del curso/gestión
        $allowed = array_map('intval', array_column(Event::rosterByCourse($courseId), 'id'));
        $saved = 0;
        if (is_array($entries)) {
            foreach ($entries as $sid => $ent) {
                $sid = (int)$sid;
                if (!in_array($sid, $allowed, true)) continue;
                $t = strtoupper(trim((string)($ent['t'] ?? 'P')));
                $j = trim((string)($ent['j'] ?? ''));
                if ($t === 'P' || $t === '') {
                    Event::upsertDay($sid, $date, '', '', userId()); // P implícito
                } elseif (Event::upsertDay($sid, $date, $t, $j, userId())) {
                    $saved++;
                }
            }
        }

        $courseName = '';
        foreach (Course::orderBy('curso') as $c) if ((int)$c['id'] === $courseId) $courseName = $c['curso'];

        $_SESSION['flash']['ok'] = 'Asistencia guardada para el curso ' . ($courseName ?: $courseId)
            . " del $date ($saved incidente(s) registrados).";
        $_SESSION['flash']['wa'] = [
            'curso' => $courseId, 'nombre' => $courseName, 'fecha' => $date, 'incidentes' => $saved,
        ];
        $this->redirect("/attendance?curso=$courseId&fecha=$date&saved=1");
    }

    // ------------------------------------------------------------------
    // ALTA INDIVIDUAL  GET /events/create
    // ------------------------------------------------------------------
    public function create() {
        $this->requireAuth();
        $this->requireRegister();

        $q = $this->getQueryData();
        $warn = $_SESSION['flash']['warn_overwrite'] ?? null;
        unset($_SESSION['flash']['warn_overwrite']);

        $this->view('events/form', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'studentId'  => (int)($q['estudiante'] ?? 0),
            'mode'       => 'create',
            'event'      => null,
            'warn'       => $warn,
        ]);
    }

    public function store() {
        $this->requireAuth();
        $this->requireRegister();

        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido. Recarga la página.';
            $this->redirect('/events/create');
        }

        $p       = $this->getPostData();
        $sid     = (int)($p['estudiante'] ?? 0);
        $from    = (string)($p['fecha_desde'] ?? '');
        $to      = (string)($p['fecha_hasta'] ?? $from);
        $short   = strtoupper(trim((string)($p['tipo'] ?? '')));
        $just    = trim((string)($p['justificacion'] ?? ''));
        $confirm = !empty($p['confirmar']);

        $err = [];
        if ($sid <= 0 || !Student::find($sid))                       $err[] = 'Estudiante no válido.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))             $err[] = 'Fecha de inicio inválida.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))               $err[] = 'Fecha final inválida.';
        if (!Event::shortToType($short))                             $err[] = 'Tipo de evento no válido (usa Atraso/Licencia/Falta).';
        if ($err) {
            $_SESSION['flash']['error'] = implode(' ', $err);
            $this->redirect('/events/create?estudiante=' . $sid);
        }

        // ¿Días del rango con registros previos? → avisar ANTES de sobrescribir
        $preview    = Event::applyRange($sid, $from, $to, $short, $just, userId(), true);
        $conflictos = (int)($preview['conflictos'][0]['n'] ?? 0);
        if ($conflictos > 0 && !$confirm) {
            $_SESSION['flash']['warn_overwrite'] = [
                'estudiante' => $sid, 'desde' => $from, 'hasta' => $to,
                'tipo' => $short, 'justificacion' => $just, 'n' => $conflictos,
            ];
            $this->redirect('/events/create?estudiante=' . $sid);
        }

        $res = Event::applyRange($sid, $from, $to, $short, $just, userId(), false);
        if (!$res['ok']) {
            $_SESSION['flash']['error'] = 'No se pudo guardar: ' . $res['error'];
        } else {
            $st = Student::find($sid);
            $_SESSION['flash']['ok'] = 'Registrado: ' . event_label(Event::shortToType($short))
                . " para " . ($st->nombre_completo ?? "estudiante #$sid")
                . " entre $from y $to ({$res['total']} día(s), feriados excluidos)."
                . ($conflictos > 0 ? " Se sobrescribieron $conflictos registro(s) previo(s)." : '');
        }
        $this->redirect('/events');
    }

    // ------------------------------------------------------------------
    // EDITAR individual
    // ------------------------------------------------------------------
    public function edit($id) {
        $this->requireAuth();
        $this->requireRegister();
        $ev = Event::find((int)$id);
        if (!$ev) { http_response_code(404); $this->view('errors/404'); return; }
        if (!$this->canManage($ev->id_usuario_registro)) {
            $_SESSION['flash']['error'] = 'Solo puedes editar tus propios registros.';
            $this->redirect('/events');
        }
        $this->view('events/form', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'event'      => $ev,
            'studentId'  => (int)$ev->id_estudiante,
            'mode'       => 'edit',
            'warn'       => null,
        ]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireRegister();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/events');
        }
        $ev = Event::find((int)$id);
        if (!$ev) { $_SESSION['flash']['error'] = 'Evento inexistente.'; $this->redirect('/events'); }
        if (!$this->canManage($ev->id_usuario_registro)) {
            $_SESSION['flash']['error'] = 'Solo puedes editar tus propios registros.';
            $this->redirect('/events');
        }
        $p = $this->getPostData();
        $type = Event::shortToType(strtoupper(trim((string)($p['tipo'] ?? ''))));
        if (!$type) { $_SESSION['flash']['error'] = 'Tipo inválido.'; $this->redirect('/events/' . $id . '/edit'); }

        $ev->tipo_evento  = $type;
        $ev->fecha_evento = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($p['fecha_desde'] ?? ''))
            ? $p['fecha_desde'] : $ev->fecha_evento;
        $ev->justificacion = trim((string)($p['justificacion'] ?? '')) ?: null;
        $ev->save();
        $_SESSION['flash']['ok'] = 'Evento actualizado.';
        $this->redirect('/events');
    }

    // ------------------------------------------------------------------
    // BORRAR  POST /events/{id}/delete
    // ------------------------------------------------------------------
    public function destroy($id) {
        $this->requireAuth();
        $this->requireRegister();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/events');
        }
        $ev = Event::find((int)$id);
        if (!$ev) { $_SESSION['flash']['error'] = 'Evento inexistente.'; $this->redirect('/events'); }
        if (!$this->canManage($ev->id_usuario_registro)) {
            $_SESSION['flash']['error'] = 'Solo puedes eliminar tus propios registros.';
            $this->redirect('/events');
        }
        $ev->delete();
        $_SESSION['flash']['ok'] = 'Evento eliminado (el alumno vuelve a Presente implícito en esa fecha).';
        $this->redirect('/events');
    }
}
