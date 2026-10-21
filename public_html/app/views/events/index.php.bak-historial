<?php
/**
 * Historial de eventos (asistencia) — v11
 * Variables: $rows, $total, $page, $pages, $filters, $courses, $canManage, $csrf_token
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$murl = function ($changes) use ($filters) {
    $qs = array_merge([
        'tipo' => $filters['tipo'] ?? '', 'desde' => $filters['desde'] ?? '',
        'hasta' => $filters['hasta'] ?? '', 'curso' => $filters['curso'] ?? '',
        'estudiante' => $filters['estudiante'] ?? '', 'page' => '',
    ], $changes);
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return '/events' . ($qs ? '?' . http_build_query($qs) : '');
};
?>
<div class="section">
  <div class="container">
    <div class="columns is-vcentered is-mobile">
      <div class="column">
        <h1 class="title is-3"><i class="fas fa-calendar-alt"></i>&nbsp; Historial de eventos</h1>
        <p class="subtitle is-6 mb-0"><?= number_format($total, 0, ',', '.') ?> registro(s) de atrasos, licencias y faltas</p>
      </div>
      <div class="column is-narrow has-text-right">
        <a href="/attendance" class="button is-light"><span class="icon"><i class="fas fa-clipboard-check"></i></span><span>Registro masivo</span></a>
        <a href="/events/create" class="button is-primary"><span class="icon"><i class="fas fa-plus"></i></span><span>Alta individual</span></a>
      </div>
    </div>

    <!-- Filtros -->
    <form method="get" action="/events" class="box py-4">
      <div class="columns is-mobile is-vcentered">
        <div class="column"><label class="label is-small">Tipo</label>
          <div class="select is-fullwidth is-small"><select name="tipo">
            <option value="">Todos</option>
            <?php foreach (['atraso', 'licencia', 'falta'] as $t): ?>
              <option value="<?= $t ?>" <?= ($filters['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= event_label($t) ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div class="column"><label class="label is-small">Desde</label><input class="input is-small" type="date" name="desde" value="<?= $h($filters['desde'] ?? '') ?>"></div>
        <div class="column"><label class="label is-small">Hasta</label><input class="input is-small" type="date" name="hasta" value="<?= $h($filters['hasta'] ?? '') ?>"></div>
        <div class="column"><label class="label is-small">Curso</label>
          <div class="select is-fullwidth is-small"><select name="curso">
            <option value="">Todos</option>
            <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)($filters['curso'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= $h($c['curso']) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="column is-narrow"><button class="button is-small is-link"><span class="icon"><i class="fas fa-filter"></i></span><span>Filtrar</span></button></div>
      </div>
    </form>

    <?php if (!$rows): ?>
      <div class="notification is-info is-light">Sin eventos para los filtros seleccionados.</div>
    <?php else: ?>
    <div class="table-container">
      <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
        <thead><tr>
          <th>Fecha</th><th>Estudiante</th><th>Curso</th><th>Tipo</th><th>Justificación</th><th>Registró</th><th class="has-text-centered">Acciones</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $uid = (int)($r['id_usuario_registro'] ?? 0); ?>
          <tr>
            <td><?= $h($r['fecha_evento']) ?></td>
            <td><a href="/students/<?= (int)$r['id_estudiante'] ?>"><?= $h($r['nombre_completo']) ?></a></td>
            <td><?= $h($r['curso_nombre'] ?? '') ?></td>
            <td><span class="tag <?= event_tag_class($r['tipo_evento']) ?>"><?= event_label($r['tipo_evento']) ?></span></td>
            <td><?= $h($r['justificacion'] ?? '') ?></td>
            <td><?= $h(trim(($r['reg_nome'] ?? '') . ' ' . ($r['reg_ape'] ?? '')) ?: '—') ?></td>
            <td class="has-text-centered">
              <?php if ($canManage($uid)): ?>
                <form method="post" action="/events/<?= (int)$r['id'] ?>/delete" style="display:inline"
                      onsubmit="return confirm('¿Eliminar este evento? El alumno quedará como Presente implícito en esa fecha.');">
                  <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                  <button class="button is-small is-danger is-light"><span class="icon"><i class="fas fa-trash"></i></span></button>
                </form>
              <?php else: ?>
                <span class="icon has-text-grey-light" title="Registro de otro usuario: solo lectura"><i class="fas fa-lock"></i></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="pagination is-small is-centered">
      <ul class="pagination-list">
        <?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
          <li><a class="pagination-link <?= $p === $page ? 'is-current' : '' ?>" href="<?= $h($murl(['page' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
