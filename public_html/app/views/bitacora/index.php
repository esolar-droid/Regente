<?php
/**
 * v17 · Bitácora del profesorado — listado + alta/edición (rol regente/admin)
 *
 * Reglas: fecha OPCIONAL (vacía => hoy); sin restricción de calendario
 * académico; búsqueda libre UN input por nombre del profesor Y detalle.
 *
 * Variables: $rows, $teachers, $f, $total, $hoy, $ok, $flash, $tipos, $csrf_token
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$editId   = (int)($_GET['editar'] ?? 0);
$editing  = null;
foreach ($rows as $r) if ((int)$r['id'] === $editId) { $editing = $r; break; }
if ($editId && !$editing) {
    $e = \App\Models\Bitacora::find($editId);
    if ($e) {
        $editing = [
            'id'          => (int)$e->id,
            'id_profesor' => (int)$e->id_profesor,
            'tipo'        => (string)$e->tipo,
            'titulo'      => (string)$e->titulo,
            'detalle'     => (string)($e->detalle ?? ''),
            'fecha'       => substr((string)$e->fecha, 0, 10),
        ];
    }
}
?>
<div class="section">
  <div class="container">

    <h1 class="title is-3"><i class="fas fa-chalkboard-teacher"></i>&nbsp; Bitácora del profesorado</h1>
    <p class="subtitle is-6 mb-4">Registro de actividades y novedades del equipo docente · Gestión <?= $h(date('Y')) ?></p>

    <?php if (!empty($flash['error'])): ?>
      <div class="notification is-danger is-light py-3"><?= $h($flash['error']) ?></div>
    <?php endif; ?>
    <?php if ($ok || !empty($flash['ok'])): ?>
      <div class="notification is-success is-light py-3"><?= $h($flash['ok'] ?? 'Guardado correctamente.') ?></div>
    <?php endif; ?>

    <!-- ===================== FILTROS / BÚSQUEDA ===================== -->
    <form method="get" action="/bitacora" class="box">
      <div class="columns is-vcentered is-mobile">
        <div class="column is-5">
          <label class="label is-small">Buscar <span class="has-text-grey">(nombre del profesor o detalle del evento)</span></label>
          <div class="control has-icons-left" style="position:relative">
            <input class="input input is-small" type="text" name="q" value="<?= $h($f['q']) ?>"
                   placeholder="Escribe 2+ letras…" autocomplete="off"
                   data-suggest-url="/api/suggest?type=profesor" data-suggest-target="#bitFilterBox">
            <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
            <div class="suggest-box" id="bitFilterBox" hidden></div>
          </div>
        </div>
        <div class="column is-2">
          <label class="label is-small">Tipo</label>
          <div class="select is-small is-fullwidth">
            <select name="tipo">
              <option value="">Todos</option>
              <?php foreach ($tipos as $k => $v): ?>
                <option value="<?= $h($k) ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= $h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="column is-2">
          <label class="label is-small">Desde</label>
          <input class="input is-small" type="date" name="desde" value="<?= $h($f['desde']) ?>">
        </div>
        <div class="column is-2">
          <label class="label is-small">Hasta</label>
          <input class="input is-small" type="date" name="hasta" value="<?= $h($f['hasta']) ?>">
        </div>
        <div class="column is-auto">
          <div class="buttons">
            <button class="button is-small is-link"><i class="fas fa-filter"></i>&nbsp; Aplicar</button>
            <a class="button is-small is-light" href="/bitacora">Limpiar</a>
          </div>
        </div>
      </div>
    </form>

    <div class="box py-2 px-3 mb-4">
      <span class="tag is-info is-light"><?= (int)$total ?> registro(s) en total</span>
      <span class="tag is-light"><?= count($rows) ?> con filtros actuales</span>
      &nbsp;
      <a class="button is-small is-primary" target="_blank" rel="noopener"
         href="/bitacora/export/pdf?<?= $h(http_build_query(array_filter([
           'q' => $f['q'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'tipo' => $f['tipo'],
         ]))) ?>"><i class="fas fa-file-pdf"></i>&nbsp; Exportar PDF</a>
    </div>

    <!-- ===================== ALTA / EDICIÓN ===================== -->
    <div class="box" id="bitFormBox">
      <h2 class="title is-5">
        <i class="fas fa-plus-circle"></i>&nbsp; <?= $editing ? 'Editar registro' : 'Nuevo registro' ?>
      </h2>
      <form method="post" action="<?= $editing ? '/bitacora/' . (int)$editing['id'] . '/update' : '/bitacora/store' ?>">
        <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
        <input type="hidden" name="fq" value="<?= $h($f['q']) ?>">
        <input type="hidden" name="fdesde" value="<?= $h($f['desde']) ?>">
        <input type="hidden" name="fhasta" value="<?= $h($f['hasta']) ?>">
        <input type="hidden" name="ftipo" value="<?= $h($f['tipo']) ?>">

        <div class="columns">
          <div class="column is-5">
            <label class="label">Profesor (educador) <span class="has-text-danger">*</span></label>
            <div class="control has-icons-left" style="position:relative">
              <input class="input" id="bitProfInput" placeholder="Escribe 2+ letras…" autocomplete="off"
                     data-suggest-url="/api/suggest?type=profesor" data-suggest-target="#bitProfBox"
                     data-suggest-select="#bitProfId,#bitProfLabel"
                     value="<?= $editing ? $h((function () use ($editing, $teachers) {
                         foreach ($teachers as $t) if ($t['id'] === (int)$editing['id_profesor']) return $t['nombre'];
                         return '';
                     })()) : '' ?>">
              <span class="icon is-small is-left"><i class="fas fa-user-tie"></i></span>
              <div class="suggest-box" id="bitProfBox" hidden></div>
            </div>
            <input type="hidden" name="id_profesor" id="bitProfId" value="<?= $editing ? (int)$editing['id_profesor'] : 0 ?>">
            <input type="hidden" id="bitProfLabel" value="">
            <div class="select is-small mt-2 is-fullwidth">
              <select id="bitProfSelect"
                      onchange="document.getElementById('bitProfId').value=this.value;document.getElementById('bitProfInput').value=this.selectedIndex>0?this.options[this.selectedIndex].text:'';">
                <option value="0">…o selecciónalo de la lista</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= (int)$t['id'] ?>" <?= $editing && (int)$editing['id_profesor'] === $t['id'] ? 'selected' : '' ?>><?= $h($t['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="column is-3">
            <label class="label">Tipo</label>
            <div class="select is-fullwidth">
              <select name="tipo">
                <?php foreach ($tipos as $k => $v): ?>
                  <option value="<?= $h($k) ?>" <?= (($editing['tipo'] ?? 'actividad') === $k) ? 'selected' : '' ?>><?= $h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <label class="label mt-3">Fecha <span class="has-text-grey">(opcional)</span></label>
            <input class="input" type="date" name="fecha" value="<?= $editing ? $h($editing['fecha']) : '' ?>">
            <p class="help">Si la dejas vacía se registrará con la fecha de hoy (<?= $h($hoy) ?>).</p>
          </div>

          <div class="column">
            <label class="label">Título <span class="has-text-danger">*</span></label>
            <input class="input" type="text" name="titulo" maxlength="160" required
                   value="<?= $editing ? $h($editing['titulo']) : '' ?>" placeholder="Ej.: Reunión de apoderados 5°A">
            <label class="label mt-3">Detalle</label>
            <textarea class="textarea" name="detalle" rows="4"
                      placeholder="Texto libre del registro (también se busca por aquí)…"><?= $editing ? $h($editing['detalle']) : '' ?></textarea>
          </div>
        </div>

        <div class="buttons">
          <button class="button is-primary" type="submit">
            <i class="fas fa-save"></i>&nbsp; <?= $editing ? 'Guardar cambios' : 'Registrar' ?>
          </button>
          <?php if ($editing): ?>
            <a class="button is-light" href="/bitacora">Cancelar edición</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- ===================== LISTADO ===================== -->
    <div class="table-container">
      <table class="table is-striped is-hoverable is-fullwidth">
        <thead>
          <tr>
            <th>Fecha</th><th>Profesor</th><th>Tipo</th><th>Título y detalle</th><th>Registra</th><th class="has-text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="has-text-centered has-text-grey py-5">
              Sin registros. <?= ($f['q'] !== '' || $f['tipo'] !== '' || $f['desde'] !== '')
                ? 'Prueba otros filtros o registra uno nuevo arriba.'
                : 'Aún no hay bitácora: usa el formulario de arriba para crear el primer registro.' ?>
            </td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td style="white-space:nowrap"><?= $h(substr((string)$r['fecha'], 0, 10)) ?></td>
              <td style="white-space:nowrap"><strong><?= $h(trim((string)$r['profesor_nombre']) ?: '—') ?></strong></td>
              <td><span class="tag is-light"><?= $h($tipos[$r['tipo']] ?? ucfirst((string)$r['tipo'])) ?></span></td>
              <td>
                <div><strong><?= $h($r['titulo']) ?></strong></div>
                <?php if (!empty($r['detalle'])): ?>
                  <div class="is-size-7 has-text-grey"><?= nl2br($h(mb_strimwidth((string)$r['detalle'], 0, 300, '…'))) ?></div>
                <?php endif; ?>
              </td>
              <td class="is-size-7"><?= $h(trim((string)($r['registra_nombre'] ?? '')) ?: '—') ?></td>
              <td class="has-text-right" style="white-space:nowrap">
                <a class="button is-small is-light" title="Editar"
                   href="/bitacora?editar=<?= (int)$r['id'] ?>&<?= $h(http_build_query(array_filter(['q'=>$f['q'],'desde'=>$f['desde'],'hasta'=>$f['hasta'],'tipo'=>$f['tipo']]))) ?>">
                  <i class="fas fa-pen"></i></a>
                <form method="post" action="/bitacora/<?= (int)$r['id'] ?>/delete" style="display:inline"
                      onsubmit="return confirm('¿Eliminar este registro de la bitácora?');">
                  <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                  <button class="button is-small is-danger is-light" title="Eliminar"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
(function () {
  // No enviar con profesor sin seleccionar (el predictivo rellena #bitProfId).
  var form = document.querySelector('#bitFormBox form');
  if (form) {
    form.addEventListener('submit', function (e) {
      var id = document.getElementById('bitProfId');
      if (!id || !id.value || id.value === '0') {
        e.preventDefault();
        document.getElementById('bitProfInput').focus();
        alert('Selecciona un profesor (escribe su nombre o usa la lista).');
      }
    });
  }
})();
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
?>
