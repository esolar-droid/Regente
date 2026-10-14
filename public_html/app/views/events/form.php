<?php
/**
 * Alta individual de evento con rango de fechas (v11)
 * Variables: $studentId, $mode, $event, $warn, $csrf_token
 * Tipos elegibles: A (Atraso), L (Licencia), F (Falta). Cada día del rango
 * recibe la marca; al guardar se avisa ANTES de sobrescribir registros.
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$ev = $event ?? null;
$curStudent = $studentId ?: (int)($ev->id_estudiante ?? 0);
$curStart   = $warn['desde'] ?? ($ev ? substr((string)$ev->fecha_evento, 0, 10) : date('Y-m-d'));
$curEnd     = $warn['hasta'] ?? $curStart;
$curTipo    = strtoupper($warn['tipo'] ?? ($ev ? \App\Models\Event::typeToShort($ev->tipo_evento) : 'L'));
$curJust    = $warn['justificacion'] ?? ($ev ? (string)$ev->justificacion : '');

// Nómina para el selector (agrupada por curso)
try {
    $db = \Database::getConnection();
    $stmt = $db->query("SELECT e.id, e.nombre_completo, c.curso AS curso_nombre
                        FROM estudiantes e
                        JOIN gestion g ON g.anio = YEAR(CURDATE()) AND g.estado='activa'
                        LEFT JOIN cursos c ON c.id = e.id_curso
                        WHERE e.id_gestion = g.id AND e.estado <> 'retirado'
                        ORDER BY c.curso, e.nombre_completo");
    $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { error_log('events/form roster: ' . $e->getMessage()); $students = []; }
?>
<div class="section">
  <div class="container" style="max-width:760px;">
    <h1 class="title is-3"><i class="fas fa-user-plus"></i>&nbsp; <?= $mode === 'edit' ? 'Editar evento' : 'Alta individual de asistencia' ?></h1>
    <p class="subtitle is-6">Marca un rango de días con Atraso (A), Licencia (L) o Falta (F). Los feriados se excluyen automáticamente.</p>

    <?php if ($warn): ?>
    <div class="notification is-warning">
      <p><strong>Confirmación requerida:</strong> entre <?= $h($warn['desde']) ?> y <?= $h($warn['hasta']) ?>
         ya existen <strong><?= (int)$warn['n'] ?></strong> registro(s) para este estudiante.</p>
      <p>Guardando, esos días serán <strong>sobrescritos</strong> con la nueva marca
         <?= $h($warn['tipo']) ?>. ¿Deseas continuar?</p>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $mode === 'edit' ? '/events/' . (int)$ev->id . '/update' : '/events/store' ?>" class="box">
      <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
      <?php if ($warn): ?><input type="hidden" name="confirmar" value="1"><?php endif; ?>

      <div class="field">
        <label class="label">Estudiante</label>
        <div class="select is-fullwidth">
          <select name="estudiante" required <?= $mode === 'edit' ? 'disabled' : '' ?>>
            <option value="">— Selecciona —</option>
            <?php $lastCourse = null; foreach ($students as $s): $sid=(int)$s['id']; ?>
              <?php if ($s['curso_nombre'] !== $lastCourse): $lastCourse = $s['curso_nombre']; ?>
                <option disabled>── <?= $h($lastCourse ?: 'Sin curso') ?> ──</option>
              <?php endif; ?>
              <option value="<?= $sid ?>" <?= $sid === $curStudent ? 'selected' : '' ?>><?= $h($s['nombre_completo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($mode === 'edit'): ?><input type="hidden" name="estudiante" value="<?= (int)$curStudent ?>"><?php endif; ?>
      </div>

      <div class="columns">
        <div class="column field">
          <label class="label">Fecha de inicio</label>
          <input class="input" type="date" name="fecha_desde" value="<?= $h($curStart) ?>" required>
        </div>
        <div class="column field">
          <label class="label">Fecha final</label>
          <input class="input" type="date" name="fecha_hasta" value="<?= $h($curEnd) ?>" <?= $mode === 'edit' ? 'disabled' : '' ?> required>
        </div>
      </div>

      <div class="field">
        <label class="label">Tipo de marca</label>
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
        <textarea class="textarea" name="justificacion" rows="3" placeholder="Motivo del atraso/licencia/falta (se aplica a cada día del rango)"><?= $h($curJust) ?></textarea>
      </div>

      <div class="level">
        <div class="level-left"><a class="button is-light" href="<?= $mode === 'edit' ? '/events' : '/attendance' ?>">Cancelar</a></div>
        <div class="level-right">
          <button class="button <?= $warn ? 'is-warning' : 'is-success' ?>">
            <span class="icon"><i class="fas fa-save"></i></span>
            <span><?= $warn ? 'Sí, sobrescribir y guardar' : 'Guardar registro' ?></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
