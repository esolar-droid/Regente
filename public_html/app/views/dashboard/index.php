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
                                        <p><strong>Ausencias:</strong> <?= $stats['total_ausencias'] ?? 0 ?></p>
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
                                        <?php foreach ($recentEvents as $event): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($event->fecha_evento))) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($event->student_name) ?>
                                                    <br>
                                                    <small class="has-text-grey">Código: <?= htmlspecialchars($event->student_code) ?></small>
                                                </td>
                                                <td>
                                                    <span class="tag is-<?= $this->getEventTagClass($event->tipo_evento) ?>">
                                                        <?= htmlspecialchars(ucfirst($event->tipo_evento)) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($event->justificacion ?? '-') ?></td>
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
                                        <?php foreach ($recentMessages as $message): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($message->fecha_envio))) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($message->student_name) ?>
                                                    <br>
                                                    <small class="has-text-grey">Código: <?= htmlspecialchars($message->student_code) ?></small>
                                                </td>
                                                <td>
                                                    <span class="tag is-<?= $message->destinatario === 'papa' ? 'primary' : 'info' ?>">
                                                        <?= htmlspecialchars(ucfirst($message->destinatario)) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($message->sender_name . ' ' . $message->sender_lastname) ?></td>
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
