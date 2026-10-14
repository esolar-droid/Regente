<?php
/**
 * Hoja de Estudiantes - Listado con filtros, conteos y paginación
 * Variables: $result, $filters, $counts, $courses, $birthdays, $canEdit, $csrf_token
 */
ob_start();

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$pages    = max(1, (int)ceil(($result['total'] ?? 0) / max(1, (int)($result['per_page'] ?? 25))));
$page     = (int)($result['page'] ?? 1);
$totalAll = array_sum($counts);

// Agregar de eventos (atraso/falta/licencia) SOLO para los ids de esta pagina
// (1 consulta en vez de 25; evita el N+1). Falta suma restos 'ausencia'.
$evAgg = [];
if (!empty($result['rows'])) {
    $evAgg = \App\Models\Student::eventCountsForIds(array_column($result['rows'], 'id'));
}

// URL de listado conservando filtros, cambiando página/estado/per_page
$murl = function ($changes) use ($filters) {
    $qs = [
        'q'      => $filters['search'],
        'estado' => $filters['estado'],
        'curso'  => $filters['id_curso'] ?: '',
    ];
    $qs = array_merge($qs, $changes);
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return '/students' . ($qs ? '?' . http_build_query($qs) : '');
};
?>

<div class="section">
    <div class="container">

        <!-- Encabezado -->
        <div class="columns is-vcentered is-mobile">
            <div class="column">
                <h1 class="title is-3"><i class="fas fa-user-graduate"></i>&nbsp; Estudiantes</h1>
                <p class="subtitle is-6 mb-0">
                    <?= number_format($totalAll, 0, ',', '.') ?> registrados ·
                    Gestión <?= $h(\App\Models\Management::getActive()->anio ?? date('Y')) ?>
                </p>
            </div>
            <div class="column is-narrow has-text-right">
                <?php if ($canEdit): ?>
                    <a href="/students/create" class="button is-primary">
                        <span class="icon"><i class="fas fa-plus"></i></span>
                        <span>Nuevo estudiante</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cumpleaños de hoy -->
        <?php if (!empty($birthdays)): ?>
        <div class="notification is-warning is-light py-3">
            <strong><i class="fas fa-cake-candles"></i> Cumplen años hoy (<?= count($birthdays) ?>):</strong>
            <?php foreach ($birthdays as $b): ?>
                <a class="tag is-warning mr-1" href="/students/<?= (int)$b['id'] ?>">
                    <?= $h($b['nombre_completo']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Filtros rapidos por estado -->
        <div class="buttons has-addons are-small mb-4" style="flex-wrap:wrap;">
            <a href="<?= $h($murl(['estado' => ''])) ?>"
               class="button <?= $filters['estado'] === '' ? 'is-link' : 'is-white' ?>">
                Todos <span class="tag <?= $filters['estado'] === '' ? 'is-white' : 'is-light' ?> ml-1"><?= number_format($totalAll, 0, ',', '.') ?></span>
            </a>
            <a href="<?= $h($murl(['estado' => 'activos'])) ?>"
               class="button <?= $filters['estado'] === 'activos' ? 'is-link' : 'is-white' ?>">
                Activos <span class="tag <?= $filters['estado'] === 'activos' ? 'is-white' : 'is-light' ?> ml-1"><?= number_format($totalAll - ($counts['retirado'] ?? 0), 0, ',', '.') ?></span>
            </a>
            <?php foreach (student_states() as $key => $label): ?>
                <a href="<?= $h($murl(['estado' => $key])) ?>"
                   class="button <?= $filters['estado'] === $key ? 'is-link' : 'is-white' ?>">
                    <?= $h($label) ?>
                    <span class="tag <?= $filters['estado'] === $key ? 'is-white' : 'is-light' ?> ml-1"><?= (int)($counts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filtros rapidos: Cumpleañeros -->
        <div class="buttons are-small mb-3" style="flex-wrap:wrap;">
            <a href="<?= $h($murl(['estado' => 'cumpleanos'])) ?>"
               class="button <?= $filters['estado'] === 'cumpleanos' ? 'is-warning' : 'is-white' ?>"
               title="Estudiantes que cumplen años hoy (ordenados por curso)">
                <span class="icon"><i class="fas fa-cake-candles"></i></span>
                <span>Cumpleañeros</span>
                <span class="tag <?= $filters['estado'] === 'cumpleanos' ? 'is-white' : 'is-light' ?> ml-1"><?= count($birthdays ?? []) ?></span>
            </a>
        </div>

        <!-- Buscador + filtro curso -->
        <form method="get" action="/students" class="mb-5">
            <div class="columns is-mobile">
                <div class="column">
                    <div class="field has-addons">
                        <div class="control is-expanded" style="position:relative;">
                            <input class="input" type="text" name="q" id="studentSearch" placeholder="Buscar por nombre o código BNB…"
                                   value="<?= $h($filters['search']) ?>" autocomplete="off" data-suggest-url="/api/suggest?type=estudiante">
                            <div id="studentSuggest" class="suggest-box" hidden></div>
                        </div>
                        <div class="control">
                            <button class="button is-info" type="submit">
                                <span class="icon"><i class="fas fa-search"></i></span>
                            </button>
                        </div>
                        <?php if ($filters['search'] !== ''): ?>
                        <div class="control">
                            <a class="button is-light" href="<?= $h($murl(['q' => ''])) ?>">Limpiar</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="column is-one-third-tablet is-narrow-desktop">
                    <div class="select is-fullwidth">
                        <select name="curso" onchange="this.form.submit()">
                            <option value="">Todos los cursos</option>
                            <?php foreach ($courses as $c):
                                $cid = is_array($c) ? ($c["id"] ?? 0) : ($c->id ?? 0);
                                $cnm = is_array($c) ? ($c["curso"] ?? "") : ($c->curso ?? ""); ?>
                                <option value="<?= (int)$cid ?>" <?= $filters["id_curso"] === (int)$cid ? "selected" : "" ?>>
                                    <?= $h($cnm) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="estado" value="<?= $h($filters['estado']) ?>">
                </div>
            </div>
        </form>

        <!-- Tabla -->
        <div class="box p-0" style="overflow-x:auto;">
            <table class="table is-fullwidth is-hoverable is-striped">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Código BNB</th>
                        <th>Curso</th>
                        <th>Estado</th>
                        <th class="has-text-centered">Eventos<br><small style="color:#fff;opacity:.85;">(A/F/L)</small></th>
                        <th>Tutores</th>
                        <th style="min-width:150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($result['rows'])): ?>
                    <tr><td colspan="7" class="has-text-centered has-text-grey py-5">
                        No hay estudiantes que coincidan con los filtros aplicados.
                    </td></tr>
                <?php else: foreach ($result['rows'] as $s): ?>
                    <tr>
                        <td>
                            <a class="has-text-weight-semibold" href="/students/<?= (int)$s['id'] ?>">
                                <?= $h($s['nombre_completo']) ?>
                            </a>
                            <?php if ($age = student_age($s['fecha_nacimiento'] ?? null)): ?>
                                <br><small class="has-text-grey"><?= (int)$age ?> años</small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= $h($s['codigo_bnb'] ?? '—') ?></code></td>
                        <td><?= $h($s['curso_nombre'] ?? 'Sin curso') ?></td>
                        <td><span class="tag <?= student_state_class($s['estado'] ?? 'regular') ?>">
                                <?= $h(student_state_label($s['estado'] ?? 'regular')) ?>
                        </span></td>
                        <td class="has-text-centered is-size-7">
                            <span class="has-text-warning-dark"><?= (int)($evAgg[(int)$s['id']]['atraso'] ?? 0) ?></span> /
                            <span class="has-text-danger"><?= (int)($evAgg[(int)$s['id']]['falta'] ?? 0) ?></span> /
                            <span class="has-text-info"><?= (int)($evAgg[(int)$s['id']]['licencia'] ?? 0) ?></span>
                        </td>
                        <td class="is-size-7">
                            <?php if (!empty($s['telefono_papa'])): ?>Papá: <?= $h($s['telefono_papa']) ?><br><?php endif; ?>
                            <?php if (!empty($s['telefono_mama'])): ?>Mamá: <?= $h($s['telefono_mama']) ?><?php endif; ?>
                            <?php if (empty($s['telefono_papa']) && empty($s['telefono_mama'])): ?>
                                <span class="has-text-danger">Sin teléfono</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="buttons are-small mb-0">
                                <a class="button is-light" href="/students/<?= (int)$s['id'] ?>" title="Ficha">
                                    <span class="icon"><i class="fas fa-id-card"></i></span>
                                </a>
                                <?php if ($canEdit): ?>
                                    <a class="button is-light" href="/students/<?= (int)$s['id'] ?>/edit" title="Editar">
                                        <span class="icon"><i class="fas fa-pen"></i></span>
                                    </a>
                                <?php endif; ?>
                                <?php $waText = 'Saludos cordiales familia ' . $s['nombre_completo'] . ', le escribe la regencia del Colegio Marista.'; ?>
                                <?php if (!empty($s['telefono_papa'])): ?>
                                <a class="button is-success is-light" title="WhatsApp Papá"
                                   href="https://wa.me/<?= preg_replace('/\D/', '', (string)$s['telefono_papa']) ?>?text=<?= rawurlencode($waText) ?>"
                                   target="_blank" rel="noopener">
                                    <span class="icon"><i class="fab fa-whatsapp"></i></span>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($s['telefono_mama'])): ?>
                                <a class="button is-danger is-light" title="WhatsApp Mamá" style="color:#d63384;"
                                   href="https://wa.me/<?= preg_replace('/\D/', '', (string)$s['telefono_mama']) ?>?text=<?= rawurlencode($waText) ?>"
                                   target="_blank" rel="noopener">
                                    <span class="icon"><i class="fab fa-whatsapp"></i></span>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($s['telefono_estudiante'])): ?>
                                <a class="button is-link is-light" title="WhatsApp Estudiante"
                                   href="https://wa.me/<?= preg_replace('/\D/', '', (string)$s['telefono_estudiante']) ?>?text=<?= rawurlencode('Estimado(a) ' . $s['nombre_completo'] . ', le escribe la regencia del Colegio Marista.') ?>"
                                   target="_blank" rel="noopener">
                                    <span class="icon"><i class="fab fa-whatsapp"></i></span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <nav class="pagination is-centered" role="navigation" aria-label="pagination">
            <?php if ($page > 1): ?>
                <a class="pagination-previous" href="<?= $h($murl(['page' => $page - 1])) ?>">Anterior</a>
            <?php else: ?>
                <span class="pagination-previous is-disabled">Anterior</span>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="pagination-next" href="<?= $h($murl(['page' => $page + 1])) ?>">Siguiente</a>
            <?php else: ?>
                <span class="pagination-next is-disabled">Siguiente</span>
            <?php endif; ?>
            <ul class="pagination-list">
                <li><span class="has-text-grey">Página <?= $page ?> de <?= $pages ?> · <?= number_format($result['total'], 0, ',', '.') ?> resultados</span></li>
            </ul>
        </nav>

    </div>
</div>

<?php
$content = ob_get_clean();
require_once APP_ROOT . '/app/views/layouts/app.php';
