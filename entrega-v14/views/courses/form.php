<?php
/**
 * Vista: formulario de curso (crear/editar)
 * Variables: $course (objeto Course o null), $csrf_token
 */
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$editing = !empty($course);
$action = $editing ? '/courses/' . (int)$course->id . '/update' : '/courses/store';
$nivel = $editing ? $course->nivel : 'Primaria';
$grado = $editing ? $course->grado : '';
$par   = $editing ? $course->paralelo : '';
?>
<div class="section">
  <div class="container">
    <h1 class="title is-3">
      <i class="fas fa-chalkboard"></i>&nbsp; <?= $editing ? 'Editar curso' : 'Nuevo curso' ?>
    </h1>

    <div class="box" style="max-width:560px">
      <form method="post" action="<?= $h($action) ?>">
        <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">

        <div class="field">
          <label class="label">Nivel</label>
          <div class="control has-icons-left">
            <div class="select is-fullwidth">
              <select name="nivel" required>
                <option value="Primaria"   <?= $nivel === 'Primaria' ? 'selected' : '' ?>>Primaria</option>
                <option value="Secundaria" <?= strtolower($nivel) === 'secundaria' ? 'selected' : '' ?>>Secundaria</option>
              </select>
            </div>
            <div class="icon is-small is-left"><i class="fas fa-layer-group"></i></div>
          </div>
          <p class="help">El código se genera solo: Primaria → P…, Secundaria → S…</p>
        </div>

        <div class="field">
          <label class="label">Grado (1–6)</label>
          <div class="control has-icons-left">
            <input class="input" type="text" name="grado" maxlength="2" required
                   pattern="[1-6PpSs]?" placeholder="Ej.: 1" value="<?= $h($grado) ?>">
            <div class="icon is-small is-left"><i class="fas fa-hashtag"></i></div>
          </div>
        </div>

        <div class="field">
          <label class="label">Paralelo (A, B, C…)</label>
          <div class="control has-icons-left">
            <input class="input" type="text" name="paralelo" maxlength="1" required
                   pattern="[A-Za-z]" placeholder="Ej.: A" value="<?= $h($par) ?>">
            <div class="icon is-small is-left"><i class="fas fa-letter-a"></i></div>
          </div>
        </div>

        <div class="field is-grouped mt-5">
          <div class="control">
            <button class="button is-link"><i class="fas fa-save"></i>&nbsp; Guardar</button>
          </div>
          <div class="control">
            <a class="button is-light" href="/courses">Cancelar</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
