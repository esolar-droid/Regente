<?php
/**
 * Plantilla standalone de impresión (PDF) para los 6 reportes.
 * Variables: $result, $title, $logoUrl, $charts, $orient, $paper, $generated, $mesEs, $h
 * Renderiza Chart.js a PNG y lo incrusta; auto-invoca window.print().
 */
$kind = $result['kind'] ?? '';
$z = fn($v) => \App\Controllers\ReportController::z($v);

/**
 * Rejilla reutilizable para "por días" / mensual / lapso.
 * Definida ANTES de su uso (las definiciones de función dentro de bloques
 * condicionales no se hoistean en PHP -> antes producía página en blanco).
 */
if (!function_exists('repGrid')) {
function repGrid(array $roster, array $cols, array $grid, array $totals, callable $h, callable $z, bool $twoLineHeader, bool $skipP = false): string {
    ob_start(); ?>
    <div style="overflow-x:auto">
    <table class="grid">
      <thead><tr><th class="name" style="text-align:left">#</th><th class="name" style="text-align:left">Estudiante</th>
        <?php foreach ($cols as $col):
            $iso = is_array($col) ? $col['f'] : $col;
            $inh = is_array($col) && !empty($col['inh']);
            $w = (int)date('N', strtotime($iso)); ?>
          <th class="<?= $inh ? 'inh' : '' ?>">
            <?php if ($twoLineHeader && is_array($col) && isset($col['m'])): ?><small><?= $h($col['m']) ?></small><br><?php endif; ?>
            <?= $h(is_array($col) && isset($col['n']) ? $col['n'] : date('d', strtotime($iso))) ?><br>
            <small><?= $h(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][$w - 1]) ?></small>
          </th>
        <?php endforeach; ?>
        <?php if (!$skipP): ?><th>P</th><?php endif; ?><th>A</th><th>L</th><th>F</th></tr></thead>
      <tbody>
        <?php foreach ($roster as $i => $s): $sid = (int)$s['id']; $t = $totals[$sid] ?? ['P'=>0,'A'=>0,'L'=>0,'F'=>0]; ?>
          <tr><td class="name"><?= $i + 1 ?></td><td class="name"><?= $h($s['nombre_completo']) ?></td>
            <?php foreach ($cols as $col):
                $iso = is_array($col) ? $col['f'] : $col;
                if (is_array($col) && !empty($col['inh'])) { echo '<td class="inh"></td>'; continue; }
                $m = $grid[$sid][$iso] ?? null;
                if ($m) { ?>
                  <td class="mark-<?= $h($m['t']) ?>"><?= $h($m['t']) ?></td>
                <?php } elseif ($skipP) { ?>
                  <td class="none">—</td>
                <?php } else { ?>
                  <td class="mark-P">P</td>
                <?php }
            endforeach; ?>
            <?php if (!$skipP): ?><td class="sumcell"><?= $h($z($t['P'])) ?></td><?php endif; ?>
            <td class="sumcell"><?= $h($z($t['A'])) ?></td>
            <td class="sumcell"><?= $h($z($t['L'])) ?></td><td class="sumcell"><?= $h($z($t['F'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php return ob_get_clean();
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= $h($title) ?></title>
<style>
  :root { --navy:#1B3A6B; --p:#2ECC71; --a:#F39C12; --l:#3498DB; --f:#E74C3C; }
  @page { size: <?= $h($paper) ?> <?= $h($orient) ?>; margin: 0.5cm; }
  * { box-sizing: border-box; }
  html,body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; font-size: 10px; color:#222; }
  .sheet { page-break-after: always; }
  .sheet:last-child { page-break-after: auto; }
  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:3px solid var(--navy); padding-bottom:6px; margin-bottom:8px; }
  .doc-head img { height:44px; max-width:220px; object-fit:contain; }
  .doc-school { color:var(--navy); font-weight:bold; font-size:12px; letter-spacing:.5px; text-transform:uppercase; }
  .doc-title { font-size:15px; font-weight:bold; margin-top:2px; }
  .doc-meta { color:#555; font-size:9.5px; }
  table.grid { width:100%; border-collapse:collapse; }
  table.grid th, table.grid td { border:1px solid #9aa7b8; padding:2px 3px; text-align:center; }
  table.grid th { background:var(--navy); color:#fff; line-height:1.15; }
  table.grid th, table.grid td,
  table.grid th small, table.grid td small,
  table.grid .name, table.grid .sumcell { font-size:9pt !important; } /* v16.3: fuente uniforme 9pt en todo el cuerpo de tabla */
  table.grid td.name { text-align:left; white-space:nowrap; max-width:180px; overflow:hidden; text-overflow:ellipsis; }
  table.grid tr:nth-child(even) td { background:#f4f7fb; }
  .mark-P { background:rgba(46,204,113,.30); }
  .mark-A { background:rgba(243,156,18,.45); font-weight:bold; }
  .mark-L { background:rgba(52,152,219,.40); font-weight:bold; }
  .mark-F { background:rgba(231,76,60,.45); font-weight:bold; color:#7a0f0f; }
  .none { color:#94a3b8; } /* v17.1: celda sin eventos cuando se filtra por tipo */
  .inh { background:#d9dde3 !important; }
  .sumcell { font-weight:bold; background:#eef2f7; }
  .legend { margin-top:6px; font-size:9px; color:#333; border-top:1px dashed #9aa7b8; padding-top:4px; }
  .legend b.P{color:#1e8449} .legend b.A{color:#b9770e} .legend b.L{color:#1f618d} .legend b.F{color:#c0392b}
  .course-block h2 { color:var(--navy); font-size:12px; margin:4px 0; }
  .charts { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-top:10px; }
  .charts canvas,.charts img { max-width:45%; }
  .noprint-note { position:fixed; bottom:6px; right:8px; font-size:9px; color:#888; }
  tfoot td { background:#eef2f7; font-weight:bold; }
  @media print { .noprint-note { display:none; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="doc-head">
  <img src="<?= $h($logoUrl) ?>" alt="Logo institucional">
  <div>
    <div class="doc-school">Colegio Marista · Sistema para Regentes</div>
    <div class="doc-title"><?= $h($title) ?></div>
    <div class="doc-meta">Generado: <?= $h($generated) ?> · Gestión <?= $h(date('Y')) ?> · Papel <?= $h($paper) ?> (<?= $orient === 'landscape' ? 'horizontal' : 'vertical' ?>)</div>
  </div>
</div>

<?php if ($kind === 'individual'): $t = $result['totals']; ?>
  <div class="sheet">
    <p class="doc-meta"><strong>Estudiante:</strong> <?= $h($result['student']['nombre']) ?> ·
       <strong>Curso:</strong> <?= $h($result['student']['curso']) ?> ·
       <strong>Periodo:</strong> <?= $h(date('d/m/Y', strtotime($result['from']))) ?> al <?= $h(date('d/m/Y', strtotime($result['to']))) ?>
       (<?= (int)$result['habiles'] ?> días hábiles)</p>
    <table class="grid">
      <thead><tr><th style="width:15%">Fecha</th><th style="width:15%">Evento</th><th>Justificación</th></tr></thead>
      <tbody>
        <?php if (!$result['detail'] && !$result['offgrid']): ?>
          <tr><td colspan="3">Sin atrasos/licencias/faltas en el periodo.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['detail'] as $d): $s = \App\Models\Event::typeToShort($d['tipo_evento']); ?>
          <tr><td><?= $h(date('d/m/Y', strtotime($d['f']))) ?></td>
              <td class="mark-<?= $h($s) ?>"><?= $h($s) ?> · <?= $h(\App\Models\Event::shortToTypeLabel($s)) ?></td>
              <td style="text-align:left"><?= $h($d['justificacion'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($result['offgrid'] as $d): ?>
          <tr><td><?= $h(date('d/m/Y', strtotime($d['f']))) ?> (inhábil)</td>
              <td class="mark-<?= $h($d['t']) ?>"><?= $h($d['t']) ?></td>
              <td style="text-align:left"><?= $h($d['j'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="2">TOTALES</td>
        <td>P: <?= $h($z($t['P'])) !== '' ? $h($z($t['P'])) : $t['P'] ?> &nbsp; A: <?= $h($z($t['A'])) ?: 0 ?> &nbsp; L: <?= $h($z($t['L'])) ?: 0 ?> &nbsp; F: <?= $h($z($t['F'])) ?: 0 ?></td></tr></tfoot>
    </table>
    <div class="legend"><b>P</b> = Presente (días hábiles sin incidente) · <b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta.</div>
    <?php if ($charts): ?>
      <div class="charts" id="chartArea"></div>
      <script>window.REP_CHARTS = [{type:'pie', labels:['Presente','Atrasos'], data:[<?= (int)$t['P'] ?>,<?= (int)$t['A'] ?>], colors:['#2ECC71','#F39C12'], title:'Asistencia P vs A'},
        {type:'bar', dual:true, labels:<?= json_encode(array_column($result['monthly'],'mes')) ?>, series:[
          {label:'Atrasos', data:<?= json_encode(array_column($result['monthly'],'A')) ?>, color:'#F39C12'},
          {label:'Faltas', data:<?= json_encode(array_column($result['monthly'],'F')) ?>, color:'#E74C3C'}], title:'Mensual A vs F'}];</script>
    <?php endif; ?>
  </div>

<?php elseif ($kind === 'dias'): $skipP = ($result['header']['tipo'] ?? '') !== ''; ?>
  <div class="sheet">
    <p class="doc-meta"><strong>Curso:</strong> <?= $h($result['header']['curso']) ?> ·
       <strong>Rango:</strong> <?= $h($result['header']['desde']) ?> → <?= $h($result['header']['hasta']) ?> ·
       <strong>Profesor:</strong> <?= $h($result['header']['profesor']) ?> ·
       <strong>Asignatura:</strong> <?= $h($result['header']['asignatura']) ?> ·
       <strong>Tipo de evento:</strong> <?= $skipP ? $h(['A'=>'Atrasos','L'=>'Licencias','F'=>'Faltas'][$result['header']['tipo']]) : 'Todos' ?></p>
    <?= repGrid($result['roster'], array_map(fn($d)=>['f'=>$d], $result['bdays']), $result['grid'], $result['totals'], $h, $z, false, $skipP) ?>
    <div class="legend"><?= $skipP
        ? '<b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta · — = sin eventos ese día · suma vacía = 0.'
        : '<b>P</b> = Presente · <b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta · suma vacía = 0.' ?></div>
    <?php if ($charts): ?>
      <div class="charts" id="chartArea"></div>
      <script>window.REP_CHARTS = [
        {type:'pie', labels:['Presente','Con atraso'], data:[<?= (int)$result['sum']['P'] ?>,<?= (int)$result['sum']['A'] ?>], colors:['#2ECC71','#F39C12'], title:'P vs Atraso'},
        <?php if ($skipP): $tl = ['A'=>'Atrasos','L'=>'Licencias','F'=>'Faltas'][$result['header']['tipo']]; ?>
        {type:'bar', labels:['<?= $h($tl) ?>'], data:[<?= (int)$result['sum'][$result['header']['tipo']] ?>], colors:['#F39C12'], title:'Total <?= $h($tl) ?>'},
        <?php else: ?>
        {type:'bar', labels:['P','A','L','F'], data:[<?= (int)$result['sum']['P'] ?>,<?= (int)$result['sum']['A'] ?>,<?= (int)$result['sum']['L'] ?>,<?= (int)$result['sum']['F'] ?>], colors:['#2ECC71','#F39C12','#3498DB','#E74C3C'], title:'Totales PALF'},
        <?php endif; ?>
        {type:'radar', labels:<?= json_encode(array_map(fn($p)=>'#'.$p['n'], $result['perStudent'])) ?>, series:[
          {label:'A', data:<?= json_encode(array_column($result['perStudent'],'A')) ?>, color:'#F39C12'},
          {label:'L', data:<?= json_encode(array_column($result['perStudent'],'L')) ?>, color:'#3498DB'},
          {label:'F', data:<?= json_encode(array_column($result['perStudent'],'F')) ?>, color:'#E74C3C'}], title:'Incidencia por estudiante (#lista)'},
        {type:'line', labels:<?= json_encode(array_map(fn($l)=>date('d/m',strtotime($l['d'])), $result['lineSeries'])) ?>, series:[
          {label:'A acum.', data:<?= json_encode(array_column($result['lineSeries'],'A')) ?>, color:'#F39C12'},
          {label:'L acum.', data:<?= json_encode(array_column($result['lineSeries'],'L')) ?>, color:'#3498DB'},
          {label:'F acum.', data:<?= json_encode(array_column($result['lineSeries'],'F')) ?>, color:'#E74C3C'}], title:'Acumulado A/L/F'}];</script>
    <?php endif; ?>
  </div>

<?php elseif ($kind === 'semanal'): ?>
  <?php foreach ($result['blocks'] as $bi => $b): ?>
    <div class="sheet">
      <div class="course-block"><h2>Curso: <?= $h($b['course']['curso']) ?> — <?= $h($result['nivel']) ?> · semana del <?= $h(date('d/m', strtotime($result['week'][0]))) ?></h2></div>
      <table class="grid">
        <thead><tr><th class="name" style="text-align:left">Estudiante</th>
          <?php foreach ($result['week'] as $d): $inh = isset($b['holidays'][$d]); $w = (int)date('N', strtotime($d)); ?>
            <th class="<?= $inh ? 'inh' : '' ?>"><?= $h(['Lun','Mar','Mié','Jue','Vie'][$w - 1]) ?><br><?= $h(date('d/m', strtotime($d))) ?><?= $inh ? '<br>feriado' : '' ?></th>
          <?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($b['roster'] as $s): $sid = (int)$s['id']; ?>
            <tr><td class="name"><?= $h($s['nombre_completo']) ?></td>
              <?php foreach ($result['week'] as $d):
                if (isset($b['holidays'][$d])) { echo '<td class="inh"></td>'; continue; }
                $m = $b['grid'][$sid][$d] ?? null; $tt = $m['t'] ?? 'P'; ?>
                <td class="mark-<?= $h($tt) ?>"><?= $h($tt) ?></td>
              <?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="legend"><b>P</b> = Presente · <b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta · gris = inhábil/feriado.</div>
    </div>
  <?php endforeach; ?>

<?php elseif ($kind === 'mensual' || $kind === 'lapso'): ?>
  <div class="sheet">
    <p class="doc-meta"><strong>Curso:</strong> <?= $h($result['header']['curso']) ?> ·
      <?php if ($kind === 'mensual'): ?><strong>Mes:</strong> <?= $h($result['header']['mes']) ?>
      <?php else: ?><strong>Lapso:</strong> <?= $h($result['header']['desde']) ?> → <?= $h($result['header']['hasta']) ?><?php endif; ?></p>
    <?= repGrid($result['roster'], $result['days'], $result['grid'], $result['totals'], $h, $z, true) ?>
    <div class="legend"><b>P</b> = Presente · <b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta · gris = inhábil/feriado · suma vacía = 0.</div>
    <?php if ($charts): ?>
      <div class="charts" id="chartArea"></div>
      <script>window.REP_CHARTS = [
        {type:'bar', stacked:true, labels:<?= json_encode(array_map(fn($x)=>date('d/m',strtotime($x['d'])), $result['chart']['perDay'])) ?>, series:[
          {label:'A', data:<?= json_encode(array_column($result['chart']['perDay'],'A')) ?>, color:'#F39C12'},
          {label:'L', data:<?= json_encode(array_column($result['chart']['perDay'],'L')) ?>, color:'#3498DB'},
          {label:'F', data:<?= json_encode(array_column($result['chart']['perDay'],'F')) ?>, color:'#E74C3C'}], title:'Eventos diarios A/L/F'},
        {type:'line', labels:<?= json_encode(array_map(fn($x)=>date('d/m',strtotime($x['d'])), $result['chart']['acc'])) ?>, series:[
          {label:'A acum.', data:<?= json_encode(array_column($result['chart']['acc'],'A')) ?>, color:'#F39C12'},
          {label:'L acum.', data:<?= json_encode(array_column($result['chart']['acc'],'L')) ?>, color:'#3498DB'},
          {label:'F acum.', data:<?= json_encode(array_column($result['chart']['acc'],'F')) ?>, color:'#E74C3C'}], title:'Acumulado A/L/F'}];</script>
    <?php endif; ?>
  </div>

<?php elseif ($kind === 'anual'): ?>
  <div class="sheet">
    <p class="doc-meta"><strong>Curso:</strong> <?= $h($result['header']['curso']) ?> · <strong>Gestión:</strong> <?= (int)$result['header']['anio'] ?></p>
    <table class="grid">
      <thead>
        <tr><th rowspan="2" class="name" style="text-align:left">Estudiante</th>
          <?php foreach ($mesEs as $m): ?><th colspan="3"><?= $h($m) ?></th><?php endforeach; ?><th colspan="3">TOTAL</th></tr>
        <tr><?php for ($i = 0; $i < 13; $i): ?><th>A</th><th>L</th><th>F</th><?php endfor; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($result['perStudent'] as $p): ?>
          <tr><td class="name"><?= $h($p['nombre']) ?></td>
            <?php for ($m = 0; $m < 12; $m++): ?>
              <td class="sumcell"><?= $h($z($p['mes'][$m]['A'])) ?></td><td class="sumcell"><?= $h($z($p['mes'][$m]['L'])) ?></td><td class="sumcell"><?= $h($z($p['mes'][$m]['F'])) ?></td>
            <?php endfor; ?>
            <td class="sumcell"><?= $h($z($p['tot']['A'])) ?></td><td class="sumcell"><?= $h($z($p['tot']['L'])) ?></td><td class="sumcell"><?= $h($z($p['tot']['F'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td class="name">CURSO</td>
        <?php for ($m = 0; $m < 12; $m++): ?>
          <td><?= $h($z($result['grand']['A'][$m])) ?></td><td><?= $h($z($result['grand']['L'][$m])) ?></td><td><?= $h($z($result['grand']['F'][$m])) ?></td>
        <?php endfor; ?><td colspan="3"></td></tr></tfoot>
    </table>
    <div class="legend"><b>A</b> = Atraso · <b>L</b> = Licencia · <b>F</b> = Falta · celda vacía = 0 eventos ese mes.</div>
    <?php if ($charts): ?>
      <div class="charts" id="chartArea"></div>
      <script>window.REP_CHARTS = [
        {type:'bar', labels:<?= json_encode(array_values($mesEs)) ?>, series:[
          {label:'A', data:<?= json_encode(array_values($result['grand']['A'])) ?>, color:'#F39C12'},
          {label:'L', data:<?= json_encode(array_values($result['grand']['L'])) ?>, color:'#3498DB'},
          {label:'F', data:<?= json_encode(array_values($result['grand']['F'])) ?>, color:'#E74C3C'}], title:'Mensual curso'},
        {type:'pie', labels:['Atrasos','Licencias','Faltas'], data:[<?= array_sum($result['grand']['A']) ?>,<?= array_sum($result['grand']['L']) ?>,<?= array_sum($result['grand']['F']) ?>], colors:['#F39C12','#3498DB','#E74C3C'], title:'ALF total gestión'}];</script>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="noprint-note">Se abrirá el diálogo de impresión — elige «Guardar como PDF», papel <?= $h($paper) ?>, márgenes «Predeterminados».</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
/* dibuja REP_CHARTS en canvases dentro de #chartArea */
(function () {
  const area = document.getElementById('chartArea');
  if (!area || !window.Chart || !window.REP_CHARTS) return;
  if (window.ChartDataLabels) Chart.register(window.ChartDataLabels); // % en tortas
  window.REP_CHARTS.forEach(cfg => {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'width:46%;min-width:250px;text-align:center';
    const cap = document.createElement('div');
    cap.textContent = cfg.title || '';
    cap.style.cssText = 'font-weight:bold;color:#1B3A6B;font-size:11px;margin-bottom:2px';
    const cv = document.createElement('canvas');
    wrap.appendChild(cap); wrap.appendChild(cv); area.appendChild(wrap);
    let ds;
    if (cfg.type === 'pie') ds = [{ data: cfg.data, backgroundColor: cfg.colors, borderWidth: 1 }];
    else ds = (cfg.series || []).map(s => ({ label: s.label, data: s.data,
        backgroundColor: cfg.type === 'line' ? 'transparent' : s.color, borderColor: s.color, tension: .2 }));
    const isPie = cfg.type === 'pie';
    new Chart(cv, { type: isPie ? 'doughnut' : cfg.type,
      data: { labels: cfg.labels, datasets: ds },
      options: { plugins: {
          title: { display: !!cfg.title, text: cfg.title || '', color: '#1B3A6B', font: { size: 13, weight: 'bold' } },
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: (c) => c.label + ': ' + c.parsed + (isPie ? ' (' + Math.round(c.parsed * 100 / (ds[0].data.reduce((a, b) => a + (+b || 0), 0) || 1)) + '%)' : '') } },
          datalabels: { display: isPie, color: '#1a1a1a', font: { weight: 'bold', size: 12 },
                        formatter: (v) => v > 0 ? Math.round(v * 100 / (ds[0].data.reduce((a, b) => a + (+b || 0), 0) || 1)) + '%' : '' }
        },
        scales: isPie || cfg.type === 'radar' ? {} :
          { x: { stacked: !!cfg.stacked }, y: { stacked: !!cfg.stacked, beginAtZero: true } } } });
  });
})();
setTimeout(function () {
  var done = false;
  function go() { if (done) return; done = true; try { window.print(); } catch (e) {} }
  // El navegador congela los timers de pestañas en segundo plano: al activarse
  // la pestaña (visibilitychange/focus) imprimimos de inmediato. Si ya estaba
  // visible, el timer corto basta. Así la hoja SIEMPRE tiene contenido impreso.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) setTimeout(go, 150); });
  window.addEventListener('focus', function () { setTimeout(go, 150); });
  setTimeout(go, 1200);
}, 0);
</script>
</body>
</html>
