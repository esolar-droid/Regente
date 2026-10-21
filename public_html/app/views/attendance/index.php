<?php
/**
 * Asistencia — Registro masivo por curso y semana (v11)
 * Variables: $courses, $course, $courseId, $date, $week, $dayLabels,
 *            $roster, $marks, $feriados, $today, $stats, $saved, $csrf_token
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$waData = $_SESSION['flash']['wa'] ?? null;
unset($_SESSION['flash']['wa']);

// Estado inicial de la grilla por estudiante/día (solo lectura; JS lo gestiona)
?>
<div class="section">
  <div class="container">

    <div class="columns is-vcentered is-mobile">
      <div class="column">
        <h1 class="title is-3"><i class="fas fa-clipboard-check"></i>&nbsp; Asistencia</h1>
        <p class="subtitle is-6 mb-0">Registro masivo por curso · Gestión <?= $h(\App\Models\Management::getActive()->anio ?? date('Y')) ?></p>
      </div>
      <div class="column is-narrow has-text-right">
        <a href="/events/create" class="button is-light"><span class="icon"><i class="fas fa-user-plus"></i></span><span>Alta individual</span></a>
        <a href="/events" class="button is-light"><span class="icon"><i class="fas fa-list"></i></span><span>Historial</span></a>
      </div>
    </div>

    <!-- Mini estadísticas -->
    <div class="buttons are-small mb-4" style="flex-wrap:wrap;">
      <span class="tag is-info is-medium">Atrasos: <?= number_format($stats['atraso'], 0, ',', '.') ?></span>
      <span class="tag is-warning is-medium">Licencias: <?= number_format($stats['licencia'], 0, ',', '.') ?></span>
      <span class="tag is-danger is-medium">Faltas: <?= number_format($stats['falta'], 0, ',', '.') ?></span>
    </div>

    <!-- Selector curso + fecha -->
    <form method="get" action="/attendance" class="box py-4">
      <div class="columns is-mobile is-vcentered">
        <div class="column is-half-tablet">
          <label class="label is-small">Curso</label>
          <div class="select is-fullwidth">
            <select name="curso" id="attCourse">
              <option value="">— Selecciona un curso —</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>>
                  <?= $h($c['curso']) ?><?= !empty($c['nivel']) ? ' · ' . $h($c['nivel']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="column">
          <label class="label is-small">Fecha (se marca día por día)</label>
          <input class="input" type="date" name="fecha" value="<?= $h($date) ?>">
        </div>
        <div class="column is-narrow">
          <label class="label is-small">&nbsp;</label>
          <button class="button is-link is-fullwidth"><span class="icon"><i class="fas fa-search"></i></span><span>Cargar</span></button>
        </div>
      </div>
      <p class="help">Los cursos <strong>P</strong> (Primaria) muestran Lunes a Viernes; los <strong>S</strong> (Secundaria), Lunes a Sábado. Feriados y días futuros quedan bloqueados.</p>
    </form>

    <?php if (!$course): ?>
      <div class="notification is-info is-light">Selecciona un curso y una fecha para cargar la nómina.</div>
    <?php elseif (!$roster): ?>
      <div class="notification is-warning is-light">No hay estudiantes activos en este curso para la gestión actual.</div>
    <?php else: ?>

    <form method="post" action="/attendance/save" id="attForm">
      <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
      <input type="hidden" name="curso" value="<?= (int)$courseId ?>">
      <input type="hidden" name="fecha" value="<?= $h($date) ?>">

      <div class="table-container att-scroll">
      <table class="table is-bordered is-narrow is-hoverable is-fullwidth att-grid">
        <thead>
          <tr>
            <th class="att-name-col">Estudiante</th>
            <?php foreach ($week as $d):
               $dow = (int)date('N', strtotime($d));
               $isFeriado = isset($feriados[$d]);
               $isFuture = $d > $today;
               $locked = $isFeriado || $isFuture;
            ?>
              <th class="att-day has-text-centered <?= $locked ? 'att-locked-head' : '' ?>" title="<?= $h($isFeriado ? $feriados[$d] : ($isFuture ? 'Día futuro' : '')) ?>">
                <?= $h($dayLabels[$dow] ?? '?') ?> (<?= (int)date('j', strtotime($d)) ?>)
                <?php if ($isFeriado): ?><br><span class="tag is-dark is-small mt-1">feriado</span><?php endif; ?>
              </th>
            <?php endforeach; ?>
            <th class="att-just-col has-text-centered">Justificación<br><span class="att-sub">(del <?= $h(date('d/m', strtotime($date))) ?>)</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roster as $i => $st): $sid = (int)$st['id']; ?>
          <tr>
            <td class="att-name-col">
              <span class="att-num"><?= $i + 1 ?></span>
              <a href="/students/<?= $sid ?>"><?= $h($st['nombre_completo']) ?></a>
            </td>
            <?php foreach ($week as $d):
               $sel = $d === $date;                       // día en edición
               $mark = $marks[$d][$sid]['tipo'] ?? '';    // A/L/F o '' (P implícito)
               $dow = (int)date('N', strtotime($d));
               $isFeriado = isset($feriados[$d]);
               $isFuture = $d > $today;
               $locked = $isFeriado || $isFuture || !$sel;
               $val = $mark ?: 'P';
            ?>
              <td class="att-day-cell <?= $sel ? 'att-selected' : '' ?> <?= $locked ? 'att-locked' : '' ?>"
                  data-sid="<?= $sid ?>" data-fecha="<?= $h($d) ?>" data-locked="<?= $locked ? 1 : 0 ?>">
                <span class="att-mark att-P" data-val="<?= $h($val) ?>"><?= $h($val) ?></span>
                <input type="hidden" name="marcas[<?= $sid ?>][t]" value="<?= $sel ? $h($val) : '' ?>" data-for-day="<?= $h($d) ?>" <?= $sel ? '' : 'disabled' ?>>
              </td>
            <?php endforeach; ?>
            <td class="att-just-col">
              <input class="input is-small att-just" type="text"
                     name="marcas[<?= $sid ?>][j]"
                     data-sid="<?= $sid ?>"
                     value="<?= $h($marks[$date][$sid]['just'] ?? '') ?>"
                     placeholder="Solo si marcas A/L/F el día seleccionado">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <div class="level att-footer">
        <div class="level-left">
          <div class="level-item tags">
            <span class="tag is-success">P Presente (implícito)</span>
            <span class="tag is-info">A Atraso</span>
            <span class="tag is-warning">L Licencia</span>
            <span class="tag is-danger">F Falta</span>
            <span class="help">Un clic en la letra del día seleccionado cambia P→A→L→F→P.</span>
          </div>
        </div>
        <div class="level-right">
          <div class="level-item">
            <button type="submit" class="button is-success">
              <span class="icon"><i class="fas fa-save"></i></span><span>Guardar asistencia</span>
            </button>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <!-- Modal post-guardado -->
    <?php if ($saved && $waData): ?>
    <div class="modal is-active" id="savedModal">
      <div class="modal-background"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title"><i class="fas fa-check-circle has-text-success"></i>&nbsp; Asistencia guardada</p>
        </header>
        <section class="modal-card-body">
          <p>Se guardó la asistencia del curso <strong><?= $h($waData['nombre'] ?: ('#' . $waData['curso'])) ?></strong>
             del <strong><?= $h($waData['fecha']) ?></strong>.</p>
          <?php if ((int)$waData['incidentes'] > 0): ?>
            <p class="mt-2"><span class="tag is-danger"><?= (int)$waData['incidentes'] ?></span>
               estudiante(s) con atraso/licencia/falta. Puedes enviar avisos por WhatsApp a sus tutores.</p>
          <?php else: ?>
            <p class="mt-2">Sin incidentes: todo el curso está presente.</p>
          <?php endif; ?>
        </section>
        <footer class="modal-card-foot">
          <a class="button" href="/attendance?curso=<?= (int)$waData['curso'] ?>&fecha=<?= $h($waData['fecha']) ?>" id="modalClose">
            <span class="icon"><i class="fas fa-times"></i></span><span>Cerrar ventana</span>
          </a>
          <?php if ((int)$waData['incidentes'] > 0): ?>
          <a class="button is-success"
             href="/messages?origen=asistencia&curso=<?= (int)$waData['curso'] ?>&fecha=<?= $h($waData['fecha']) ?>">
            <span class="icon"><i class="fab fa-whatsapp"></i></span><span>Enviar mensajes WhatsApp</span>
          </a>
          <?php endif; ?>
        </footer>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
(function () {
  var CYCLE = ['P', 'A', 'L', 'F'];
  document.querySelectorAll('.att-day-cell:not(.att-locked)').forEach(function (cell) {
    cell.style.cursor = 'pointer';
    cell.addEventListener('click', function () {
      var span = cell.querySelector('.att-mark');
      var hidden = cell.querySelector('input[type=hidden]');
      if (!span || !hidden || hidden.disabled) return;
      var idx = CYCLE.indexOf(span.dataset.val || 'P');
      var next = CYCLE[(idx + 1) % CYCLE.length];
      span.dataset.val = next;
      span.textContent = next;
      span.className = 'att-mark att-' + next;
      hidden.value = next;
      // limpiar justificación si vuelve a P
      var row = cell.closest('tr');
      var just = row && row.querySelector('.att-just');
      if (just && next === 'P') just.value = '';
    });
  });
})();
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
