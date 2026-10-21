<?php
/**
 * ReportController — Hoja «Reportes» con 6 pestañas:
 *   1. Individual : estudiante (búsqueda predictiva) -> detalle gestión a hoy
 *                   + torta P/A y histograma mensual A vs F (opcional en PDF).
 *   2. Por días   : curso + rango + profesor + asignatura -> rejilla solo con
 *                   los días en que ese par dicta esa asignatura + 4 gráficos.
 *   3. Semanal    : nivel (Primaria/Secundaria) + semana -> una hoja por curso.
 *   4. Mensual    : curso + mes -> rejilla del mes + sumas P/A/L/F por alumno.
 *   5. Lapso      : curso + rango (típico trimestral, horizontal) -> mes/día.
 *   6. Anual      : curso -> sumas mensuales por estudiante.
 *
 * Exportación común: CSV (datos puros) y PDF vía «Imprimir» del navegador
 * (plantilla standalone printable.php), carta/A4, vertical/horizontal,
 * márgenes estrechos, colores institucionales y logotipo. Totales 0 = blanco.
 */

namespace App\Controllers;

use App\Models\Report;
use App\Models\Student;
use App\Models\Course;
use App\Models\Event;
use App\Middleware\CsrfMiddleware;

class ReportController extends Controller {

    const LOGO_URL = 'https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png';

    // v16.2: MES_ES/DIA_ES 0-based ([0=>Ene...[11=>Dic]) para que json_encode
    // los serialice como ARRAY (lista) y no como OBJETO JSON. Con claves 1..12
    // PHP los convertía en {"1":"Ene",...} y Chart.js recibía un objeto como
    // labels/datos -> ejes duplicados ("Ene..Dic" + "1..12") y celdas vacías.
    const MES_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const DIA_ES = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

    /** Día hábil estándar del sistema: Lunes a Viernes (sin feriados). */
    private function businessDays($from, $to) {
        return Report::businessDays($from, $to, Report::holidayMap($from, $to));
    }

    // ====================================================================
    // Índice: 6 pestañas
    // ====================================================================
    /** Módulo exclusivo del administrador (regente). */
    private function guard() {
        $this->requireAuth();
        if (!isAdmin() && !isRegente()) {
            $this->redirect('/403');
        }
    }

    public function index() {
        $this->guard();
        $tabs = ['individual', 'dias', 'semanal', 'mensual', 'lapso', 'anual'];
        $tab  = (string)($_GET['tab'] ?? 'individual');
        if (!in_array($tab, $tabs, true)) $tab = 'individual';

        $data = [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'tab'        => $tab,
            'courses'    => Report::courses(),
            'teachers'   => Report::teachers(),
            'subjects'   => Report::subjects(),
            'anio'       => Report::activeManagement()['anio'],
            'result'     => null,
        ];

        switch ($tab) {
            case 'individual': $data['result'] = $this->buildIndividual($_GET); break;
            case 'dias':       $data['result'] = $this->buildByDays($_GET); break;
            case 'semanal':    $data['result'] = $this->buildWeekly($_GET); break;
            case 'mensual':    $data['result'] = $this->buildMonthly($_GET); break;
            case 'lapso':      $data['result'] = $this->buildRange($_GET); break;
            case 'anual':      $data['result'] = $this->buildAnnual($_GET); break;
        }

        $this->view('reports/index', $data);
    }

    // ====================================================================
    // Constructores de reporte (?tab=...&generar=1)
    // ====================================================================

    private function buildIndividual(array $q) {
        if (empty($q['generar'])) return null;
        $sid = (int)($q['estudiante'] ?? 0);
        $st  = $sid ? Student::find($sid) : null;
        if (!$st) return ['error' => 'Selecciona un estudiante válido (usa la búsqueda predictiva).'];

        $anio = Report::activeManagement()['anio'];
        $from = sprintf('%04d-01-01', $anio);
        $to   = date('Y-m-d');

        $marks  = Report::marks([$sid], $from, $to);
        $bdays  = $this->businessDays($from, $to);
        $grid   = Report::matrix([['id' => $sid]], $bdays, $marks);
        $totals = Report::totals($grid)[$sid] ?? ['P'=>0,'A'=>0,'L'=>0,'F'=>0];
        $course = Course::find((int)$st->id_curso);

        return [
            'kind'      => 'individual',
            'student'   => ['id' => $sid, 'nombre' => $st->nombre_completo, 'curso' => $course ? $course->curso : '—'],
            'from'      => $from, 'to' => $to,
            'detail'    => Report::detailForStudent($sid, $from, $to),
            'monthly'   => Report::monthlyCounts($sid, $from, $to),
            'totals'    => $totals,
            'habiles'   => count($bdays),
            'offgrid'   => Report::offGridEvents([['id' => $sid]], $bdays, $marks)[$sid] ?? [],
        ];
    }

    private function buildByDays(array $q) {
        if (empty($q['generar'])) return null;
        $cid = (int)($q['curso'] ?? 0);
        $from = (string)($q['desde'] ?? '');
        $to   = (string)($q['hasta'] ?? '');
        if (!$cid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            return ['error' => 'Completa curso y un rango de fechas válido.'];
        }
        $tid  = (int)($q['profesor'] ?? 0);
        $subj = trim((string)($q['asignatura'] ?? ''));

        // v17.1: filtro por tipo de evento ('' = Todos, A, L o F).
        $tipo = strtoupper(trim((string)($q['tipo_evento'] ?? '')));
        if (!in_array($tipo, ['A', 'L', 'F'], true)) $tipo = '';
        $tipos = $tipo !== '' ? [$tipo] : [];

        $roster = Report::roster($cid);
        if (!$roster) return ['error' => 'No hay estudiantes activos en el curso seleccionado.'];
        $ids   = array_map(fn($s) => (int)$s['id'], $roster);
        $marks = Report::marks($ids, $from, $to, $tipos);

        // Días hábiles base del rango (totales correctos aunque no se muestren)
        $allBdays = $this->businessDays($from, $to);

        // Columnas mostradas: solo los días de semana en que el par dicta clase
        $daysWithClass = [];
        try {
            $db = \Database::getConnection();
            $conds = ''; $prm = [(int)substr($from, 0, 4)];
            if ($tid)  { $conds .= ' AND id_profesor = ?'; $prm[] = $tid; }
            if ($subj !== '') { $conds .= ' AND asignatura = ?'; $prm[] = $subj; }
            $stmt = $db->prepare("SELECT DISTINCT dia FROM horarios WHERE anio = ?" . $conds);
            $stmt->execute($prm);
            $diaSem = ['lunes'=>1,'martes'=>2,'miercoles'=>3,'jueves'=>4,'viernes'=>5];
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $dn) {
                $key = mb_strtolower(preg_replace('/[áéíóú]/iu', '', (string)$dn), 'UTF-8');
                if (isset($diaSem[$key])) $daysWithClass[$diaSem[$key]] = true;
            }
        } catch (\Throwable $e) { error_log('buildByDays dias: ' . $e->getMessage()); }

        $bdays = array_values(array_filter($allBdays, function ($d) use ($daysWithClass) {
            return empty($daysWithClass) || isset($daysWithClass[(int)date('N', strtotime($d))]);
        }));

        // v17.2: con filtro de tipo (A/L/F), la tabla muestra SOLO los estudiantes
        // que tienen al menos un evento de ese tipo en el rango; sin filtro (Todos)
        // se mantiene el roster completo.
        if ($tipo !== '') {
            $roster = array_values(array_filter($roster, function ($s) use ($marks, $bdays, $tipo) {
                $sid = (int)$s['id'];
                foreach ($bdays as $d) {
                    if (($marks[$sid][$d]['t'] ?? '') === $tipo) return true;
                }
                return false;
            }));
            if (!$roster) {
                return ['error' => 'Ningún estudiante del curso tiene eventos del tipo seleccionado en el rango indicado.'];
            }
        }

        $grid = Report::matrix($roster, $bdays, $marks);

        // Totales sobre TODOS los días hábiles del rango (no solo los mostrados)
        $totals = [];
        foreach ($roster as $s) {
            $sid = (int)$s['id'];
            $cnt = ['A'=>0,'L'=>0,'F'=>0];
            foreach (($marks[$sid] ?? []) as $d => $m) {
                if (in_array($d, $allBdays, true)) $cnt[$m['t']]++;
            }
            if ($tipo !== '') {
                // Con filtro de tipo: P no aplica; se muestran solo los del tipo elegido.
                $totals[$sid] = ['P' => null, 'A' => $cnt['A'], 'L' => $cnt['L'], 'F' => $cnt['F']];
            } else {
                $totals[$sid] = ['P' => max(0, count($allBdays) - $cnt['A'] - $cnt['L'] - $cnt['F']),
                                 'A' => $cnt['A'], 'L' => $cnt['L'], 'F' => $cnt['F']];
            }
        }

        // Agregados para los 4 gráficos (sobre los días mostrados)
        $sum = ['P'=>0,'A'=>0,'L'=>0,'F'=>0];
        $acc = ['A'=>0,'L'=>0,'F'=>0]; $lineSeries = [];
        foreach ($bdays as $d) {
            foreach ($roster as $s) {
                $m = $marks[(int)$s['id']][$d] ?? null;
                if ($m) { $sum[$m['t']]++; $acc[$m['t']]++; }
                elseif ($tipo === '') { $sum['P']++; } // sin filtro: cuenta como presente
            }
            $lineSeries[] = ['d' => $d, 'A' => $acc['A'], 'L' => $acc['L'], 'F' => $acc['F']];
        }
        $perStudent = [];
        foreach ($roster as $i => $s) {
            $t = $totals[(int)$s['id']];
            $perStudent[] = ['n' => $i + 1, 'nombre' => $s['nombre_completo'], 'A' => $t['A'], 'L' => $t['L'], 'F' => $t['F']];
        }

        $course = Course::find($cid);
        return [
            'kind' => 'dias',
            'header' => ['curso' => $course ? $course->curso : ('#' . $cid), 'desde' => $from, 'hasta' => $to,
                         'profesor' => $this->teacherName($tid), 'asignatura' => $subj !== '' ? $subj : 'Todas',
                         'tipo' => $tipo], // v17.1: '' = Todos | A | L | F
            'roster' => $roster, 'bdays' => $bdays, 'grid' => $grid, 'totals' => $totals,
            'marks' => $marks, 'sum' => $sum, 'perStudent' => $perStudent, 'lineSeries' => $lineSeries,
            'filteredBySchedule' => !empty($daysWithClass),
        ];
    }

    private function teacherName($tid) {
        if (!$tid) return 'Todos';
        foreach (Report::teachers() as $t) if ((int)$t['id'] === $tid) return $t['nombre'];
        return "#$tid";
    }

    private function buildWeekly(array $q) {
        if (empty($q['generar'])) return null;
        $nivel = ($q['nivel'] ?? 'Primaria') === 'Secundaria' ? 'Secundaria' : 'Primaria';
        $date  = (string)($q['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $week  = Report::weekDays($date);
        $hol   = Report::holidayMap($week[0], end($week));
        $bdays = array_values(array_filter($week, fn($d) => !isset($hol[$d])));

        $blocks = [];
        foreach (Report::coursesWithRoster($nivel) as $cw) {
            $ids = array_map(fn($s) => (int)$s['id'], $cw['roster']);
            $marks = Report::marks($ids, $week[0], end($week));
            $grid  = Report::matrix($cw['roster'], $bdays, $marks);
            $blocks[] = [
                'course' => $cw['course'], 'roster' => $cw['roster'],
                'bdays' => $bdays, 'holidays' => $hol, 'grid' => $grid,
                'totals' => Report::totals($grid),
            ];
        }
        if (!$blocks) return ['error' => 'No hay cursos con estudiantes activos en ' . $nivel . '.'];

        return ['kind' => 'semanal', 'nivel' => $nivel, 'week' => $week, 'holidays' => $hol, 'blocks' => $blocks];
    }

    private function buildMonthly(array $q) {
        if (empty($q['generar'])) return null;
        $cid = (int)($q['curso'] ?? 0);
        $year  = (int)($q['anio'] ?? date('Y'));
        $month = max(1, min(12, (int)($q['mes'] ?? date('n'))));
        if (!$cid) return ['error' => 'Selecciona un curso.'];

        $first = sprintf('%04d-%02d-01', $year, $month);
        $last  = date('Y-m-t', strtotime($first));
        $hol   = Report::holidayMap($first, $last);

        $days = [];
        for ($d = strtotime($first); $d <= strtotime($last); $d = strtotime('+1 day', $d)) {
            $w = (int)date('N', $d);
            if ($w > 5) continue;
            $iso = date('Y-m-d', $d);
            $days[] = ['f' => $iso, 'n' => (int)date('j', $d), 'w' => self::DIA_ES[$w - 1], 'inh' => isset($hol[$iso])];
        }

        $roster = Report::roster($cid);
        if (!$roster) return ['error' => 'No hay estudiantes activos en el curso seleccionado.'];
        $ids   = array_map(fn($s) => (int)$s['id'], $roster);
        $marks = Report::marks($ids, $first, $last);

        $bdays = array_column(array_filter($days, fn($d) => !$d['inh']), 'f');
        $grid  = Report::matrix($roster, $bdays, $marks);
        $course = Course::find($cid);

        return [
            'kind' => 'mensual',
            'header' => ['curso' => $course ? $course->curso : ('#' . $cid), 'mes' => self::MES_ES[$month-1] . ' ' . $year],
            'roster' => $roster, 'days' => $days, 'bdays' => $bdays, 'grid' => $grid,
            'totals' => Report::totals($grid), 'marks' => $marks,
            'chart' => $this->dailyChart($ids, $first, $last, $bdays),
        ];
    }

    /** Serie diaria + acumulada A/L/F (gráficos de mensual y lapso). */
    private function dailyChart(array $ids, $from, $to, array $bdays) {
        $marks = Report::marks($ids, $from, $to);
        $perDay = []; $acc = ['A'=>0,'L'=>0,'F'=>0]; $accSeries = [];
        foreach ($bdays as $d) {
            $c = ['A'=>0,'L'=>0,'F'=>0];
            foreach ($ids as $sid) {
                $m = $marks[$sid][$d] ?? null;
                if ($m) $c[$m['t']]++;
            }
            $perDay[] = ['d' => $d, 'A' => $c['A'], 'L' => $c['L'], 'F' => $c['F']];
            foreach (['A','L','F'] as $k) $acc[$k] += $c[$k];
            $accSeries[] = ['d' => $d, 'A' => $acc['A'], 'L' => $acc['L'], 'F' => $acc['F']];
        }
        return ['perDay' => $perDay, 'acc' => $accSeries];
    }

    private function buildRange(array $q) {
        if (empty($q['generar'])) return null;
        $cid  = (int)($q['curso'] ?? 0);
        $from = (string)($q['desde'] ?? '');
        $to   = (string)($q['hasta'] ?? '');
        if (!$cid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            return ['error' => 'Selecciona curso y rango de fechas válido.'];
        }
        $roster = Report::roster($cid);
        if (!$roster) return ['error' => 'No hay estudiantes activos en el curso seleccionado.'];
        $ids   = array_map(fn($s) => (int)$s['id'], $roster);
        $marks = Report::marks($ids, $from, $to);
        $bdays = $this->businessDays($from, $to); // strings ISO (los metadatos van en 'days')

        $days = [];
        foreach ($bdays as $d) {
            $ts = strtotime($d);
            $days[] = ['f' => $d, 'm' => self::MES_ES[(int)date('n', $ts)-1], 'n' => (int)date('j', $ts),
                       'w' => self::DIA_ES[(int)date('N', $ts) - 1]];
        }
        $grid = Report::matrix($roster, $bdays, $marks);
        $course = Course::find($cid);

        return [
            'kind' => 'lapso',
            'header' => ['curso' => $course ? $course->curso : ('#' . $cid), 'desde' => $from, 'hasta' => $to],
            'roster' => $roster, 'days' => $days, 'bdays' => $bdays, 'grid' => $grid,
            'totals' => Report::totals($grid), 'marks' => $marks,
            'chart' => $this->dailyChart($ids, $from, $to, $bdays),
        ];
    }

    private function buildAnnual(array $q) {
        if (empty($q['generar'])) return null;
        $cid  = (int)($q['curso'] ?? 0);
        $year = (int)($q['anio'] ?? Report::activeManagement()['anio']);
        if (!$cid) return ['error' => 'Selecciona un curso.'];

        // v16.3: la nomina se lista de la gestion del anio consultado (antes
        // siempre usaba la gestion activa -> reportes anuales de anios pasados
        // salian vacios o con estudiantes equivocados).
        $roster = Report::roster($cid, $year);
        if (!$roster) return ['error' => 'No hay estudiantes en el curso seleccionado para esa gestion.'];
        $ids   = array_map(fn($s) => (int)$s['id'], $roster);
        $from  = sprintf('%04d-01-01', $year);
        $to    = sprintf('%04d-12-31', $year);

        // v16.3 FIX memory-exhausted: los totales mensuales se agregan EN SQL
        // (marksAgg con GROUP BY). Antes marks() traia TODAS las filas del ano
        // y cada rebuild (pantalla, CSV y PDF) las expandia en arrays PHP de
        // estudiante x fecha x tipo -> 500+ MB en colegios con mucha asistencia.
        $agg = Report::marksAgg($ids, $from, $to);

        $perStudent = [];
        $grand = ['A'=>array_fill(0,12,0),'L'=>array_fill(0,12,0),'F'=>array_fill(0,12,0)];
        foreach ($roster as $s) {
            $sid = (int)$s['id'];
            $mo  = [];
            $tot = ['A'=>0,'L'=>0,'F'=>0];
            for ($m = 0; $m < 12; $m++) {
                $row = ['A'=>0,'L'=>0,'F'=>0];
                foreach (['A','L','F'] as $k) {
                    $row[$k] = (int)($agg[$sid][$m][$k] ?? 0);
                    if ($row[$k]) { $grand[$k][$m] += $row[$k]; $tot[$k] += $row[$k]; }
                }
                $mo[$m] = $row;
            }
            $perStudent[] = ['id' => $sid, 'nombre' => $s['nombre_completo'], 'mes' => $mo, 'tot' => $tot];
        }
        $course = Course::find($cid);
        return [
            'kind' => 'anual',
            'header' => ['curso' => $course ? $course->curso : ('#' . $cid), 'anio' => $year],
            'perStudent' => $perStudent, 'grand' => $grand,
            'mesEs' => self::MES_ES,
        ];
    }

    // ====================================================================
    // Helpers compartidos por vista y exportadores
    // ====================================================================

    /** Celdas de totales: 0 => '' (quedan vacías en rejillas y CSV). */
    public static function z($v) { return ((int)$v) > 0 ? (string)(int)$v : ''; }

    private function title(array $r) {
        switch ($r['kind'] ?? '') {
            case 'individual': return 'Reporte Individual — ' . ($r['student']['nombre'] ?? '') . ' (' . ($r['student']['curso'] ?? '') . ')';
            case 'dias':       $tl = ['A'=>' · solo Atrasos','L'=>' · solo Licencias','F'=>' · solo Faltas'][$r['header']['tipo'] ?? ''] ?? '';
                               return 'Reporte por Días — Curso ' . $r['header']['curso'] . ' · ' . $r['header']['desde'] . ' a ' . $r['header']['hasta'] . $tl;
            case 'semanal':    return 'Reporte Semanal — ' . $r['nivel'] . ' · Semana del ' . ($r['week'][0] ?? '');
            case 'mensual':    return 'Reporte Mensual — Curso ' . $r['header']['curso'] . ' · ' . $r['header']['mes'];
            case 'lapso':      return 'Reporte de Lapso — Curso ' . $r['header']['curso'] . ' · ' . $r['header']['desde'] . ' a ' . $r['header']['hasta'];
            case 'anual':      return 'Reporte Anual — Curso ' . $r['header']['curso'] . ' · ' . $r['header']['anio'];
        }
        return 'Reporte';
    }

    /** Reconstruye el resultado desde la query para exportar. */
    private function rebuild(array $q) {
        $tab = (string)($q['tab'] ?? 'individual');
        $q['generar'] = 1;
        switch ($tab) {
            case 'individual': return $this->buildIndividual($q);
            case 'dias':       return $this->buildByDays($q);
            case 'semanal':    return $this->buildWeekly($q);
            case 'mensual':    return $this->buildMonthly($q);
            case 'lapso':      return $this->buildRange($q);
            case 'anual':      return $this->buildAnnual($q);
        }
        return null;
    }

    // ====================================================================
    // Exportación CSV (datos puros, separador ; para Excel es-BO)
    // ====================================================================
    public function exportCsv() {
        $this->guard();
        $r = $this->rebuild($_GET);
        if (!$r || !empty($r['error'])) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No hay reporte para exportar: ' . ($r['error'] ?? 'parámetros inválidos');
            return;
        }

        $rows = [];
        $rows[] = [$this->title($r)];
        $rows[] = ['Colegio Marista — Sistema para Regentes', 'Generado: ' . date('d/m/Y H:i')];
        $rows[] = [];

        switch ($r['kind']) {
            case 'individual':
                $rows[] = ['Estudiante', $r['student']['nombre']];
                $rows[] = ['Curso', $r['student']['curso']];
                $rows[] = ['Periodo', $r['from'] . ' a ' . $r['to'] . ' (' . $r['habiles'] . ' días hábiles)'];
                $rows[] = [];
                $t = $r['totals'];
                $rows[] = ['Totales', 'Presente', 'Atraso', 'Licencia', 'Falta'];
                $rows[] = ['', self::z($t['P']), self::z($t['A']), self::z($t['L']), self::z($t['F'])];
                $rows[] = [];
                $rows[] = ['Fecha', 'Evento', 'Justificación'];
                foreach ($r['detail'] as $d) $rows[] = [$d['f'], Event::typeToShort($d['tipo_evento']) ?: '-', (string)($d['justificacion'] ?? '')];
                break;

            case 'dias':
            case 'mensual':
            case 'lapso':
                $h = $r['header'];
                $rows[] = ['Curso', $h['curso'], 'Desde', $h['desde'] ?? ($h['mes'] ?? ''), 'Hasta', $h['hasta'] ?? ''];
                if ($r['kind'] === 'dias') {
                    $tl = ['A'=>'Atrasos','L'=>'Licencias','F'=>'Faltas'][$h['tipo'] ?? ''] ?? 'Todos';
                    $rows[] = ['Profesor', $h['profesor'], 'Asignatura', $h['asignatura'], 'Tipo de evento', $tl];
                }
                $rows[] = [];
                $bdays = $r['bdays'];
                $diasFilter = ($r['kind'] === 'dias' && ($h['tipo'] ?? '') !== '');
                $head = ['Estudiante'];
                foreach ($bdays as $d) $head[] = date('d/m', strtotime(is_array($d) ? $d['f'] : $d));
                $head = array_merge($head, $diasFilter ? ['A', 'L', 'F'] : ['P', 'A', 'L', 'F']);
                $rows[] = $head;
                foreach ($r['roster'] as $s) {
                    $sid = (int)$s['id'];
                    $line = [$s['nombre_completo']];
                    foreach ($bdays as $d) {
                        $iso = is_array($d) ? $d['f'] : $d;
                        $line[] = $r['grid'][$sid][$iso]['t'] ?? ($diasFilter ? '-' : 'P');
                    }
                    $t = $r['totals'][$sid] ?? ['P'=>0,'A'=>0,'L'=>0,'F'=>0];
                    $tail = [$t['P'], self::z($t['A']), self::z($t['L']), self::z($t['F'])];
                    $rows[] = array_merge($line, $diasFilter ? array_slice($tail, 1) : $tail);
                }
                break;

            case 'semanal':
                foreach ($r['blocks'] as $b) {
                    $rows[] = ['CURSO', $b['course']['curso']];
                    $head = ['Estudiante'];
                    foreach ($b['bdays'] as $d) $head[] = date('D d/m', strtotime($d));
                    $rows[] = $head;
                    foreach ($b['roster'] as $s) {
                        $sid = (int)$s['id'];
                        $line = [$s['nombre_completo']];
                        foreach ($b['bdays'] as $d) $line[] = $b['grid'][$sid][$d]['t'] ?? 'P';
                        $rows[] = $line;
                    }
                    $rows[] = [];
                }
                break;

            case 'anual':
                $head = ['Estudiante'];
                foreach (self::MES_ES as $m) $head = array_merge($head, ["M$m-A", "M$m-L", "M$m-F"]);
                $rows[] = $head;
                foreach ($r['perStudent'] as $p) {
                    $line = [$p['nombre']];
                    for ($m = 0; $m < 12; $m++) {
                        $line[] = self::z($p['mes'][$m]['A']);
                        $line[] = self::z($p['mes'][$m]['L']);
                        $line[] = self::z($p['mes'][$m]['F']);
                    }
                    $rows[] = $line;
                }
                break;
        }

        $rows[] = [];
        $rows[] = ['LEYENDA', 'P=Presente', 'A=Atraso', 'L=Licencia', 'F=Falta', 'Totales en blanco = 0'];

        $name = 'reporte-' . $r['kind'] . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
        foreach ($rows as $row) fputcsv($out, $row, ';');
        fclose($out);
        exit;
    }

    // ====================================================================
    // Exportación PDF: plantilla standalone (impresión del navegador)
    // ====================================================================
    public function exportPdf() {
        // v16.1: doble candado. Rutas autenticadas -> requireAuth+guard normal.
        // Ruta pública /reports/export/pdf.html -> solo sirve el archivo si el
        // token _k coincide con la ruta derivada (sin sesión no hay rebuild).
        if (!empty($_GET['_p'])) {
            $key  = preg_replace('/[^a-f0-9]/', '', (string)($_GET['_k'] ?? ''));
            $file = sys_get_temp_dir() . '/repdf-' . preg_replace('/[^a-f0-9]/', '', md5($key)) . '.html';
            if (!is_file($file)) {
                http_response_code(404);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<p>El reporte expiró. Vuelve a Reportes y genera el PDF otra vez.</p>';
                exit;
            }
            if (!isAuthenticated()) { $this->requireAuth(); } // redirige a login
            if (!isAdmin() && !isRegente()) { $this->redirect('/403'); }
            header('Content-Type: text/html; charset=UTF-8');
            readfile($file);
            exit;
        }
        $this->guard();

        // El navegador abre la ventana de impresión en segundo plano y congela
        // los JS inline hasta que se enfoca -> si window.print() corre temprano
        // imprime una hoja con solo el encabezado. Solución: renderizar TODO el
        // contenido como HTML estático e imprimir recién al activarse la pestaña.

        // Blindaje anti-página-en-blanco: capturar todo error fatal y mostrarlo
        // dentro de la propia ventana de impresión en vez de truncar el HTML.
        // v16.2: no imprimir warnings dentro del HTML (rompen headers y ensucian
        // el PDF). El shutdown handler usa ob_get_clean()+echo, así que mostrar
        // errores inline solo generaba "Cannot modify header information".
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
                   . '<h2 style="color:#c0392b">No se pudo generar el reporte</h2><pre style="background:#faf3f0;padding:12px;border-radius:6px;white-space:pre-wrap">'
                   . htmlspecialchars($err['message'] . ' @ ' . $err['file'] . ':' . $err['line'])
                   . '</pre><p>Vuelve atrás y reintenta, o exporta el CSV.</p></body></html>';
            }
        });
        $obLevel = ob_get_level();

        try {
            // v16.3: techo de memoria explícito para este request
            // (hosting suele venir en 128M; con el agregado SQL basta con 256M).
            @ini_set('memory_limit', '512M');
            $r = $this->rebuild($_GET);
            if (!$r || !empty($r['error'])) {
                ob_end_clean();
                http_response_code(400);
                header('Content-Type: text/html; charset=utf-8');
                echo '<h2>No hay reporte para exportar</h2><p>' . htmlspecialchars($r['error'] ?? 'parámetros inválidos') . '</p>';
                return;
            }
            $data = [
                'result'    => $r,
                'title'     => $this->title($r),
                'logoUrl'   => self::LOGO_URL,
                'charts'    => !empty($_GET['charts']),          // default NO incluir gráficos
                'orient'    => (($_GET['orientacion'] ?? 'portrait') === 'landscape') ? 'landscape' : 'portrait',
                'paper'     => (($_GET['papel'] ?? 'letter') === 'a4') ? 'A4' : 'Letter',
                'generated' => date('d/m/Y H:i'),
                'mesEs'     => self::MES_ES,
                'h'         => fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'),
            ];
            extract($data);
            require APP_ROOT . '/app/views/reports/printable.php';
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) { ob_end_clean(); }
            http_response_code(200);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial;padding:30px">'
               . '<h2 style="color:#c0392b">Error al generar el reporte</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre></body></html>';
            return;
        }

        $html = ob_get_clean();

        // Guardar copia estática autocontenida (sin sesión) y abrir esa URL.
        // El print diferido queda a cargo del propio printable.php.
        if (!empty($html) && strpos($html, '</html>') !== false) {
            $key  = bin2hex(random_bytes(8));
            $file = sys_get_temp_dir() . '/repdf-' . preg_replace('/[^a-f0-9]/', '', md5($key)) . '.html';
            if (@file_put_contents($file, $html) !== false) {
                $base = strtok((string)($_SERVER['REQUEST_URI'] ?? '/reports/export/pdf'), '?');
                $url  = dirname($base) . '/pdf.html?' . http_build_query([
                    '_p' => 1, '_k' => $key,
                    'tab' => $_GET['tab'] ?? '', 'orientacion' => $_GET['orientacion'] ?? 'portrait',
                ]);
                $js = '<script>window.location.href=' . json_encode($url) . ';</script>';
                if (($pos = strrpos($html, '</body>')) !== false) {
                    $html = substr($html, 0, $pos) . $js . substr($html, $pos);
                } else {
                    $html .= $js;
                }
                // Limpieza básica de archivos viejos (> 1 día)
                foreach (glob(sys_get_temp_dir() . '/repdf-*.html') ?: [] as $old) {
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
