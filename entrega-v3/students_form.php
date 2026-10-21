<?php
/**
 * Formulario Alta/Edición de estudiante
 * Variables: $student (stdClass|null), $courses, $mode ('create'|'edit'), $csrf_token
 */
ob_start();

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$val = function ($field) use ($student) { return $student->{$field} ?? ''; };
$isEdit = $mode === 'edit';
$action = $isEdit ? "/students/{$student->id}/update" : '/students/store';
?>

<div class="section">
    <div class="container" style="max-width:820px;">

        <a class="button is-light is-small mb-3" href="<?= $isEdit ? "/students/{$student->id}" : '/students' ?>">
            <span class="icon"><i class="fas fa-arrow-left"></i></span><span>Cancelar</span>
        </a>

        <h1 class="title is-3">
            <i class="fas fa-user-graduate"></i>&nbsp;
            <?= $isEdit ? 'Editar ficha: ' . $h($student->nombre_completo) : 'Nuevo estudiante' ?>
        </h1>

        <form method="post" action="<?= $h($action) ?>">
            <?= \App\Middleware\CsrfMiddleware::getTokenInput() ?>

            <div class="box">
                <h2 class="title is-6">Datos personales</h2>
                <div class="columns is-multiline">
                    <div class="column is-full">
                        <label class="label">Nombre completo *</label>
                        <input class="input" type="text" name="nombre_completo" required minlength="3"
                               value="<?= $h($val('nombre_completo')) ?>" placeholder="Apellidos y nombres">
                    </div>
                    <div class="column is-half-tablet">
                        <label class="label">Código BNB</label>
                        <input class="input" type="text" name="codigo_bnb"
                               value="<?= $h($val('codigo_bnb')) ?>" placeholder="Ej. 1234567890">
                    </div>
                    <div class="column is-half-tablet">
                        <label class="label">Fecha de nacimiento</label>
                        <input class="input" type="date" name="fecha_nacimiento"
                               value="<?= $h(substr((string)$val('fecha_nacimiento'), 0, 10)) ?>">
                    </div>
                    <div class="column is-full">
                        <label class="label">Curso</label>
                        <div class="select is-fullwidth">
                            <select name="id_curso">
                                <option value="">— Sin asignar —</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                        <?= (int)($val('id_curso')) === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= $h($c['curso']) ?><?= !empty($c['paralelo']) ? ' (' . $h($c['paralelo']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box">
                <h2 class="title is-6"><i class="fab fa-whatsapp"></i>&nbsp; Teléfonos de contacto</h2>
                <p class="is-size-7 has-text-grey">Formato nacional: fijo 8 dígitos o celular 9 dígitos empezando en 0. Se normalizan para WhatsApp.</p>
                <div class="columns is-multiline">
                    <div class="column is-half-tablet">
                        <label class="label">Teléfono papá</label>
                        <input class="input" type="tel" name="telefono_papa"
                               value="<?= $h($val('telefono_papa')) ?>" placeholder="Ej. 71234567">
                    </div>
                    <div class="column is-half-tablet">
                        <label class="label">Teléfono mamá</label>
                        <input class="input" type="tel" name="telefono_mama"
                               value="<?= $h($val('telefono_mama')) ?>" placeholder="Ej. 071234567">
                    </div>
                    <div class="column is-half-tablet">
                        <label class="label">Teléfono estudiante</label>
                        <input class="input" type="tel" name="telefono_estudiante"
                               value="<?= $h($val('telefono_estudiante')) ?>" placeholder="Opcional">
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <div class="box">
                <h2 class="title is-6">Estado académico inicial</h2>
                <div class="columns is-multiline">
                    <div class="column is-half-tablet">
                        <label class="label">Estado *</label>
                        <div class="select is-fullwidth">
                            <select name="estado" required>
                                <?php foreach (student_states() as $key => $label): ?>
                                    <option value="<?= $h($key) ?>" <?= $key === 'regular' ? 'selected' : '' ?>><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="column is-half-tablet">
                        <label class="label">Fecha de inicio del estado *</label>
                        <input class="input" type="date" name="fecha_ini" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <p class="is-size-7 has-text-grey mb-0">En edición, el estado se cambia desde la ficha con asiento histórico.</p>
            </div>
            <?php endif; ?>

            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" class="button is-primary">
                        <span class="icon"><i class="fas fa-save"></i></span>
                        <span><?= $isEdit ? 'Guardar cambios' : 'Registrar estudiante' ?></span>
                    </button>
                </div>
                <div class="control">
                    <a class="button is-light" href="<?= $isEdit ? "/students/{$student->id}" : '/students' ?>">Cancelar</a>
                </div>
            </div>
        </form>

        <?php if ($isEdit && isAdmin()): ?>
        <div class="box mt-5 has-background-danger-light">
            <h2 class="title is-6 has-text-danger">Zona peligrosa (solo administrador)</h2>
            <p class="is-size-7">Eliminar borra al estudiante junto con sus eventos e historial. Para bajas use el cambio de estado a "Retirado".</p>
            <form method="post" action="/students/<?= (int)$student->id ?>/delete"
                  onsubmit="return confirm('¿Eliminar definitivamente a <?= $h($student->nombre_completo) ?>? Esta acción es irreversible.');">
                <?= \App\Middleware\CsrfMiddleware::getTokenInput() ?>
                <button type="submit" class="button is-danger is-small">
                    <span class="icon"><i class="fas fa-trash"></i></span><span>Eliminar estudiante</span>
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require_once APP_ROOT . '/app/views/layouts/app.php';
