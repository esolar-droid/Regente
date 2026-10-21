<?php
/**
 * EventController — «Registro de Eventos» (v11, rediseño con pestañas)
 *
 * Página única con dos pestañas:
 *   Pestaña 1  Registro masivo por curso (Lun–Sáb, clic P→A→L→F→P)
 *   Pestaña 2  Registro individual (curso → estudiante → rango → tipo)
 *
 * Rutas:
 *   GET  /events            Registro de Eventos (pestañas masiva|individual)
 *                            ?tab=masiva|individual  ·  ?curso=&fecha=  ·  ?saved=1
 *   GET  /attendance        Redirige a /events (compatibilidad con enlaces viejos)
 *   POST /attendance/save   Guarda la grilla masiva (solo la fecha elegida)
 *   GET  /events/create     Redirige a /events?tab=individual (con ?estudiante= si aplica)
 *   POST /events/store      Guarda el registro individual (rango A/L/F + justificación)
 *   POST /events/{id}/delete Elimina un evento (admin o dueño del registro)
 *
 * Reglas de negocio pactadas:
 *   - Pueden registrar: administrador y regente. Profesor NO tiene acceso.
 *   - El regente ve sus eventos y los del administrador, pero solo edita/borra
 *     los propios. El administrador puede con todos.
 *   - Solo se guardan atrasos/faltas/licencias; presente = sin registro (P).
 *   - Feriados (tabla `feriados`) precargan la rejilla semanal como días
 *     INHÁBILES: se muestran bloqueados aunque la fecha elegida sea feriado,
 *     y se saltan en los rangos individuales.
 *   - Todos los cursos marcan Lunes a Sábado (domingo fuera).
 *   - En la grilla solo es editable la columna de la fecha elegida; los días
 *     anteriores y posteriores son visibles pero no editables. Se precarga la
 *     última información guardada; si es la primera vez, todos con P.
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

    /** Semana LUNES a SÁBADO que contiene $date (domingo fuera). */
    private function weekDates($date) {
        $ts = strtotime((string)$date ?: 'now');
        if ($ts === false) $ts = time();
        $dow = (int)date('N', $ts);              // 1=Lun .. 7=Dom
        // Si es domingo, la rejilla corresponde a la semana que terminó
        if ($dow === 7) $ts = strtotime('-1 day', $ts);
        $dow = min(6, (int)date('N', $ts));
        $monday = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
        $days = [];
        for ($i = 0; $i < 6; $i++) {             // Lun..Sáb siempre
            $days[] = date('Y-m-d', strtotime($monday . " +$i days"));
        }
        return $days;
    }

    /** Etiquetas L,M,X,J,V,S con día numérico. Miércoles = X (por diseño). */
    private static $DAY_LABELS = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S'];

    // ------------------------------------------------------------------
    // REGISTRO DE EVENTOS (pestañas)  GET /events?tab=masiva|individual
    // ------------------------------------------------------------------
    public function index() {
        $this->requireAuth();
        $this->requireRegister();

        $q       = $this->getQueryData();
        $tab     = ($q['tab'] ?? '') === 'individual' ? 'individual' : 'masiva';

        // ---------- PESTAÑA 1: datos para la grilla masiva ----------
        $courses = Course::orderBy('curso');
        $courseId = (int)($q['curso'] ?? 0);
        $date     = (string)($q['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $course = null;
        foreach ($courses as $c) if ((int)$c['id'] === $courseId) { $course = $c; break; }

        $week = []; $roster = []; $marks = []; $feriados = [];
        if ($course) {
            $week = $this->weekDates($date);                    // Lunes..Sábado
            $roster = Event::rosterByCourse($courseId);
            $ids = array_column($roster, 'id');
            $feriados = Feriado::mapForDates($week);            // días inhábiles precargados
            foreach ($week as $d) {
                $marks[$d] = Event::dayMarks($ids, $d);         // última info guardada (o P)
            }
        }

        // Modal post-guardado: "Se guardó… / Cerrar / Enviar mensajes"
        $waData = $_SESSION['flash']['wa'] ?? null;
        unset($_SESSION['flash']['wa']);

        // ---------- PESTAÑA 2: formulario individual ----------
        $warn = $_SESSION['flash']['warn_overwrite'] ?? null;
        unset($_SESSION['flash']['warn_overwrite']);

        // Prellenado del formulario individual (incluye regreso con errores/aviso)
        $indPrefill = [
            'desde' => '', 'hasta' => '', 'tipo' => '', 'justificacion' => '',
        ];
        foreach (['fecha_desde', 'fecha_hasta', 'tipo', 'justificacion'] as $k) {
            if (isset($q[$k])) $indPrefill[($k === 'fecha_desde' ? 'desde' : ($k === 'fecha_hasta' ? 'hasta' : ($k === 'justificacion' ? 'justificacion' : 'tipo')))] = (string)$q[$k];
        }

        $studentId = (int)($q['estudiante'] ?? 0);
        $editEvent = null;
        if (!empty($q['editar'])) {                             // editar registro existente
            $editEvent = Event::find((int)$q['editar']);
            if ($editEvent && $this->canManage($editEvent->id_usuario_registro)) {
                $studentId = (int)$editEvent->id_estudiante;
            } else {
                $editEvent = null;
            }
        }
        $student = $studentId ? Student::find($studentId) : null;
        if (!$student) $studentId = 0;

        // Estudiantes del curso elegido en la pestaña individual
        $indCourseId = (int)($q['icurso'] ?? ($student->id_curso ?? 0));
        $indStudents = [];
        if ($indCourseId > 0) {
            try {
                $db = \Database::getConnection();
                $stmt = $db->prepare(
                    "SELECT e.id, e.nombre_completo, e.codigo_bnb
                     FROM estudiantes e
                     JOIN gestion g ON g.anio = YEAR(CURDATE()) AND g.estado = 'activa'
                     WHERE e.id_curso = ? AND e.id_gestion = g.id AND e.estado <> 'retirado'
                     ORDER BY e.nombre_completo"
                );
                $stmt->execute([$indCourseId]);
                $indStudents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ex) {
                error_log('EventController.index(ind): ' . $ex->getMessage());
            }
        }

        $this->view('events/index', [
            'csrf_token'   => CsrfMiddleware::generateToken(),
            'tab'          => $tab,
            // masiva
            'courses'      => $courses,
            'course'       => $course,
            'courseId'     => $courseId,
            'date'         => $date,
            'week'         => $week,
            'dayLabels'    => self::$DAY_LABELS,
            'roster'       => $roster,
            'marks'        => $marks,
            'feriados'     => $feriados,
            'today'        => date('Y-m-d'),
            'stats'        => Event::getStatistics(),
            'saved'        => !empty($q['saved']),
            'waData'       => $waData,
            // individual
            'studentId'    => $studentId,
            'student'      => $student,
            'event'        => $editEvent,
            'mode'         => $editEvent ? 'edit' : 'create',
            'warn'         => $warn,
            'indCourseId'  => $indCourseId,
            'indStudents'  => $indStudents,
            'indPrefill'   => $indPrefill,
        ]);
    }

    /** Compatibilidad: /attendance → pestaña masiva de /events */
    public function attendance() {
        $this->requireAuth();
        $this->requireRegister();
        $qs = [];
        foreach (['curso', 'fecha', 'saved'] as $k) {
            if (!empty($_GET[$k])) $qs[$k] = $_GET[$k];
        }
        $qs['tab'] = 'masiva';
        $this->redirect('/events?' . http_build_query($qs));
    }

    // ------------------------------------------------------------------
    // GUARDAR GRILLA  POST /attendance/save
    // ------------------------------------------------------------------
    public function saveAttendance() {
        $this->requireAuth();
        $this->requireRegister();

        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.';
            $this->redirect('/events?tab=masiva');
        }

        $p        = $this->getPostData();
        $courseId = (int)($p['curso'] ?? 0);
        $date     = (string)($p['fecha'] ?? '');
        $entries  = $p['marcas'] ?? [];   // [id_estudiante] => ['t'=>'A|L|F|P','j'=>'texto']

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $courseId <= 0) {
            $_SESSION['flash']['error'] = 'Curso/fecha inválidos.';
            $this->redirect('/events?tab=masiva');
        }
        if (Feriado::exists($date)) {
            $_SESSION['flash']['error'] = 'La fecha seleccionada es feriado: no se puede registrar asistencia.';
            $this->redirect("/events?tab=masiva&curso=$courseId&fecha=$date");
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
        $this->redirect("/events?tab=masiva&curso=$courseId&fecha=$date&saved=1");
    }

    // ------------------------------------------------------------------
    // ALTA INDIVIDUAL  GET /events/create
    // ------------------------------------------------------------------
    /** Compatibilidad: /events/create → pestaña individual de /events */
    public function create() {
        $this->requireAuth();
        $this->requireRegister();
        $qs = ['tab' => 'individual'];
        if (!empty($_GET['estudiante'])) $qs['estudiante'] = (int)$_GET['estudiante'];
        if (!empty($_GET['icurso']))     $qs['icurso']     = (int)$_GET['icurso'];
        if (!empty($_GET['editar']))     $qs['editar']     = (int)$_GET['editar'];
        // Si venía una advertencia de sobrescritura pendiente, index() la muestra
        $this->redirect('/events?' . http_build_query($qs));
    }

    public function store() {
        $this->requireAuth();
        $this->requireRegister();

        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido. Recarga la página.';
            $this->redirect('/events?tab=individual');
        }

        $p       = $this->getPostData();
        $sid     = (int)($p['estudiante'] ?? 0);
        $from    = (string)($p['fecha_desde'] ?? '');
        $to      = (string)($p['fecha_hasta'] ?? $from);
        $short   = strtoupper(trim((string)($p['tipo'] ?? '')));
        $just    = trim((string)($p['justificacion'] ?? ''));
        $confirm = !empty($p['confirmar']);

        $back = '/events?tab=individual&icurso=' . (int)($p['curso'] ?? 0) . '&estudiante=' . $sid
             . '&fecha_desde=' . urlencode($from) . '&fecha_hasta=' . urlencode($to)
             . '&tipo=' . urlencode($short) . '&justificacion=' . urlencode($just);
        $err = [];
        $student = $sid > 0 ? Student::find($sid) : null;
        if (!$student)                                            $err[] = 'Estudiante no válido.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))         $err[] = 'Fecha de inicio inválida.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))           $err[] = 'Fecha final inválida.';
        if ($from && $to && $to < $from)                          $err[] = 'La fecha final no puede ser anterior a la de inicio.';
        if (!Event::shortToType($short))                          $err[] = 'Tipo de evento no válido (usa Atraso/Licencia/Falta).';
        if ($err) {
            $_SESSION['flash']['error'] = implode(' ', $err);
            $this->redirect($back);
        }

        // ¿Días del rango con registros previos? → avisar ANTES de sobrescribir
        $preview    = Event::applyRange($sid, $from, $to, $short, $just, userId(), true);
        $conflictos = (int)($preview['conflictos'][0]['n'] ?? 0);
        if ($conflictos > 0 && !$confirm) {
            $_SESSION['flash']['warn_overwrite'] = [
                'estudiante' => $sid, 'desde' => $from, 'hasta' => $to,
                'tipo' => $short, 'justificacion' => $just, 'n' => $conflictos,
            ];
            $this->redirect($back);
        }

        // Si el formulario venía en modo edición (registro ya guardado), se
        // actualiza ese evento en lugar de crear uno duplicado.
        $editId = (int)($p['id'] ?? 0);
        if ($editId > 0) {
            $evEdit = Event::find($editId);
            if ($evEdit && $this->canManage($evEdit->id_usuario_registro)) {
                $evEdit->tipo_evento   = Event::shortToType($short);
                $evEdit->fecha_evento  = $from;
                $evEdit->justificacion = $just !== '' ? $just : null;
                $evEdit->save();
                $_SESSION['flash']['ok'] = 'Registro individual actualizado (' . event_label(Event::shortToType($short))
                    . " del $from al $to). La planilla refleja el cambio en la semana correspondiente.";
                $this->redirect('/events?tab=masiva&curso=' . (int)($p['curso'] ?? 0) . '&fecha=' . urlencode($from));
            }
        }

        $res = Event::applyRange($sid, $from, $to, $short, $just, userId(), false);
        if (!$res['ok']) {
            $_SESSION['flash']['error'] = 'No se pudo guardar: ' . $res['error'];
        } else {
            $_SESSION['flash']['ok'] = 'Registrado: ' . event_label(Event::shortToType($short))
                . " para " . ($student->nombre_completo ?? "estudiante #$sid")
                . " entre $from y $to ({$res['total']} día(s), feriados excluidos)."
                . ($conflictos > 0 ? " Se sobrescribieron $conflictos registro(s) previo(s)." : '')
                . ' La planilla semanal refleja estos días si corresponden al rango.';
        }
        $this->redirect('/events?tab=masiva&curso=' . (int)($p['curso'] ?? 0) . '&fecha=' . urlencode($from));
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
            $this->redirect('/events?tab=individual');
        }
        // El formulario vive en la pestaña 2 de /events (se precarga vía ?editar=)
        $this->redirect('/events?tab=individual&editar=' . (int)$id);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireRegister();
        if (!CsrfMiddleware::validateToken()) {
            $_SESSION['flash']['error'] = 'Token CSRF inválido.';
            $this->redirect('/events?tab=individual&editar=' . (int)$id);
        }
        $ev = Event::find((int)$id);
        if (!$ev) { $_SESSION['flash']['error'] = 'Evento inexistente.'; $this->redirect('/events?tab=individual'); }
        if (!$this->canManage($ev->id_usuario_registro)) {
            $_SESSION['flash']['error'] = 'Solo puedes editar tus propios registros.';
            $this->redirect('/events?tab=individual');
        }
        $p = $this->getPostData();
        $type = Event::shortToType(strtoupper(trim((string)($p['tipo'] ?? ''))));
        if (!$type) { $_SESSION['flash']['error'] = 'Tipo inválido.'; $this->redirect('/events?tab=individual&editar=' . (int)$id); }

        $ev->tipo_evento  = $type;
        $ev->fecha_evento = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($p['fecha_desde'] ?? ''))
            ? $p['fecha_desde'] : $ev->fecha_evento;
        $ev->justificacion = trim((string)($p['justificacion'] ?? '')) ?: null;
        $ev->save();
        $_SESSION['flash']['ok'] = 'Evento actualizado. La planilla refleja el cambio en la semana correspondiente.';
        $this->redirect('/events?tab=masiva&curso=' . (int)($p['curso'] ?? 0) . '&fecha=' . urlencode((string)$ev->fecha_evento));
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
        $this->redirect('/events?tab=individual');
    }
}
