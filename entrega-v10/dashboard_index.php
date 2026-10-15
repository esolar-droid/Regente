<?php
// Set the layout
ob_start();
?>

<div class="section">
    <div class="container">
        <!-- Welcome message -->
        <div class="columns is-mobile">
            <div class="column">
                <h1 class="title is-3">
                    <i class="fas fa-home"></i>&nbsp; Dashboard
                </h1>
                <p class="subtitle is-5">
                    Bienvenido, <?= htmlspecialchars(user()->nombre . ' ' . user()->apellido) ?>
                </p>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="columns is-mobile is-multiline mb-5">
            <?php if (isAdmin() || isRegente()): ?>
                <div class="column is-6-mobile is-3-tablet is-3-desktop">
                    <div class="box has-background-primary has-text-white">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="fas fa-3x fa-user-graduate"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-4"><?= $stats['total_students'] ?? 0 ?></p>
                                <p class="subtitle is-6">Estudiantes</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="column is-6-mobile is-3-tablet is-3-desktop">
                    <div class="box has-background-info has-text-white">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="fas fa-3x fa-chalkboard"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-4"><?= $stats['total_courses'] ?? 0 ?></p>
                                <p class="subtitle is-6">Cursos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="column is-6-mobile is-3-tablet is-3-desktop">
                    <div class="box has-background-warning has-text-dark">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="fas fa-3x fa-users"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-4"><?= $stats['total_users'] ?? 0 ?></p>
                                <p class="subtitle is-6">Usuarios</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="column is-6-mobile is-3-tablet is-3-desktop">
                    <div class="box has-background-danger has-text-white">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="fas fa-3x fa-calendar-alt"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-4"><?= $stats['total_events'] ?? 0 ?></p>
                                <p class="subtitle is-6">Eventos</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Event Statistics -->
        <?php if (isAdmin() || isRegente()): ?>
            <div class="columns is-mobile mb-5">
                <div class="column">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                <i class="fas fa-chart-bar"></i>&nbsp; Estadísticas de Eventos
                            </p>
                        </div>
                        <div class="card-content">
                            <div class="columns is-mobile">
                                <div class="column is-4">
                                    <div class="notification is-info">
                                        <p><strong>Atrasos:</strong> <?= $stats['total_atrasos'] ?? 0 ?></p>
                                    </div>
                                </div>
                                <div class="column is-4">
                                    <div class="notification is-warning">
                                        <p><strong>Licencias:</strong> <?= $stats['total_licencias'] ?? 0 ?></p>
                                    </div>
                                </div>
                                <div class="column is-4">
                                    <div class="notification is-danger">
                                        <p><strong>Faltas:</strong> <?= $stats['total_faltas'] ?? 0 ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Active Management -->
        <?php if (isAdmin() || isRegente()): ?>
            <div class="columns is-mobile mb-5">
                <div class="column">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                <i class="fas fa-calendar"></i>&nbsp; Gestión Actual
                            </p>
                        </div>
                        <div class="card-content">
                            <p class="is-size-4">
                                <strong><?= $stats['active_management'] ?? 'Ninguna' ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Recent Events -->
        <?php if (can('view_events') && !empty($recentEvents)): ?>
            <div class="columns is-mobile mb-5">
                <div class="column">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                <i class="fas fa-clock"></i>&nbsp; Eventos Recientes
                            </p>
                        </div>
                        <div class="card-content">
                            <div class="table-container">
                                <table class="table is-fullwidth is-striped">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Estudiante</th>
                                            <th>Tipo</th>
                                            <th>Justificación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentEvents as $event):
                                            $eGet = function ($k, $d = '') use ($event) {
                                                if (is_array($event)) return $event[$k] ?? $d;
                                                if ($event instanceof \App\Models\Model) return $event->toArray()[$k] ?? $d;
                                                return $event->$k ?? $d;
                                            }; ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime((string)$eGet('fecha_evento')))) ?></td>
                                                <td>
                                                    <?= htmlspecialchars((string)$eGet('student_name', '—')) ?>
                                                    <br>
                                                    <small class="has-text-grey">Código: <?= htmlspecialchars((string)$eGet('student_code', '—')) ?></small>
                                                </td>
                                                <td>
                                                    <span class="tag is-<?= event_tag_class((string)$eGet('tipo_evento')) ?>">
                                                        <?= htmlspecialchars(event_label((string)$eGet('tipo_evento'))) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars((string)$eGet('justificacion', '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Recent Messages -->
        <?php if (can('view_messages') && !empty($recentMessages)): ?>
            <div class="columns is-mobile">
                <div class="column">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                <i class="fas fa-envelope"></i>&nbsp; Mensajes Recientes
                            </p>
                        </div>
                        <div class="card-content">
                            <div class="table-container">
                                <table class="table is-fullwidth is-striped">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Estudiante</th>
                                            <th>Destinatario</th>
                                            <th>Enviado por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentMessages as $message):
                                            // Message es objeto (Model); si llegara un array crudo, tambien funciona
                                            $mGet = function ($k, $d = '') use ($message) {
                                                if (is_array($message)) return $message[$k] ?? $d;
                                                if ($message instanceof \App\Models\Model) return $message->toArray()[$k] ?? $d;
                                                return $message->$k ?? $d;
                                            }; ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$mGet('fecha_envio')))) ?></td>
                                                <td>
                                                    <?= htmlspecialchars((string)$mGet('student_name', '—')) ?>
                                                    <br>
                                                    <small class="has-text-grey">Código: <?= htmlspecialchars((string)$mGet('student_code', '—')) ?></small>
                                                </td>
                                                <td>
                                                    <span class="tag is-<?= $mGet('destinatario') === 'papa' ? 'primary' : 'info' ?>">
                                                        <?= htmlspecialchars(ucfirst((string)$mGet('destinatario'))) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars(trim($mGet('sender_name') . ' ' . $mGet('sender_lastname'))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once APP_ROOT . '/app/views/layouts/app.php';
?>
