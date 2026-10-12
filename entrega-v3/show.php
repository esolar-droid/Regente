<?php
/**
 * Ficha del Estudiante - informacion inmediata y precisa
 * Variables: $student (stdClass), $history, $events, $canEdit, $csrf_token
 */
ob_start();

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$waClean = function ($t) {
    $t = preg_replace('/\D/', '', (string)$t);
    if ($t === '') return '';
    if (strlen($t) === 8)  return '591' . $t;   // fijo Bolivia -> +591
    if (strpos($t, '0') === 0 && strlen($t) === 9) return '591' . substr($t, 1); // celular 0X -> +591X
    return $t;
};

$counts = $student->getEventCounts();
$curso  = $student->getCourse();
$gestion = $student->getManagement();
$edad   = student_age($student->fecha_nacimiento);
$whatsappPend = 0;
?>

<div class="section">
    <div class="container">

        <div class="level is-mobile mb-4">
            <div class="level-left">
                <div>
                    <a class="button is-light is-small mb-2" href="/students">
                        <span class="icon"><i class="fas fa-arrow-left"></i></span><span>Volver a la hoja</span>
                    </a>
                    <h1 class="title is-3 mb-0"><?= $h($student->nombre_completo) ?></h1>
                    <p class="subtitle is-6 mb-0">
                        Código BNB: <code><?= $h($student->codigo_bnb ?: '—') ?></code>
                        <?php if ($curso): ?> · <?= $h($curso->curso) ?><?php endif; ?>
                        <?php if ($gestion): ?> · Gestión <?= $h($gestion->anio) ?><?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="level-right has-text-right">
                <span class="tag <?= student_state_class($student->estado) ?> is-medium">
                    <?= $h(student_state_label($student->estado)) ?>
                </span>
                <?php if ($canEdit): ?>
                    <a class="button is-small is-light mt-2" href="/students/<?= (int)$student->id ?>/edit">
                        <span class="icon"><i class="fas fa-pen"></i></span><span>Editar ficha</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="columns">
            <!-- Columna izquierda: datos -->
            <div class="column is-one-third">
                <div class="box">
                    <h2 class="title is-6"><i class="fas fa-id-card"></i>&nbsp; Datos personales</h2>
                    <table class="table is-narrow is-fullwidth">
                        <tr><th>Nombre</th><td><?= $h($student->nombre_completo) ?></td></tr>
                        <tr><th>Código BNB</th><td><?= $h($student->codigo_bnb ?: '—') ?></td></tr>
                        <tr><th>Curso</th><td><?= $h($curso->curso ?? 'Sin curso') ?></td></tr>
                        <tr><th>Nacimiento</th>
                            <td><?= $student->fecha_nacimiento ? $h(date('d/m/Y', strtotime($student->fecha_nacimiento))) : '—' ?>
                                <?php if ($edad !== null): ?><span class="tag is-light ml-1"><?= (int)$edad ?> años</span><?php endif; ?>
                            </td></tr>
                        <tr><th>Estado desde</th>
                            <td><?= $student->fecha_ini ? $h(date('d/m/Y', strtotime($student->fecha_ini))) : '—' ?>
                                <?php if ($student->fecha_fin): ?> hasta <?= $h(date('d/m/Y', strtotime($student->fecha_fin))) ?><?php endif; ?>
                            </td></tr>
                    </table>
                </div>

                <div class="box">
                    <h2 class="title is-6"><i class="fab fa-whatsapp"></i>&nbsp; Contacto familia</h2>
                    <table class="table is-narrow is-fullwidth">
                        <tr><th>Papá</th><td><?= $h($student->telefono_papa ?: '—') ?></td></tr>
                        <tr><th>Mamá</th><td><?= $h($student->telefono_mama ?: '—') ?></td></tr>
                        <tr><th>Estudiante</th><td><?= $h($student->telefono_estudiante ?: '—') ?></td></tr>
                    </table>
                    <div class="buttons are-small">
                        <?php foreach (['PAPÁ' => $student->telefono_papa, 'MAMÁ' => $student->telefono_mama] as $lbl => $tel):
                            $num = $waClean($tel); if (!$num) continue; ?>
                            <a class="button is-success is-light" target="_blank" rel="noopener"
                               href="https://wa.me/<?= $h($num) ?>?text=<?= rawurlencode('Saludos cordiales familia de ' . $student->nombre_completo . ', le escribe la regencia del Colegio Marista.') ?>">
                                <span class="icon"><i class="fab fa-whatsapp"></i></span><span>WhatsApp <?= $lbl ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($student->telefono_papa) && empty($student->telefono_mama)): ?>
                            <span class="has-text-danger is-size-7">⚠ Sin teléfonos registrados: complete la ficha para poder avisar a la familia.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="box">
                    <h2 class="title is-6"><i class="fas fa-chart-simple"></i>&nbsp; Resumen de eventos</h2>
                    <div class="columns is-mobile has-text-centered">
                        <div class="column py-2">
                            <p class="title is-3 has-text-warning-dark"><?= (int)$counts['atraso'] ?></p>
                            <p class="is-size-7">Atrasos</p>
                        </div>
                        <div class="column py-2">
                            <p class="title is-3 has-text-danger"><?= (int)$counts['falta'] ?></p>
                            <p class="is-size-7">Faltas</p>
                        </div>
                        <div class="column py-2">
                            <p class="title is-3 has-text-info"><?= (int)$counts['licencia'] ?></p>
                            <p class="is-size-7">Licencias</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: historial + eventos -->
            <div class="column">
                <?php if ($canEdit): ?>
                <div class="box">
                    <h2 class="title is-6"><i class="fas fa-exchange-alt"></i>&nbsp; Cambiar estado académico</h2>
                    <form method="post" action="/students/<?= (int)$student->id ?>/change-state" class="columns is-mobile is-vcentered is-multiline mb-0">
                        <?= \App\Middleware\CsrfMiddleware::getTokenInput() ?>
                        <div class="column is-half-tablet">
                            <div class="select is-fullwidth">
                                <select name="nuevo_estado" required>
                                    <?php foreach (student_states() as $key => $label): ?>
                                        <option value="<?= $h($key) ?>" <?= $key === $student->estado ? '' : '' ?>><?= $h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column is-one-quarter-tablet">
                            <input class="input" type="date" name="fecha_ini" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="column is-half-tablet">
                            <input class="input" type="text" name="motivo" placeholder="Motivo u observación (opcional)">
                        </div>
                        <div class="column is-narrow">
                            <button class="button is-warning">Registrar cambio</button>
                        </div>
                    </form>
                    <p class="is-size-7 has-text-grey mt-2 mb-0">Cada cambio cierra el asiento anterior y abre uno nuevo en el historial.</p>
                </div>
                <?php endif; ?>

                <div class="box">
                    <h2 class="title is-6"><i class="fas fa-clock-rotate-left"></i>&nbsp; Historial de estados</h2>
                    <?php if (empty($history)): ?>
                        <p class="has-text-grey">Sin asientos históricos (ejecute la migración 001).</p>
                    <?php else: ?>
                        <table class="table is-fullwidth is-narrow">
                            <thead><tr><th>Estado</th><th>Desde</th><th>Hasta</th><th>Motivo</th><th>Registró</th></tr></thead>
                            <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><span class="tag <?= student_state_class($row['estado']) ?>"><?= $h(student_state_label($row['estado'])) ?></span></td>
                                    <td><?= $h($row['fecha_ini'] ? date('d/m/Y', strtotime($row['fecha_ini'])) : '—') ?></td>
                                    <td><?= $row['fecha_fin'] ? $h(date('d/m/Y', strtotime($row['fecha_fin']))) : '<span class="has-text-success">vigente</span>' ?></td>
                                    <td class="is-size-7"><?= $h($row['motivo'] ?? '—') ?></td>
                                    <td class="is-size-7"><?= $h(trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')) ?: 'Sistema') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="box">
                    <h2 class="title is-6"><i class="fas fa-calendar-x-mark"></i>&nbsp; Últimos eventos (máx. 50)</h2>
                    <?php if (empty($events)): ?>
                        <p class="has-text-grey">Sin atrasos, faltas ni licencias registrados.</p>
                    <?php else: ?>
                        <div style="max-height:420px;overflow-y:auto;">
                        <table class="table is-fullwidth is-narrow is-striped">
                            <thead><tr><th>Fecha</th><th>Tipo</th><th>Justificación</th></tr></thead>
                            <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td><?= $h(date('d/m/Y', strtotime($ev['fecha_evento']))) ?></td>
                                    <td><span class="tag <?= event_tag_class($ev['tipo_evento']) ?>"><?= $h(event_label($ev['tipo_evento'])) ?></span></td>
                                    <td class="is-size-7"><?= $h($ev['justificacion'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require_once APP_ROOT . '/app/views/layouts/app.php';
