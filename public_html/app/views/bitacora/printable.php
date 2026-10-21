<?php
/**
 * v17 · Plantilla standalone imprimible de la Bitácora del profesorado.
 *
 * Mismo patrón probado en reports/printable.php (v16.3): HTML estático
 * autocontenido (sin sesión, sin Bulma) que el navegador convierte a PDF
 * vía window.print(). El controlador la renderiza con output buffering y
 * la cachea; esta hoja se sirve después desde /bitacora/export/pdf.html.
 *
 * Variables esperadas: $rows, $f, $logoUrl, $generated, $h, $tipos
 */
$rows = $rows ?? [];
$f    = $f ?? ['q' => '', 'desde' => '', 'hasta' => '', 'tipo' => ''];
$tipos = $tipos ?? \App\Models\Bitacora::TIPOS;

// Filtro defensivo por si la hoja cacheada se manipula (no hay sesión aquí).
if (!empty($f['desde'])) {
    $rows = array_values(array_filter($rows, function ($r) use ($f) {
        return substr((string)$r['fecha'], 0, 10) >= $f['desde'];
    }));
}
if (!empty($f['hasta'])) {
    $rows = array_values(array_filter($rows, function ($r) use ($f) {
        return substr((string)$r['fecha'], 0, 10) <= $f['hasta'];
    }));
}
if (!empty($f['tipo'])) {
    $rows = array_values(array_filter($rows, function ($r) use ($f) {
        return (string)$r['tipo'] === $f['tipo'];
    }));
}

$periodo = trim(($f['desde'] !== '' ? 'desde ' . date('d/m/Y', strtotime($f['desde'])) : '')
              . ($f['hasta']  !== '' ? ' hasta ' . date('d/m/Y', strtotime($f['hasta']))  : ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Bitácora del profesorado</title>
<style>
  :root { --navy:#1B3A6B; }
  @page { size: A4 portrait; margin: 0.7cm; }
  * { box-sizing: border-box; }
  html,body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; font-size: 10px; color:#222; }
  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:3px solid var(--navy); padding-bottom:6px; margin-bottom:8px; }
  .doc-head img { height:44px; max-width:220px; object-fit:contain; }
  .doc-school { color:var(--navy); font-weight:bold; font-size:12px; letter-spacing:.5px; text-transform:uppercase; }
  .doc-title { font-size:15px; font-weight:bold; margin-top:2px; }
  .doc-meta { color:#555; font-size:9.5px; }
  table.grid { width:100%; border-collapse:collapse; }
  table.grid th, table.grid td { border:1px solid #9aa7b8; padding:3px 5px; text-align:left; vertical-align:top; }
  table.grid th, table.grid td { font-size:9pt !important; } /* fuente uniforme 9pt (criterio v16.3) */
  table.grid th { background:var(--navy); color:#fff; }
  table.grid tr:nth-child(even) td { background:#f4f7fb; }
  table.grid td.c, table.grid th.c { text-align:center; white-space:nowrap; }
  .tag { display:inline-block; border:1px solid #9aa7b8; border-radius:3px; padding:0 4px; font-size:8.5px; background:#eef3f9; color:#1B3A6B; }
  .empty { padding:20px; text-align:center; color:#777; border:1px dashed #9aa7b8; }
  .foot { margin-top:8px; border-top:1px dashed #9aa7b8; padding-top:4px; font-size:9px; color:#666; }
  .noprint-note { position:fixed; bottom:6px; right:8px; font-size:9px; color:#888; }
  @media print { .noprint-note { display:none; } }
</style>
</head>
<body>

<div class="doc-head">
  <img src="<?= $h($logoUrl) ?>" alt="Logo">
  <div>
    <div class="doc-school">Colegio Marista · Sistema para Regentes</div>
    <div class="doc-title">Bitácora del profesorado</div>
    <div class="doc-meta">
      Generado: <?= $h($generated) ?> · Gestión <?= $h(date('Y')) ?> ·
      <?= $h($periodo !== '' ? $periodo : 'Todo el período') ?><?= $buscado !== '' ? ' · ' . $h($buscado) : '' ?><?= $f['tipo'] !== '' ? ' · Tipo: ' . $h($tipos[$f['tipo']] ?? $f['tipo']) : '' ?>
      · <?= count($rows) ?> registro(s)
    </div>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="empty">No hay registros de bitácora que coincidan con los filtros aplicados.</div>
<?php else: ?>
<table class="grid">
  <thead>
    <tr>
      <th class="c" style="width:1%">#</th>
      <th class="c" style="width:9%">Fecha</th>
      <th style="width:20%">Profesor</th>
      <th class="c" style="width:11%">Tipo</th>
      <th>Título y detalle</th>
      <th class="c" style="width:14%">Registra</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td class="c"><?= $i + 1 ?></td>
      <td class="c"><?= $h(substr((string)$r['fecha'], 0, 10)) ?></td>
      <td><?= $h(trim((string)$r['profesor_nombre']) ?: '—') ?></td>
      <td class="c"><span class="tag"><?= $h($tipos[$r['tipo']] ?? ucfirst((string)$r['tipo'])) ?></span></td>
      <td>
        <strong><?= $h($r['titulo']) ?></strong>
        <?php if (!empty($r['detalle'])): ?><div><?= nl2br($h($r['detalle'])) ?></div><?php endif; ?>
      </td>
      <td class="c"><?= $h(trim((string)($r['registra_nombre'] ?? '')) ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="foot">
  Bitácora del profesorado: los registros no están restringidos por el calendario académico;
  cuando la fecha no se especificó al crearlos, se asumió la fecha del día.
</div>

<div class="noprint-note">Usa «Imprimir» del navegador y elige «Guardar como PDF».</div>

<script>
setTimeout(function () {
  var done = false;
  function go() { if (done) return; done = true; try { window.print(); } catch (e) {} }
  // Los navegadores congelan timers de pestañas en segundo plano: imprimir al
  // activarse la pestaña garantiza que la hoja tenga contenido impreso (fix v16.1).
  document.addEventListener('visibilitychange', function () { if (!document.hidden) setTimeout(go, 150); });
  window.addEventListener('focus', function () { setTimeout(go, 150); });
  setTimeout(go, 1200);
}, 0);
</script>
</body>
</html>
