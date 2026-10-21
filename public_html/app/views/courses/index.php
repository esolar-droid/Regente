<?php
/**
 * Vista: listado de cursos (/courses)
 * Variables: $courses (con ->alumnos), $canManage, $csrf_token
 */
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$flash = $_SESSION['course_flash'] ?? null; unset($_SESSION['course_flash']);
?>
<div class="section">
  <div class="container">
    <h1 class="title is-3"><i class="fas fa-chalkboard"></i>&nbsp; Cursos</h1>
    <p class="subtitle is-6 mb-4">Mantenimiento del catálogo de cursos (nivel, grado, paralelo).</p>

    <?php if (!empty($flash)): ?>
      <div class="notification <?= $h($flash[0]) ?> py-3"><?= $h($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
      <a class="button is-link mb-4" href="/courses/create">
        <span class="icon"><i class="fas fa-plus"></i></span><span>Nuevo curso</span>
      </a>
    <?php endif; ?>

    <div class="box">
      <table class="table is-fullwidth is-hoverable">
        <thead>
          <tr>
            <th>Código</th><th>Nivel</th><th>Grado</th><th>Paralelo</th>
            <th>Alumnos</th>
            <?php if ($canManage): ?><th>Acciones</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($courses)): ?>
            <tr><td colspan="6" class="has-text-centered has-text-grey py-5">
              No hay cursos registrados todavía.<?= $canManage ? ' Usa «Nuevo curso».' : '' ?>
            </td></tr>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><strong><?= $h($c->curso) ?></strong></td>
                <td><?= $h($c->nivel) ?></td>
                <td><?= $h($c->grado) ?></td>
                <td><?= $h($c->paralelo) ?></td>
                <td><?= (int)($c->alumnos ?? 0) ?></td>
                <?php if ($canManage): ?>
                  <td>
                    <a class="button is-small is-light mr-1" href="/courses/<?= (int)$c->id ?>/edit" title="Editar">
                      <span class="icon"><i class="fas fa-pen"></i></span>
                    </a>
                    <form method="post" action="/courses/<?= (int)$c->id ?>/delete" style="display:inline"
                          onsubmit="return confirm('¿Eliminar el curso <?= $h($c->curso) ?>? Solo si está vacío.');">
                      <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                      <button class="button is-small is-danger is-light" title="Eliminar">
                        <span class="icon"><i class="fas fa-trash"></i></span>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
