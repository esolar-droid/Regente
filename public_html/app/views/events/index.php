<?php
/**
 * Registro de Eventos (v11) — página con DOS pestañas:
 *   1. Registro masivo por curso: rejilla Lunes–Sábado, solo editable la
 *      columna de la fecha elegida; feriados precargados como inhábiles;
 *      clic cíclico P→A→L→F→P; justificación por estudiante; modal al guardar.
 *   2. Registro individual: curso → estudiante → rango de fechas → A/L/F +
 *      justificación; se precarga si el registro ya existe y puede editarse.
 *
 * Variables: $tab, $courses, $course, $courseId, $date, $week, $dayLabels,
 *            $roster, $marks, $feriados, $today, $stats, $saved, $waData,
 *            $studentId, $student, $event, $mode, $warn, $indCourseId,
 *            $indStudents, $indPrefill, $csrf_token
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$ev        = $event ?? null;
$curStudent = (int)($studentId ?: 0);
$curStart   = $warn['desde'] ?? ($indPrefill['desde'] ?? ($ev ? substr((string)$ev->fecha_evento, 0, 10) : date('Y-m-d')));
$curEnd     = $warn['hasta'] ?? ($indPrefill['hasta'] ?? $curStart);
$curTipo    = strtoupper($warn['tipo'] ?? ($indPrefill['tipo'] ?? ($ev ? \App\Models\Event::typeToShort($ev->tipo_evento) : 'L')));
$curJust    = $warn['justificacion'] ?? ($indPrefill['justificacion'] ?? ($ev ? (string)$ev->justificacion : ''));
$curEvId    = $ev ? (int)$ev->id : 0;

// Feriado en la fecha pedida? -> ese día queda INHÁBIL aunque esté seleccionado
$fechaEsFeriado = isset($feriados[$date]);
?>
<div class="section">
  <div class="container">

    <h1 class="title is-3"><i class="fas fa-calendar-check"></i>&nbsp; Registro de Eventos</h1>
    <p class="subtitle is-6 mb-4">Gestión <?= $h(\App\Models\Management::getActive()->anio ?? date('Y')) ?> · Asistencia escolar</p>

    <!-- ===================== PESTAÑAS ===================== -->
    <div class="tabs is-boxed" id="evTabs">
      <ul>
        <li class="<?= $tab === 'masiva' ? 'is-active' : '' ?>" data-tab="masiva">
          <a href="/events?tab=masiva<?= $courseId ? '&curso=' . (int)$courseId . '&fecha=' . $h($date) : '' ?>">
            <span class="icon"><i class="fas fa-table"></i></span><span>Registro por curso (masivo)</span>
          </a>
        </li>
        <li class="<?= $tab === 'individual' ? 'is-active' : '' ?>" data-tab="individual">
          <a href="/events?tab=individual<?= $indCourseId ? '&icurso=' . (int)$indCourseId : '' ?><?= $curStudent ? '&estudiante=' . $curStudent : '' ?>">
            <span class="icon"><i class="fas fa-user-edit"></i></span><span>Registro individual</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- =================== PESTAÑA 1: MASIVA =================== -->
    <div id="panel-masiva" style="<?= $tab === 'masiva' ? '' : 'display:none' ?>">

      <!-- Mini estadísticas -->
      <div class="buttons are-small mb-4" style="flex-wrap:wrap;">
        <span class="tag is-info is-medium">Atrasos: <?= number_format($stats['atraso'], 0, ',', '.') ?></span>
        <span class="tag is-warning is-medium">Licencias: <?= number_format($stats['licencia'], 0, ',', '.') ?></span>
        <span class="tag is-danger is-medium">Faltas: <?= number_format($stats['falta'], 0, ',', '.') ?></span>
      </div>

      <!-- Filtros: Curso + Fecha -->
      <form method="get" action="/events" class="box py-4">
        <input type="hidden" name="tab" value="masiva">
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
            <label class="label is-small">Fecha</label>
            <input class="input" type="date" name="fecha" value="<?= $h($date) ?>">
          </div>
          <div class="column is-narrow">
            <label class="label is-small">&nbsp;</label>
            <button class="button is-link is-fullwidth"><span class="icon"><i class="fas fa-search"></i></span><span>Cargar</span></button>
          </div>
        </div>
        <p class="help">La rejilla muestra <strong>Lunes a Sábado</strong>. Solo es editable la columna de la fecha elegida; los demás días son visibles pero no editables. Los feriados cargados desde la BD quedan inhabilitados.</p>
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

        <?php if ($fechaEsFeriado): ?>
        <div class="notification is-dark is-light">
          <strong><?= $h($date) ?></strong> es feriado (<?= $h($feriados[$date]) ?>): día inhábil.
          La columna seleccionada está bloqueada y no se puede registrar asistencia.
        </div>
        <?php endif; ?>

        <div class="table-container att-scroll">
        <table class="table is-bordered is-narrow is-hoverable is-fullwidth att-grid">
          <thead>
            <tr>
              <th class="att-name-col">Estudiante</th>
              <?php foreach ($week as $d):
                 $dow = (int)date('N', strtotime($d));
                 $isFeriado = isset($feriados[$d]);
                 $isFuture = $d > $today;
                 $isSel = $d === $date;
                 $locked = $isFeriado || $isFuture || !$isSel;
              ?>
                <th class="att-day has-text-centered <?= $isSel ? 'att-selected-head' : '' ?> <?= $locked ? 'att-locked-head' : '' ?>"
                    title="<?= $h($isFeriado ? $feriados[$d] : ($isFuture ? 'Día futuro' : ($isSel ? 'Día en edición' : 'Visible, no editable'))) ?>">
                  <?= $h($dayLabels[$dow] ?? '?') ?> (<?= (int)date('j', strtotime($d)) ?>)
                  <?php if ($isFeriado): ?><br><span class="tag is-dark is-small mt-1">feriado</span><?php endif; ?>
                  <?php if ($isSel && !$isFeriado): ?><br><span class="tag is-link is-small mt-1">editar</span><?php endif; ?>
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
                 $sel = $d === $date;                     // día en edición
                 $mark = $marks[$d][$sid]['tipo'] ?? '';  // A/L/F o '' (P implícito)
                 $dow = (int)date('N', strtotime($d));
                 $isFeriado = isset($feriados[$d]);
                 $isFuture = $d > $today;
                 $locked = $isFeriado || $isFuture || !$sel;
                 // "P" de relleno solo en el día elegido (y si no es feriado);
                 // en los demás días se muestra blanco salvo marca real registrada
                 // (p. ej. un rango futuro cargado desde registro individual).
                 $val = $mark ?: ($sel && !$isFeriado ? 'P' : '');
              ?>
                <td class="att-day-cell <?= $sel ? 'att-selected' : '' ?> <?= $locked ? 'att-locked' : '' ?>"
                    data-sid="<?= $sid ?>" data-fecha="<?= $h($d) ?>" data-locked="<?= $locked ? 1 : 0 ?>">
                  <?php if ($val !== ''): ?>
                  <span class="att-mark att-<?= $h($val) ?>" data-val="<?= $h($val) ?>"><?= $h($val) ?></span>
                  <?php endif; ?>
                  <input type="hidden" name="marcas[<?= $sid ?>][t]" value="<?= $sel ? $h($val) : '' ?>" data-for-day="<?= $h($d) ?>" <?= ($sel && !$locked) ? '' : 'disabled' ?>>
                </td>
              <?php endforeach; ?>
              <td class="att-just-col">
                <input class="input is-small att-just" type="text"
                       name="marcas[<?= $sid ?>][j]"
                       data-sid="<?= $sid ?>"
                       value="<?= $h($marks[$date][$sid]['just'] ?? '') ?>"
                       placeholder="Motivo del A/L/F del día elegido">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>

        <div class="level att-footer">
          <div class="level-left">
            <div class="level-item tags">
              <span class="tag is-success">P Presente</span>
              <span class="tag is-info">A Atraso</span>
              <span class="tag is-warning">L Licencia</span>
              <span class="tag is-danger">F Falta</span>
              <span class="help">Un clic en la letra del día seleccionado cambia P→A→L→F→P.</span>
            </div>
          </div>
          <div class="level-right">
            <div class="level-item">
              <button type="submit" class="button is-success" <?= $fechaEsFeriado ? 'disabled' : '' ?>>
                <span class="icon"><i class="fas fa-save"></i></span><span>Guardar / Sobrescribir</span>
              </button>
            </div>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- ================= PESTAÑA 2: INDIVIDUAL ================= -->
    <div id="panel-individual" class="box" style="<?= $tab === 'individual' ? 'max-width:820px' : 'display:none' ?>">
      <p class="title is-5">Registrar evento individual</p>
      <p class="subtitle is-7">Elige curso y estudiante, define el rango de fechas y la marca (A/L/F). Los feriados se excluyen automáticamente y la planilla semanal refleja los días registrados.</p>

      <?php if ($warn): ?>
      <div class="notification is-warning">
        <p><strong>Confirmación requerida:</strong> entre <?= $h($curStart) ?> y <?= $h($curEnd) ?>
           ya existen <strong><?= (int)$warn['n'] ?></strong> registro(s) para este estudiante.</p>
        <p>Guardando, esos días serán <strong>sobrescritos</strong> con la nueva marca
           <?= $h($curTipo) ?>. ¿Deseas continuar?</p>
      </div>
      <?php endif; ?>

      <form method="post" action="/events/store" id="indForm">
        <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
        <?php if ($curEvId): ?><input type="hidden" name="id" value="<?= $curEvId ?>"><?php endif; ?>
        <?php if ($warn): ?><input type="hidden" name="confirmar" value="1"><?php endif; ?>

        <div class="columns">
          <div class="column field">
            <label class="label">Curso</label>
            <div class="select is-fullwidth">
              <select name="curso" id="indCourse">
                <option value="">— Selecciona un curso —</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $indCourseId ? 'selected' : '' ?>><?= $h($c['curso']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="column field">
            <label class="label">Estudiante</label>
            <div class="select is-fullwidth">
              <select name="estudiante" id="indStudent" required <?= (!$indCourseId && !$curStudent) ? 'disabled' : '' ?>>
                <option value="">— Selecciona —</option>
                <?php foreach ($indStudents as $s): $sid = (int)$s['id']; ?>
                  <option value="<?= $sid ?>" <?= $sid === $curStudent ? 'selected' : '' ?>><?= $h($s['nombre_completo']) ?><?= $s['codigo_bnb'] ? ' · ' . $h($s['codigo_bnb']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="columns">
          <div class="column field">
            <label class="label">Fecha de inicio</label>
            <input class="input" type="date" name="fecha_desde" value="<?= $h($curStart) ?>" required>
          </div>
          <div class="column field">
            <label class="label">Fecha final</label>
            <input class="input" type="date" name="fecha_hasta" value="<?= $h($curEnd) ?>" required>
          </div>
        </div>

        <div class="field">
          <label class="label">Tipo de evento</label>
          <div class="buttons">
            <?php foreach (['A' => ['Atraso', 'is-info'], 'L' => ['Licencia', 'is-warning'], 'F' => ['Falta', 'is-danger']] as $k => [$lbl, $cls]): ?>
              <label class="button <?= $cls ?> is-light <?= $curTipo === $k ? 'is-selected' : '' ?>" style="cursor:pointer">
                <input type="radio" name="tipo" value="<?= $k ?>" <?= $curTipo === $k ? 'checked' : '' ?> style="margin-right:.4em" required>
                <strong><?= $k ?></strong>&nbsp;<?= $lbl ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label class="label">Justificación</label>
          <textarea class="textarea" name="justificacion" rows="3" placeholder="Redacta el motivo (se aplica a cada día del rango)"><?= $h($curJust) ?></textarea>
        </div>

        <div class="level">
          <div class="level-left">
            <?php if ($curEvId): ?>
              <a class="button is-light" href="/events?tab=masiva">Volver a la planilla</a>
            <?php endif; ?>
          </div>
          <div class="level-right">
            <button class="button <?= $warn ? 'is-warning' : 'is-success' ?>">
              <span class="icon"><i class="fas fa-save"></i></span>
              <span><?= $warn ? 'Sí, sobrescribir y guardar' : ($curEvId ? 'Actualizar registro' : 'Guardar registro') ?></span>
            </button>
          </div>
        </div>
      </form>
    </div>

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
               estudiante(s) con atraso/licencia/falta. Puedes enviar avisos a sus tutores.</p>
          <?php else: ?>
            <p class="mt-2">Sin incidentes: todo el curso está presente.</p>
          <?php endif; ?>
        </section>
        <footer class="modal-card-foot">
          <a class="button" href="/events?tab=masiva&curso=<?= (int)$waData['curso'] ?>&fecha=<?= $h($waData['fecha']) ?>" id="modalClose">
            <span class="icon"><i class="fas fa-times"></i></span><span>Cerrar ventana</span>
          </a>
          <?php if ((int)$waData['incidentes'] > 0): ?>
          <a class="button is-success"
             href="/messages?origen=asistencia&curso=<?= (int)$waData['curso'] ?>&fecha=<?= $h($waData['fecha']) ?>">
            <span class="icon"><i class="fab fa-whatsapp"></i></span><span>Enviar mensajes</span>
          </a>
          <?php endif; ?>
        </footer>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<style>
/* Rejilla de asistencia — Registro de Eventos (pestaña masiva) */
.att-scroll { overflow-x:auto; }
.att-grid th, .att-grid td { white-space:nowrap; }
.att-name-col { position:sticky; left:0; background:#fff; z-index:2; min-width:220px; }
.att-num { display:inline-block; width:1.8em; color:#888; font-size:.8em; }
.att-day-cell { text-align:center; }
.att-mark { display:inline-block; width:1.9em; height:1.9em; line-height:1.9em; border-radius:4px;
            font-weight:700; text-align:center; }
.att-P { background:#e8f8f0; color:#257953; }
.att-A { background:#eef3fc; color:#2e60b0; }
.att-L { background:#fdf3e3; color:#9a6a00; }
.att-F { background:#fdecea; color:#c0303a; }
.att-selected      { background:#f2fbff !important; }
.att-selected-head { background:#2e60b0 !important; color:#fff !important; }
.att-locked        { opacity:.55; cursor:not-allowed; background:#f5f5f5; }
.att-locked-head   { background:#eee; color:#777; }
.att-just-col { min-width:220px; }
.att-footer .tags { flex-wrap:wrap; gap:.3rem; }
</style>

<script>
(function () {
  /* ---------- Pestaña 1: ciclo P→A→L→F→P en el día elegido ---------- */
  var CYCLE = ['P', 'A', 'L', 'F'];
  document.querySelectorAll('.att-day-cell:not(.att-locked)').forEach(function (cell) {
    cell.style.cursor = 'pointer';
    cell.addEventListener('click', function () {
      var span = cell.querySelector('.att-mark');
      var hidden = cell.querySelector('input[type=hidden]');
      if (!hidden || hidden.disabled) return;
      // Si el día aparece en blanco (p. ej. marca futura registrada
      // individualmente), el primer clic lo normaliza a "P" editable.
      if (!span) {
        span = document.createElement('span');
        span.className = 'att-mark att-P';
        span.dataset.val = 'P';
        span.textContent = 'P';
        hidden.value = 'P';
        cell.insertBefore(span, hidden);
        return;
      }
      var idx = CYCLE.indexOf(span.dataset.val || 'P');
      var next = CYCLE[(idx + 1) % CYCLE.length];
      span.dataset.val = next;
      span.textContent = next;
      span.className = 'att-mark att-' + next;
      hidden.value = next;
      var row = cell.closest('tr');
      var just = row && row.querySelector('.att-just');
      if (just && next === 'P') just.value = '';
    });
  });

  /* ---------- Pestaña 2: curso -> carga estudiantes (GET con icurso) ---------- */
  var indCourse = document.getElementById('indCourse');
  if (indCourse) {
    indCourse.addEventListener('change', function () {
      var url = '/events?tab=individual';
      if (this.value) url += '&icurso=' + encodeURIComponent(this.value);
      location.href = url;
    });
  }
})();
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
