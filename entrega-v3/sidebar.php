<?php
/**
 * Menú lateral de navegación
 *
 * Las secciones se muestran según los permisos del usuario.
 * Algunas rutas apuntan a páginas que están en construcción
 * (marcadas con badge "pronto").
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$sections = [];

if (isAuthenticated()) {
    $sections[] = [
        'title' => 'General',
        'items' => array_values(array_filter([
            ['route' => '/dashboard', 'label' => 'Inicio', 'icon' => 'fas fa-home'],
            can('view_events') ? ['route' => '/attendance', 'label' => 'Asistencia', 'icon' => 'fas fa-clipboard-check', 'badge' => 'pronto'] : null,
            can('view_reports') ? ['route' => '/reports', 'label' => 'Reportes', 'icon' => 'fas fa-chart-bar'] : null,
        ])),
    ];

    if (can('view_students') || can('view_courses')) {
        $sections[] = [
            'title' => 'Académico',
            'items' => array_values(array_filter([
                can('view_students') ? ['route' => '/students', 'label' => 'Estudiantes', 'icon' => 'fas fa-user-graduate'] : null,
                can('view_courses') ? ['route' => '/courses', 'label' => 'Cursos y Horarios', 'icon' => 'fas fa-chalkboard'] : null,
                can('view_events') ? ['route' => '/events', 'label' => 'Eventos', 'icon' => 'fas fa-calendar-alt'] : null,
            ])),
        ];
    }

    if (can('view_messages') || can('send_whatsapp')) {
        $sections[] = [
            'title' => 'Comunicación',
            'items' => array_values(array_filter([
                can('view_messages') ? ['route' => '/messages', 'label' => 'Mensajes WhatsApp', 'icon' => 'fab fa-whatsapp'] : null,
                can('send_whatsapp') ? ['route' => '/templates', 'label' => 'Plantillas', 'icon' => 'fas fa-file-alt', 'badge' => 'pronto'] : null,
            ])),
        ];
    }

    if (isAdmin() || can('view_users') || can('view_management')) {
        $sections[] = [
            'title' => 'Administración',
            'items' => array_values(array_filter([
                can('view_users') ? ['route' => '/users', 'label' => 'Usuarios', 'icon' => 'fas fa-users-cog'] : null,
                can('view_management') ? ['route' => '/management', 'label' => 'Gestión Escolar', 'icon' => 'fas fa-calendar-check'] : null,
                can('view_professors') ? ['route' => '/professors', 'label' => 'Profesores', 'icon' => 'fas fa-user-tie', 'badge' => 'pronto'] : null,
            ])),
        ];
    }
}
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <span class="icon"><i class="fas fa-school"></i></span>
        <span class="sidebar-title">Regente</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($sections as $section): ?>
            <?php if (!empty($section['items'])): ?>
                <p class="sidebar-section-title"><?= htmlspecialchars($section['title']) ?></p>
                <?php foreach ($section['items'] as $item): ?>
                    <?php
                    $isActive = ($currentPath === $item['route']) ||
                                ($item['route'] !== '/' && strpos($currentPath, rtrim($item['route'], '/')) === 0);
                    ?>
                    <a class="sidebar-link <?= $isActive ? 'is-active' : '' ?>"
                       href="<?= htmlspecialchars($item['route']) ?>">
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="sidebar-badge"><?= htmlspecialchars($item['badge']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a class="sidebar-link" href="/logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
