<?php
// Set page title
$title = 'Dashboard';

// Get statistics
$stats = [
    'total_students' => 0,
    'total_courses' => 0,
    'total_events' => 0,
    'total_users' => 0,
    'students_by_course' => []
];

try {
    $stats['total_students'] = count(\App\Models\Student::all());
    $stats['total_courses'] = count(\App\Models\Course::all());
    $stats['total_events'] = 0; // Placeholder
    $stats['total_users'] = count(\App\Models\User::all());
    
    // Get students by course for chart
    $courses = \App\Models\Course::all();
    foreach ($courses as $course) {
        $students = \App\Models\Student::getByCourse($course->id);
        $stats['students_by_course'][$course->name] = count($students);
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Start output buffering
ob_start();
?>

<div class="dashboard-container">
    <!-- Stats Cards -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="title">Estudiantes</div>
            <div class="value"><?= $stats['total_students'] ?></div>
        </div>
        
        <div class="stat-card">
            <div class="icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="title">Cursos</div>
            <div class="value"><?= $stats['total_courses'] ?></div>
        </div>
        
        <div class="stat-card">
            <div class="icon">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="title">Eventos</div>
            <div class="value"><?= $stats['total_events'] ?></div>
        </div>
        
        <div class="stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="title">Usuarios</div>
            <div class="value"><?= $stats['total_users'] ?></div>
        </div>
    </div>
    
    <div class="columns">
        <!-- Students by Course Chart -->
        <div class="column">
            <div class="box">
                <div class="box-header">
                    <h3 class="title is-5">Estudiantes por Curso</h3>
                </div>
                <div class="box-content">
                    <canvas id="studentsByCourseChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="column">
            <div class="box">
                <div class="box-header">
                    <h3 class="title is-5">Actividad Reciente</h3>
                </div>
                <div class="box-content">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <p class="has-text-weight-semibold">Nuevo estudiante registrado</p>
                                <p class="has-text-grey is-size-7">Hace 2 horas</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <p class="has-text-weight-semibold">Mensaje enviado a padres</p>
                                <p class="has-text-grey is-size-7">Hace 1 día</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <p class="has-text-weight-semibold">Nuevo curso creado</p>
                                <p class="has-text-grey is-size-7">Hace 3 días</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="box">
        <div class="box-header">
            <h3 class="title is-5">Acciones Rápidas</h3>
        </div>
        <div class="box-content">
            <div class="quick-actions">
                <?php if (can('create_student')): ?>
                    <a href="/students/create" class="button is-primary">
                        <span class="icon">
                            <i class="fas fa-plus"></i>
                        </span>
                        <span>Nuevo Estudiante</span>
                    </a>
                <?php endif; ?>
                
                <?php if (can('create_course')): ?>
                    <a href="/courses/create" class="button is-info">
                        <span class="icon">
                            <i class="fas fa-plus"></i>
                        </span>
                        <span>Nuevo Curso</span>
                    </a>
                <?php endif; ?>
                
                <?php if (can('send_messages')): ?>
                    <a href="/messages/send" class="button is-success">
                        <span class="icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <span>Enviar Mensaje</span>
                    </a>
                <?php endif; ?>
                
                <?php if (can('view_reports')): ?>
                    <a href="/reports/students" class="button is-warning">
                        <span class="icon">
                            <i class="fas fa-chart-bar"></i>
                        </span>
                        <span>Ver Reportes</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for statistics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Students by Course Chart
        const ctx = document.getElementById('studentsByCourseChart').getContext('2d');
        const studentsByCourse = <?= json_encode($stats['students_by_course']) ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(studentsByCourse),
                datasets: [{
                    label: 'Estudiantes por Curso',
                    data: Object.values(studentsByCourse),
                    backgroundColor: '#133b64',
                    borderColor: '#133b64',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    });
</script>

<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .stat-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-card .icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #133b64;
    }
    
    .stat-card .title {
        font-size: 0.875rem;
        color: #777;
        margin-bottom: 0.5rem;
    }
    
    .stat-card .value {
        font-size: 2rem;
        font-weight: bold;
        color: #133b64;
    }
    
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .box-header {
        margin-bottom: 1rem;
    }
    
    .box-content {
        padding: 0 0.5rem;
    }
    
    .timeline {
        position: relative;
        padding-left: 2rem;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e6e7e9;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }
    
    .timeline-marker {
        position: absolute;
        left: -1.5rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #133b64;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #133b64;
    }
    
    .timeline-content {
        background: white;
        padding: 0.75rem;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    @media (max-width: 768px) {
        .dashboard-stats {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php
// Set content for layout
$content = ob_get_clean();

// Include layout
require_once __DIR__ . '/../layouts/app.php';
?>
