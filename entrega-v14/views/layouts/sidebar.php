<?php
/**
 * Menú lateral de navegación — unificado con el menú superior.
 *
 * Opciones (visibilidad según rol/permisos):
 *   Inicio, Estudiantes, Asistencia, Mensajería, Reportes, Buscar, Administración.
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$items = [];

if (isAuthenticated()) {
    $items[] = ['route' => '/dashboard', 'label' => 'Inicio', 'icon' => 'fas fa-home'];

    if (can('view_students')) {
        $items[] = ['route' => '/students', 'label' => 'Estudiantes', 'icon' => 'fas fa-user-graduate'];
    }

    // «Asistencia» = Registro de Eventos (masivo + individual)
    if (can('view_events')) {
        $items[] = ['route' => '/events', 'label' => 'Asistencia', 'icon' => 'fas fa-calendar-check'];
    }

    if (can('view_messages') || can('send_whatsapp')) {
        $items[] = ['route' => '/messages', 'label' => 'Mensajería', 'icon' => 'fab fa-whatsapp'];
    }

    if (can('view_reports')) {
        $items[] = ['route' => '/reports', 'label' => 'Reportes', 'icon' => 'fas fa-chart-bar'];
    }

    // «Buscar»: profesor / curso / asignatura en tiempo real (tabla horarios)
    if (can('view_courses')) {
        $items[] = ['route' => '/search', 'label' => 'Buscar', 'icon' => 'fas fa-search'];
    }

    // Panel exclusivo del administrador
    if (isAdmin()) {
        $items[] = ['route' => '/admin', 'label' => 'Administración', 'icon' => 'fas fa-shield-alt'];
    }
}
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <span class="icon"><i class="fas fa-school"></i></span>
        <span class="sidebar-title">Regente</span>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-section-title">Navegación</p>
        <?php foreach ($items as $item): ?>
            <?php
            $isActive = ($currentPath === $item['route']) ||
                        ($item['route'] !== '/' && strpos($currentPath, rtrim($item['route'], '/')) === 0);
            ?>
            <a class="sidebar-link <?= $isActive ? 'is-active' : '' ?>"
               href="<?= htmlspecialchars($item['route']) ?>">
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a class="sidebar-link" href="/logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
