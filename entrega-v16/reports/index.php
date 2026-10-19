<?php
/**
 * Vista: Reportes — 6 pestañas (individual, dias, semanal, mensual, lapso, anual).
 * Variables: $tab, $result, $courses, $teachers, $subjects, $anio, $csrf_token
 * Exportación: CSV directo + PDF en ventana de impresión (opciones papel/orientación/gráficos).
 */
ob_start();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
use App\Controllers\ReportController;

$tabs = [
    'individual' => ['fa-user',           'Individual'],
    'dias'       => ['fa-calendar-day',   'Por días'],
    'semanal'    => ['fa-table-columns',  'Semanal'],
    'mensual'    => ['fa-calendar-days',  'Mensual'],
    'lapso'      => ['fa-hourglass-half', 'Lapso'],
    'anual'      => ['fa-calendar-check', 'Anual'],
];
$res = $result;
$q = $_GET; // params actuales para reconstruir URLs de export
$mkUrl = function ($path, $extra = []) use ($q) {
    $p = array_merge(array_diff_key($q, array_flip(['generar'])), $extra);
    return $path . '?' . http_build_query($p);
};
$z = fn($v) => ReportController::z($v);

/** Cabecera institucional pequeña para la vista on-screen. */
function repHeader($h, $title) { ?>
  <div class="rep-doc-head">
    <img src="<?= ReportController::LOGO_URL ?>" alt="Logo" class="rep-logo">
    <div>
      <div class="rep-school">COLEGIO MARISTA · Sistema para Regentes</div>
      <div class="rep-title"><?= $h($title) ?></div>
    </div>
  </div>
<?php }
?>
<div class="section">
  <div class="container is-fluid">

    <h1 class="title is-3"><i class="fas fa-chart-bar"></i>&nbsp; Reportes</h1>
    <p class="subtitle is-6 mb-4">Gestión <?= $h($anio ?? date('Y')) ?> · Asistencia escolar a colores, exportable a CSV y PDF</p>

    <!-- ===== Pestañas ===== -->
    <div class="tabs is-boxed is-fullwidth mb-5" style="overflow-x:auto">
      <ul>
        <?php foreach ($tabs as $k => [$ic, $lb]): ?>
          <li class="<?= $tab === $k ? 'is-active' : '' ?>">
            <a href="/reports?tab=<?= $k ?>"><span class="icon"><i class="fas <?= $ic ?>"></i></span><span><?= $lb ?></span></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- ============================================================ -->
    <!-- 1) INDIVIDUAL                                                -->
    <!-- ============================================================ -->
    <?php if ($tab === 'individual'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="individual">
        <div class="columns is-vcentered">
          <div class="column is-5">
            <label class="label">Estudiante</label>
            <div class="control has-icons-left" style="position:relative">
              <input class="input" id="repStudentInput" placeholder="Escribe 2+ letras…"
                     autocomplete="off" data-suggest-url="/api/suggest?type=estudiante" data-suggest-target="#repStudentBox"
                     data-suggest-select="#repStudentId"
                     value="<?= $h($res['student']['nombre'] ?? '') ?>">
              <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
              <div class="suggest-box" id="repStudentBox" hidden></div>
            </div>
            <input type="hidden" name="estudiante" id="repStudentId" value="<?= (int)($res['student']['id'] ?? ($q['estudiante'] ?? 0)) ?>">
          </div>
          <div class="column">
            <button class="button is-link" name="generar" value="1"><i class="fas fa-file-alt"></i>&nbsp; Generar reporte</button>
          </div>
        </div>
      </form>

      <?php if ($res): ?>
        <?php if (!empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div>
        <?php else: $t = $res['totals']; ?>
          <div class="box rep-doc">
            <?php repHeader($h, 'Reporte Individual — ' . $res['student']['nombre']); ?>
            <p class="mb-2"><strong>Curso:</strong> <?= $h($res['student']['curso']) ?> ·
               <strong>Periodo:</strong> <?= $h($res['from']) ?> al <?= $h($res['to']) ?>
               (<?= (int)$res['habiles'] ?> días hábiles)</p>

            <div class="table-container">
              <table class="table is-bordered is-narrow is-hoverable rep-table">
                <thead><tr><th>Fecha</th><th>Evento</th><th>Justificación</th></tr></thead>
                <tbody>
                  <?php if (!$res['detail']): ?>
                    <tr><td colspan="3" class="has-text-centered has-text-success">Sin atrasos/licencias/faltas en el periodo 🎉</td></tr>
                  <?php endif; ?>
                  <?php foreach ($res['detail'] as $d): $s = \App\Models\Event::typeToShort($d['tipo_evento']); ?>
                    <tr>
                      <td><?= $h(date('d/m/Y', strtotime($d['f']))) ?></td>
                      <td class="mark-<?= $h($s) ?>"><?= $h($s) ?> · <?= $h(\App\Models\Event::shortToTypeLabel($s)) ?></td>
                      <td><?= $h($d['justificacion'] ?: '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php foreach ($res['offgrid'] as $d): ?>
                    <tr class="is-inhabil"><td><?= $h(date('d/m/Y', strtotime($d['f']))) ?> <span class="tag is-small">día inhábil</span></td>
                        <td class="mark-<?= $h($d['t']) ?>"><?= $h($d['t']) ?></td><td><?= $h($d['j'] ?: '—') ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="rep-sumrow">
                  <td colspan="2"><strong>Totales</strong></td>
                  <td>
                    <span class="tag is-success-light-bg">P: <?= $z($t['P']) !== '' ? $z($t['P']) : $t['P'] ?></span>
                    <span class="marksum mark-A">A: <?= $z($t['A']) ?></span>
                    <span class="marksum mark-L">L: <?= $z($t['L']) ?></span>
                    <span class="marksum mark-F">F: <?= $z($t['F']) ?></span>
                  </td>
                </tr></tfoot>
              </table>
            </div>
            <p class="rep-legend">Leyenda: P = Presente (días hábiles sin incidente) · A = Atraso · L = Licencia · F = Falta.</p>

            <!-- Gráficos -->
            <div class="columns mt-4">
              <div class="column is-5"><canvas id="chIndTorta" height="220"></canvas></div>
              <div class="column is-7"><canvas id="chIndHist" height="220"></canvas></div>
            </div>
            <script>window.REP_IND = <?= json_encode([
                'torta' => ['labels' => ['Presente', 'Atrasos'], 'data' => [$t['P'], $t['A']]],
                'hist'  => ['meses' => array_column($res['monthly'], 'mes'),
                            'A' => array_column($res['monthly'], 'A'),
                            'F' => array_column($res['monthly'], 'F')],
            ], JSON_UNESCAPED_UNICODE); ?>;</script>

            <div class="buttons mt-4">
              <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['tab'=>'individual','estudiante'=>$res['student']['id'],'generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
              <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', ['tab'=>'individual','estudiante'=>$res['student']['id']])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <!-- ============================================================ -->
    <!-- 2) POR DÍAS                                                  -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'dias'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="dias">
        <div class="columns is-vcentered is-multiline">
          <div class="column is-3">
            <label class="label">Curso</label>
            <div class="select is-fullwidth"><select name="curso">
              <option value="">— Curso —</option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($q['curso'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['curso']) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2"><label class="label">Desde</label><input class="input" type="date" name="desde" value="<?= $h($q['desde'] ?? date('Y-m-d')) ?>"></div>
          <div class="column is-2"><label class="label">Hasta</label><input class="input" type="date" name="hasta" value="<?= $h($q['hasta'] ?? date('Y-m-d')) ?>"></div>
          <div class="column is-3">
            <label class="label">Profesor</label>
            <div class="select is-fullwidth"><select name="profesor">
              <option value="">Todos</option>
              <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)($q['profesor'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= $h($t['nombre']) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2">
            <label class="label">Asignatura</label>
            <div class="select is-fullwidth"><select name="asignatura">
              <option value="">Todas</option>
              <?php foreach ($subjects as $s): ?><option value="<?= $h($s) ?>" <?= ($q['asignatura'] ?? '') === $s ? 'selected' : '' ?>><?= $h($s) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-12"><button class="button is-link" name="generar" value="1"><i class="fas fa-sync"></i>&nbsp; Filtrar</button></div>
        </div>
      </form>

      <?php if ($res && !empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div><?php endif; ?>
      <?php if ($res && empty($res['error']) && $res['kind'] === 'dias'): ?>
        <div class="box rep-doc">
          <?php repHeader($h, 'Reporte por Días — Curso ' . $res['header']['curso']); ?>
          <p class="mb-2"><?= $h($res['header']['desde']) ?> → <?= $h($res['header']['hasta']) ?> ·
             Profesor: <strong><?= $h($res['header']['profesor']) ?></strong> ·
             Asignatura: <strong><?= $h($res['header']['asignatura']) ?></strong>
             <?= $res['filteredBySchedule'] ? '<span class="tag is-info is-light">columnas filtradas por horario</span>' : '<span class="tag is-warning is-light">sin horarios cargados: se muestran todos los días hábiles</span>' ?></p>

          <div class="table-container">
            <table class="table is-bordered is-narrow rep-table rep-days">
              <thead><tr><th class="rep-name">#</th><th class="rep-name">Estudiante</th>
                <?php foreach ($res['bdays'] as $d): ?><th><?= $h(date('d', strtotime($d))) ?><br><small><?= $h(['Lun','Mar','Mié','Jue','Vie'][(int)date('N', strtotime($d)) - 1]) ?></small></th><?php endforeach; ?>
                <th>P</th><th>A</th><th>L</th><th>F</th></tr></thead>
              <tbody>
                <?php foreach ($res['roster'] as $i => $s): $sid = (int)$s['id']; $t = $res['totals'][$sid]; ?>
                  <tr>
                    <td class="rep-num"><?= $i + 1 ?></td>
                    <td class="rep-name"><?= $h($s['nombre_completo']) ?></td>
                    <?php foreach ($res['bdays'] as $d): $m = $res['grid'][$sid][$d] ?? null; ?>
                      <td class="<?= $m ? 'mark-' . $h($m['t']) : 'mark-P' ?>" title="<?= $h($m['j'] ?? '') ?>"><?= $h($m['t'] ?? 'P') ?></td>
                    <?php endforeach; ?>
                    <td class="sumcell"><?= $z($t['P']) ?></td><td class="sumcell"><?= $z($t['A']) ?></td>
                    <td class="sumcell"><?= $z($t['L']) ?></td><td class="sumcell"><?= $z($t['F']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="rep-legend">Leyenda: P = Presente · A = Atraso · L = Licencia · F = Falta · celdas de suma vacías = 0.</p>

          <div class="columns mt-4 is-multiline">
            <div class="column is-6"><canvas id="chDTorta" height="200"></canvas></div>
            <div class="column is-6"><canvas id="chDBarras" height="200"></canvas></div>
            <div class="column is-6"><canvas id="chDTelara" height="260"></canvas></div>
            <div class="column is-6"><canvas id="chDLineas" height="260"></canvas></div>
          </div>
          <script>window.REP_DIAS = <?= json_encode([
              'torta'  => ['labels'=>['Presente','Con atraso'], 'data'=>[$res['sum']['P'], $res['sum']['A']]],
              'barras' => ['labels'=>['P','A','L','F'], 'data'=>[$res['sum']['P'],$res['sum']['A'],$res['sum']['L'],$res['sum']['F']]],
              'telara' => ['labels'=>array_map(fn($p)=>'#'.$p['n'], $res['perStudent']),
                           'A'=>array_column($res['perStudent'],'A'),'L'=>array_column($res['perStudent'],'L'),'F'=>array_column($res['perStudent'],'F')],
              'lineas' => ['labels'=>array_map(fn($l)=>date('d/m',strtotime($l['d'])), $res['lineSeries']),
                           'A'=>array_column($res['lineSeries'],'A'),'L'=>array_column($res['lineSeries'],'L'),'F'=>array_column($res['lineSeries'],'F')],
          ], JSON_UNESCAPED_UNICODE); ?>;</script>

          <div class="buttons mt-4">
            <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
            <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', [])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
          </div>
        </div>
      <?php endif; ?>

    <!-- ============================================================ -->
    <!-- 3) SEMANAL                                                   -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'semanal'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="semanal">
        <div class="columns is-vcentered">
          <div class="column is-3">
            <label class="label">Nivel</label>
            <div class="select is-fullwidth"><select name="nivel">
              <option value="Primaria"    <?= ($q['nivel'] ?? '') === 'Primaria' ? 'selected' : '' ?>>Primaria</option>
              <option value="Secundaria"  <?= ($q['nivel'] ?? '') === 'Secundaria' ? 'selected' : '' ?>>Secundaria</option>
            </select></div>
          </div>
          <div class="column is-3"><label class="label">Fecha dentro de la semana</label><input class="input" type="date" name="fecha" value="<?= $h($q['fecha'] ?? date('Y-m-d')) ?>"></div>
          <div class="column"><button class="button is-link" name="generar" value="1"><i class="fas fa-sync"></i>&nbsp; Generar</button></div>
        </div>
      </form>

      <?php if ($res && !empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div><?php endif; ?>
      <?php if ($res && empty($res['error']) && $res['kind'] === 'semanal'): ?>
        <div class="box rep-doc">
          <?php repHeader($h, 'Reporte Semanal — ' . $res['nivel']); ?>
          <p class="mb-3">Semana del <?= $h($res['week'][0]) ?> al <?= $h(end($res['week'])) ?> · una hoja por curso.</p>

          <?php foreach ($res['blocks'] as $b): ?>
            <div class="rep-course-block">
              <h2 class="title is-5 rep-course-title"><?= $h($b['course']['curso']) ?> <span class="tag is-light"><?= $h($b['course']['nivel'] ?? '') ?></span></h2>
              <div class="table-container">
                <table class="table is-bordered is-narrow rep-table">
                  <thead><tr><th class="rep-name">Estudiante</th>
                    <?php foreach ($res['week'] as $d): $inh = isset($b['holidays'][$d]); $w = (int)date('N', strtotime($d)); ?>
                      <th class="<?= $inh ? 'rep-inh' : '' ?>"><?= $h(['Lun','Mar','Mié','Jue','Vie'][$w - 1]) ?><br><small><?= $h(date('d/m', strtotime($d))) ?><?= $inh ? ' · feriado' : '' ?></small></th>
                    <?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php foreach ($b['roster'] as $s): $sid = (int)$s['id']; ?>
                      <tr><td class="rep-name"><?= $h($s['nombre_completo']) ?></td>
                        <?php foreach ($res['week'] as $d):
                          $inh = isset($b['holidays'][$d]);
                          $m = $b['grid'][$sid][$d] ?? null; ?>
                          <?php if ($inh): ?><td class="rep-inh"></td>
                          <?php else: $t = $m['t'] ?? 'P'; ?><td class="mark-<?= $h($t) ?>" title="<?= $h($m['j'] ?? '') ?>"><?= $h($t) ?></td><?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <p class="rep-legend">Leyenda: P = Presente · A = Atraso · L = Licencia · F = Falta · columna gris = feriado/inhábil.</p>
            </div>
          <?php endforeach; ?>

          <div class="buttons mt-4">
            <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
            <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', [])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
          </div>
        </div>
      <?php endif; ?>

    <!-- ============================================================ -->
    <!-- 4) MENSUAL                                                   -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'mensual'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="mensual">
        <div class="columns is-vcentered">
          <div class="column is-3">
            <label class="label">Curso</label>
            <div class="select is-fullwidth"><select name="curso">
              <option value="">— Curso —</option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($q['curso'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['curso']) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2"><label class="label">Mes</label>
            <div class="select is-fullwidth"><select name="mes">
              <?php foreach ([1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'] as $mn => $ml): ?>
                <option value="<?= $mn ?>" <?= (int)($q['mes'] ?? date('n')) === $mn ? 'selected' : '' ?>><?= $ml ?></option>
              <?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2"><label class="label">Año</label><input class="input" type="number" name="anio" min="2000" max="2100" value="<?= (int)($q['anio'] ?? $anio) ?>"></div>
          <div class="column"><button class="button is-link" name="generar" value="1"><i class="fas fa-sync"></i>&nbsp; Generar</button></div>
        </div>
      </form>

      <?php if ($res && !empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div><?php endif; ?>
      <?php if ($res && empty($res['error']) && $res['kind'] === 'mensual'): ?>
        <div class="box rep-doc">
          <?php repHeader($h, 'Reporte Mensual — Curso ' . $res['header']['curso'] . ' · ' . $res['header']['mes']); ?>
          <div class="table-container">
            <table class="table is-bordered is-narrow rep-table rep-days">
              <thead><tr><th class="rep-name">Estudiante</th>
                <?php foreach ($res['days'] as $d): ?><th class="<?= $d['inh'] ? 'rep-inh' : '' ?>"><small><?= $h($d['w']) ?></small><br><?= (int)$d['n'] ?></th><?php endforeach; ?>
                <th>P</th><th>A</th><th>L</th><th>F</th></tr></thead>
              <tbody>
                <?php foreach ($res['roster'] as $s): $sid = (int)$s['id']; $t = $res['totals'][$sid]; ?>
                  <tr><td class="rep-name"><?= $h($s['nombre_completo']) ?></td>
                    <?php foreach ($res['days'] as $d):
                      if ($d['inh']) { echo '<td class="rep-inh"></td>'; continue; }
                      $m = $res['grid'][$sid][$d['f']] ?? null; $tt = $m['t'] ?? 'P'; ?>
                      <td class="mark-<?= $h($tt) ?>" title="<?= $h($m['j'] ?? '') ?>"><?= $h($tt) ?></td>
                    <?php endforeach; ?>
                    <td class="sumcell"><?= $z($t['P']) ?></td><td class="sumcell"><?= $z($t['A']) ?></td>
                    <td class="sumcell"><?= $z($t['L']) ?></td><td class="sumcell"><?= $z($t['F']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="rep-legend">Leyenda: P = Presente · A = Atraso · L = Licencia · F = Falta · gris = inhábil/feriado · suma vacía = 0.</p>

          <div class="columns mt-4">
            <div class="column is-6"><canvas id="chMBarras" height="220"></canvas></div>
            <div class="column is-6"><canvas id="chMLineas" height="220"></canvas></div>
          </div>
          <script>window.REP_MES = <?= json_encode([
              'labels' => array_map(fn($x)=>date('d/m', strtotime($x['d'])), $res['chart']['perDay']),
              'A' => array_column($res['chart']['perDay'],'A'),
              'L' => array_column($res['chart']['perDay'],'L'),
              'F' => array_column($res['chart']['perDay'],'F'),
              'accA'=>array_column($res['chart']['acc'],'A'),
              'accL'=>array_column($res['chart']['acc'],'L'),
              'accF'=>array_column($res['chart']['acc'],'F'),
          ], JSON_UNESCAPED_UNICODE); ?>;</script>

          <div class="buttons mt-4">
            <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
            <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', [])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
          </div>
        </div>
      <?php endif; ?>

    <!-- ============================================================ -->
    <!-- 5) LAPSO                                                     -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'lapso'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="lapso">
        <div class="columns is-vcentered">
          <div class="column is-3">
            <label class="label">Curso</label>
            <div class="select is-fullwidth"><select name="curso">
              <option value="">— Curso —</option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($q['curso'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['curso']) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2"><label class="label">Inicio</label><input class="input" type="date" name="desde" value="<?= $h($q['desde'] ?? date('Y-m-01')) ?>"></div>
          <div class="column is-2"><label class="label">Fin</label><input class="input" type="date" name="hasta" value="<?= $h($q['hasta'] ?? date('Y-m-d')) ?>"></div>
          <div class="column"><button class="button is-link" name="generar" value="1"><i class="fas fa-sync"></i>&nbsp; Generar</button>
            <p class="help">Recomendado para trimestres → usa PDF horizontal.</p></div>
        </div>
      </form>

      <?php if ($res && !empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div><?php endif; ?>
      <?php if ($res && empty($res['error']) && $res['kind'] === 'lapso'): ?>
        <div class="box rep-doc">
          <?php repHeader($h, 'Reporte de Lapso — Curso ' . $res['header']['curso']); ?>
          <p class="mb-2"><?= $h($res['header']['desde']) ?> → <?= $h($res['header']['hasta']) ?> (<?= count($res['bdays']) ?> días hábiles)</p>
          <div class="table-container">
            <table class="table is-bordered is-narrow rep-table rep-days">
              <thead><tr><th class="rep-name">Estudiante</th>
                <?php foreach ($res['days'] as $d): ?><th><small><?= $h($d['m']) ?></small><br><?= (int)$d['n'] ?><br><small><?= $h($d['w']) ?></small></th><?php endforeach; ?>
                <th>P</th><th>A</th><th>L</th><th>F</th></tr></thead>
              <tbody>
                <?php foreach ($res['roster'] as $s): $sid = (int)$s['id']; $t = $res['totals'][$sid]; ?>
                  <tr><td class="rep-name"><?= $h($s['nombre_completo']) ?></td>
                    <?php foreach ($res['bdays'] as $d): $m = $res['grid'][$sid][$d] ?? null; $tt = $m['t'] ?? 'P'; ?>
                      <td class="mark-<?= $h($tt) ?>" title="<?= $h($m['j'] ?? '') ?>"><?= $h($tt) ?></td>
                    <?php endforeach; ?>
                    <td class="sumcell"><?= $z($t['P']) ?></td><td class="sumcell"><?= $z($t['A']) ?></td>
                    <td class="sumcell"><?= $z($t['L']) ?></td><td class="sumcell"><?= $z($t['F']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="rep-legend">Leyenda: P = Presente · A = Atraso · L = Licencia · F = Falta · suma vacía = 0.</p>

          <div class="columns mt-4">
            <div class="column is-6"><canvas id="chLBarras" height="220"></canvas></div>
            <div class="column is-6"><canvas id="chLLineas" height="220"></canvas></div>
          </div>
          <script>window.REP_LAPSO = <?= json_encode([
              'labels' => array_map(fn($x)=>date('d/m', strtotime($x['d'])), $res['chart']['perDay']),
              'A' => array_column($res['chart']['perDay'],'A'),
              'L' => array_column($res['chart']['perDay'],'L'),
              'F' => array_column($res['chart']['perDay'],'F'),
              'accA'=>array_column($res['chart']['acc'],'A'),
              'accL'=>array_column($res['chart']['acc'],'L'),
              'accF'=>array_column($res['chart']['acc'],'F'),
          ], JSON_UNESCAPED_UNICODE); ?>;</script>

          <div class="buttons mt-4">
            <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
            <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', [])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
          </div>
        </div>
      <?php endif; ?>

    <!-- ============================================================ -->
    <!-- 6) ANUAL                                                     -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'anual'): ?>
      <form method="get" action="/reports" class="box">
        <input type="hidden" name="tab" value="anual">
        <div class="columns is-vcentered">
          <div class="column is-3">
            <label class="label">Curso</label>
            <div class="select is-fullwidth"><select name="curso">
              <option value="">— Curso —</option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($q['curso'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['curso']) ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="column is-2"><label class="label">Año</label><input class="input" type="number" name="anio" min="2000" max="2100" value="<?= (int)($q['anio'] ?? $anio) ?>"></div>
          <div class="column"><button class="button is-link" name="generar" value="1"><i class="fas fa-sync"></i>&nbsp; Generar</button></div>
        </div>
      </form>

      <?php if ($res && !empty($res['error'])): ?><div class="notification is-warning"><?= $h($res['error']) ?></div><?php endif; ?>
      <?php if ($res && empty($res['error']) && $res['kind'] === 'anual'): $mesEs = $res['mesEs']; ?>
        <div class="box rep-doc">
          <?php repHeader($h, 'Reporte Anual — Curso ' . $res['header']['curso'] . ' · ' . $res['header']['anio']); ?>
          <div class="table-container">
            <table class="table is-bordered is-narrow rep-table rep-anual">
              <thead>
                <tr><th class="rep-name" rowspan="2">Estudiante</th>
                  <?php foreach ($mesEs as $m): ?><th colspan="3"><?= $h($m) ?></th><?php endforeach; ?>
                  <th colspan="3">TOTAL</th></tr>
                <tr><?php foreach ($mesEs as $m): ?><th>A</th><th>L</th><th>F</th><?php endforeach; ?><th>A</th><th>L</th><th>F</th></tr>
              </thead>
              <tbody>
                <?php foreach ($res['perStudent'] as $p): ?>
                  <tr><td class="rep-name"><?= $h($p['nombre']) ?></td>
                    <?php for ($m = 0; $m < 12; $m++): ?>
                      <td class="sumcell"><?= $z($p['mes'][$m]['A']) ?></td><td class="sumcell"><?= $z($p['mes'][$m]['L']) ?></td><td class="sumcell"><?= $z($p['mes'][$m]['F']) ?></td>
                    <?php endfor; ?>
                    <td class="sumcell rep-total"><?= $z($p['tot']['A']) ?></td><td class="sumcell rep-total"><?= $z($p['tot']['L']) ?></td><td class="sumcell rep-total"><?= $z($p['tot']['F']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr class="rep-sumrow"><td class="rep-name"><strong>Curso</strong></td>
                <?php for ($m = 0; $m < 12; $m++): ?>
                  <td><?= $z($res['grand']['A'][$m]) ?></td><td><?= $z($res['grand']['L'][$m]) ?></td><td><?= $z($res['grand']['F'][$m]) ?></td>
                <?php endfor; ?>
                <td colspan="3"></td></tr></tfoot>
            </table>
          </div>
          <p class="rep-legend">Leyenda: A = Atraso · L = Licencia · F = Falta · celda vacía = 0 eventos ese mes.</p>

          <div class="columns mt-4">
            <div class="column is-7"><canvas id="chABarras" height="240"></canvas></div>
            <div class="column is-5"><canvas id="chATorta" height="240"></canvas></div>
          </div>
          <script>window.REP_ANUAL = <?= json_encode([
              'labels' => array_values($mesEs),
              'A' => array_values($res['grand']['A']), 'L' => array_values($res['grand']['L']), 'F' => array_values($res['grand']['F']),
              'torta' => ['labels'=>['Atrasos','Licencias','Faltas'],
                          'data'=>[array_sum($res['grand']['A']), array_sum($res['grand']['L']), array_sum($res['grand']['F'])]],
          ], JSON_UNESCAPED_UNICODE); ?>;</script>

          <div class="buttons mt-4">
            <a class="button is-small is-success is-light" target="_blank" href="<?= $h($mkUrl('/reports/export/csv', ['generar'=>1])) ?>"><i class="fas fa-file-csv"></i>&nbsp; CSV</a>
            <button class="button is-small is-danger is-light rep-pdf" data-export="<?= $h($mkUrl('/reports/export/pdf', [])) ?>"><i class="fas fa-file-pdf"></i>&nbsp; PDF</button>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* Paleta institucional marista: azul profundo + acentos de marca PALF */
const REP_C = { P:'#2ECC71', A:'#F39C12', L:'#3498DB', F:'#E74C3C', NAVY:'#1B3A6B' };
function repPie(id, labels, data, colors, title) {
  const el = document.getElementById(id); if (!el || !window.Chart) return;
  new Chart(el, { type:'pie', data:{ labels, datasets:[{ data, backgroundColor:colors, borderWidth:1 }] },
    options:{ plugins:{ title: repTitle(title), legend:{ position:'bottom' } } } });
}
function repBars(id, labels, series, stacked=false, title) {
  const el = document.getElementById(id); if (!el || !window.Chart) return;
  new Chart(el, { type:'bar', data:{ labels, datasets:series.map(s=>({ label:s.label, data:s.data, backgroundColor:s.color, stack: stacked?'a':undefined })) },
    options:{ responsive:true, scales:{ x:{ stacked:stacked }, y:{ stacked:stacked, beginAtZero:true } }, plugins:{ title: repTitle(title), legend:{ position:'bottom' } } } });
}
function repLine(id, labels, series, title) {
  const el = document.getElementById(id); if (!el || !window.Chart) return;
  new Chart(el, { type:'line', data:{ labels, datasets:series.map(s=>({ label:s.label, data:s.data, borderColor:s.color, tension:.2 })) },
    options:{ plugins:{ title: repTitle(title), legend:{ position:'bottom' } } } });
}
/* Título en todos los estadígrafos y % dentro de las tortas */
const repTitle = (t) => ({ display: !!t, text: t || '', color: '#1B3A6B', font: { size: 14, weight: 'bold' } });
if (window.Chart) Chart.register({
  id: 'repPctLabels',
  afterDatasetsDraw(chart) {
    const type = (chart.config && chart.config.type) || '';
    if (!['pie', 'doughnut'].includes(type)) return;
    const ds = chart.data.datasets[0];
    const total = (ds.data || []).reduce((a, b) => a + (+b || 0), 0);
    if (!total) return;
    const ctx = chart.ctx;
    ctx.save();
    ctx.font = 'bold 12px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    chart.getDatasetMeta(0).data.forEach((arc, i) => {
      const v = +ds.data[i] || 0;
      if (!v) return;
      const p = arc.tooltipPosition();
      ctx.fillStyle = '#1a1a1a';
      ctx.fillText(Math.round(v * 100 / total) + '%', p.x, p.y);
    });
    ctx.restore();
  }
});
document.addEventListener('DOMContentLoaded', () => {
  const I = window.REP_IND;
  if (I) {
    repPie('chIndTorta', I.torta.labels, I.torta.data, [REP_C.P, REP_C.A], 'Presente vs Atrasos (%)');
    repBars('chIndHist', I.hist.meses, [{label:'Atrasos', data:I.hist.A, color:REP_C.A},{label:'Faltas', data:I.hist.F, color:REP_C.F}], false, 'Atrasos y Faltas mensuales');
  }
  const D = window.REP_DIAS;
  if (D) {
    repPie('chDTorta', D.torta.labels, D.torta.data, [REP_C.P, REP_C.A], 'Presente vs Atraso (%)');
    // barras P/A/L/F con color por barra
    const bEl = document.getElementById('chDBarras');
    if (bEl && window.Chart) new Chart(bEl, { type:'bar', data:{ labels:D.barras.labels,
      datasets:[{ label:'Eventos', data:D.barras.data, backgroundColor:[REP_C.P,REP_C.A,REP_C.L,REP_C.F] }] },
      options:{ plugins:{ title: repTitle('Totales P / A / L / F'), legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } } });
    const tEl = document.getElementById('chDTelara');
    if (tEl && window.Chart) new Chart(tEl, { type:'radar', data:{ labels:D.telara.labels, datasets:[
        {label:'Atrasos',data:D.telara.A,borderColor:REP_C.A,backgroundColor:'rgba(243,156,18,.2)'},
        {label:'Licencias',data:D.telara.L,borderColor:REP_C.L,backgroundColor:'rgba(52,152,219,.2)'},
        {label:'Faltas',data:D.telara.F,borderColor:REP_C.F,backgroundColor:'rgba(231,76,60,.2)'}]},
        options:{ plugins:{ title: repTitle('Incidencia por estudiante (# lista)'), legend:{ position:'bottom' } } } });
    repLine('chDLineas', D.lineas.labels, [
        {label:'A acum.',data:D.lineas.A,color:REP_C.A},
        {label:'L acum.',data:D.lineas.L,color:REP_C.L},
        {label:'F acum.',data:D.lineas.F,color:REP_C.F}], 'Acumulado A / L / F');
  }
  const mkDaily = (pre, S, t1, t2) => { if (!S) return;
    repBars(pre+'Barras', S.labels, [{label:'A',data:S.A,color:REP_C.A},{label:'L',data:S.L,color:REP_C.L},{label:'F',data:S.F,color:REP_C.F}], true, t1);
    repLine(pre+'Lineas', S.labels, [
      {label:'A acum.',data:S.accA,color:REP_C.A},
      {label:'L acum.',data:S.accL,color:REP_C.L},
      {label:'F acum.',data:S.accF,color:REP_C.F}], t2);
  };
  mkDaily('chM', window.REP_MES, 'Eventos diarios A/L/F del mes', 'Acumulado mensual A/L/F');
  mkDaily('chL', window.REP_LAPSO, 'Eventos diarios A/L/F del lapso', 'Acumulado del lapso A/L/F');
  const A = window.REP_ANUAL;
  if (A) {
    repBars('chABarras', A.labels, [{label:'A',data:A.A,color:REP_C.A},{label:'L',data:A.L,color:REP_C.L},{label:'F',data:A.F,color:REP_C.F}], false, 'A/L/F mensuales del curso');
    repPie('chATorta', A.torta.labels, A.torta.data, [REP_C.A, REP_C.L, REP_C.F], 'Distribución ALF de la gestión (%)');
  }

  /* Diálogo de opciones PDF -> abre ventana de impresión */
  document.querySelectorAll('.rep-pdf').forEach(btn => btn.addEventListener('click', () => {
    const base = btn.dataset.export;
    const dlg = document.createElement('div');
    dlg.className = 'modal is-active';
    dlg.innerHTML = `<div class="modal-background"></div><div class="modal-card" style="max-width:430px">
      <header class="modal-card-head"><p class="modal-title title is-5">Opciones de PDF</p></header>
      <section class="modal-card-body">
        <div class="field"><label class="label">Orientación</label><div class="control">
          <label class="radio"><input type="radio" name="orient" value="portrait" checked> Vertical</label>
          <label class="radio"><input type="radio" name="orient" value="landscape"> Horizontal</label></div></div>
        <div class="field"><label class="label">Papel</label><div class="control">
          <label class="radio"><input type="radio" name="papel" value="letter" checked> Carta (Letter)</label>
          <label class="radio"><input type="radio" name="papel" value="a4"> A4</label></div></div>
        <div class="field"><div class="control"><label class="checkbox"><input type="checkbox" name="charts"> Incluir estadígrafos</label></div></div>
      </section>
      <footer class="modal-card-foot"><a class="button is-link" id="pdfGo">Generar / Imprimir</a><a class="button" id="pdfNo">Cancelar</a></footer>
    </div>`;
    document.body.appendChild(dlg);
    dlg.querySelector('.modal-background').onclick = () => dlg.remove();
    dlg.querySelector('#pdfNo').onclick = () => dlg.remove();
    dlg.querySelector('#pdfGo').onclick = () => {
      const o = dlg.querySelector('[name=orient]:checked').value;
      const p = dlg.querySelector('[name=papel]:checked').value;
      const c = dlg.querySelector('[name=charts]').checked ? '1' : '0';
      window.open(base + '&orientacion=' + o + '&papel=' + p + '&charts=' + c, '_blank');
      dlg.remove();
    };
  }));
});
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
