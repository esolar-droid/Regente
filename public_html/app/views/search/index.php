<?php
/**
 * Página «Buscar» — tres columnas de consulta sobre la tabla `horarios`,
 * siempre referidas a lo que ocurre AHORA (hora actual del servidor):
 *   1. Buscar profesor : caja predictiva + selector respaldo + botón buscar.
 *   2. Buscar curso    : selector de curso + botón buscar.
 *   3. Buscar asignatura: selector de asignatura + botón buscar.
 * El resultado se muestra DENTRO de la columna usada (GET, sin AJAX).
 *
 * Variables: $tipo, $result{titulo,blocks,totalHoy}, $profesores, $courses,
 *            $subjects, $diaHoy, $horaActual, $selProfesor, $selCurso,
 *            $selAsignatura, $csrf_token
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

/** Renderiza el bloque de resultado de una columna. */
$renderResult = function ($res) use ($h, $diaHoy, $horaActual) {
    if (!$res) return;
    echo '<div class="search-result box" style="background:#f2fbf5;border-left:4px solid #48c774;margin-top:.75rem">';
    echo '<p class="title is-7 mb-1"><i class="fas fa-hourglass-half"></i>&nbsp; Ahora (' . $h($horaActual) . ') · ' . $h($diaHoy) . '</p>';
    if (empty($res['blocks'])) {
        echo '<p class="is-size-7 has-text-grey">Sin horario activo a esta hora'
           . ($res['totalHoy'] ? ' (tiene ' . (int)$res['totalHoy'] . ' franja(s) hoy, pero ninguna cubre esta hora).' : '.')
           . '</p>';
    } else {
        foreach ($res['blocks'] as $b) {
            echo '<div class="mb-2">';
            echo '<span class="tag is-info is-light">' . $h($b['hora_inicio']) . ' – ' . $h($b['hora_fin']) . '</span>';
            foreach ($b['items'] as $it) {
                echo '<div class="content is-small mt-1 mb-0">';
                echo '<strong>' . $h($it['curso']) . '</strong> · ' . $h($it['asignatura']);
                echo ' — <em>' . $h($it['profesor'] !== '' ? $it['profesor'] : 'sin profesor asignado') . '</em>';
                echo '</div>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
};
?>
<div class="section">
  <div class="container">

    <h1 class="title is-3"><i class="fas fa-search"></i>&nbsp; Buscar</h1>
    <p class="subtitle is-6 mb-4">
      ¿Quién dicta ahora? Consulta en tiempo real sobre los horarios de hoy
      (<strong><?= $h($diaHoy) ?></strong>, hora actual: <strong><?= $h($horaActual) ?></strong>).
    </p>

    <div class="columns">

      <!-- ============ Columna 1: Buscar profesor ============ -->
      <div class="column">
        <div class="box">
          <p class="title is-6"><i class="fas fa-user-tie"></i>&nbsp; Buscar Profesor</p>
          <form method="get" action="/search">
            <input type="hidden" name="tipo" value="profesor">
            <div class="field" style="position:relative">
              <div class="control has-icons-left">
                <input class="input is-small" type="text" id="profSearch" placeholder="Escribe el nombre del profesor…"
                       autocomplete="off" data-suggest-url="/api/suggest?type=profesor"
                       data-suggest-target="#profSuggestBox">
                <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
              </div>
              <div id="profSuggestBox" class="suggest-box" hidden></div>
            </div>
            <div class="field">
              <div class="control">
                <select class="select is-small is-fullwidth" name="profesor_id" id="profesorSelect" required>
                  <option value="">— Seleccione un profesor —</option>
                  <?php foreach ($profesores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selProfesor === (int)$p['id'] ? 'selected' : '' ?>>
                      <?= $h($p['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <p class="help is-size-7">La caja superior busca predictivamente; el desplegable es respaldo.</p>
            </div>
            <button class="button is-small is-link" type="submit"><i class="fas fa-search"></i>&nbsp;Buscar</button>
          </form>
          <?php if ($tipo === 'profesor' && $result): ?>
            <p class="mt-2 mb-0 is-size-7"><strong><?= $h($result['titulo']) ?></strong></p>
            <?php $renderResult($result); ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ============ Columna 2: Buscar curso ============ -->
      <div class="column">
        <div class="box">
          <p class="title is-6"><i class="fas fa-chalkboard"></i>&nbsp; Buscar Curso</p>
          <form method="get" action="/search">
            <input type="hidden" name="tipo" value="curso">
            <div class="field">
              <div class="control">
                <select class="select is-small is-fullwidth" name="curso_id" required>
                  <option value="">— Seleccione un curso —</option>
                  <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selCurso === (int)$c['id'] ? 'selected' : '' ?>>
                      <?= $h($c['curso']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="button is-small is-link" type="submit"><i class="fas fa-search"></i>&nbsp;Buscar</button>
          </form>
          <?php if ($tipo === 'curso' && $result): ?>
            <p class="mt-2 mb-0 is-size-7"><strong><?= $h($result['titulo']) ?></strong></p>
            <?php $renderResult($result); ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ============ Columna 3: Buscar asignatura ============ -->
      <div class="column">
        <div class="box">
          <p class="title is-6"><i class="fas fa-book"></i>&nbsp; Buscar Asignatura</p>
          <form method="get" action="/search">
            <input type="hidden" name="tipo" value="asignatura">
            <div class="field">
              <div class="control">
                <select class="select is-small is-fullwidth" name="asignatura" required>
                  <option value="">— Seleccione una asignatura —</option>
                  <?php foreach ($subjects as $s): ?>
                    <option value="<?= $h($s) ?>" <?= $selAsignatura === $s ? 'selected' : '' ?>><?= $h($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="button is-small is-link" type="submit"><i class="fas fa-search"></i>&nbsp;Buscar</button>
          </form>
          <?php if ($tipo === 'asignatura' && $result): ?>
            <p class="mt-2 mb-0 is-size-7"><strong><?= $h($result['titulo']) ?></strong></p>
            <?php $renderResult($result); ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <?php if (!count($subjects)): ?>
      <div class="notification is-warning is-light py-3">
        La tabla <code>horarios</code> está vacía para la gestión actual. Las búsquedas
        funcionarán cuando se carguen los horarios (futuro CRUD en Administración).
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
(function () {
    // Si el usuario escribe en la caja predictiva y pulsa Enter sin elegir
    // sugerencia, no enviar con select vacío: mostrar ayuda.
    var form = document.querySelector('form[action="/search"][method="get"]');
    var sel = document.getElementById('profesorSelect');
    var inp = document.getElementById('profSearch');
    if (inp && sel && form) {
        form.addEventListener('submit', function (e) {
            if (!sel.value) { e.preventDefault(); inp.focus(); }
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
?>
