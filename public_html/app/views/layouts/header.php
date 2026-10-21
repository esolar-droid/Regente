<header>
    <nav class="navbar is-fixed-top" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a role="button" class="navbar-item sidebar-toggle" id="sidebarToggle"
               aria-label="Alternar menú lateral" title="Menú">
                <i class="fas fa-bars"></i>
            </a>
            <a class="navbar-item" href="/dashboard">
                <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
                     alt="Colegio Marista" 
                     style="max-height: 40px; margin-right: 10px;">
                <span class="has-text-white">SISTEMA PARA REGENTES</span>
            </a>
            
            <a role="button" 
               class="navbar-burger has-text-white" 
               aria-label="menu" 
               aria-expanded="false" 
               data-target="mainNavbar">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>
        
        <div id="mainNavbar" class="navbar-menu">
            <div class="navbar-start">
                <?php if (isAuthenticated()): ?>
                    <a class="navbar-item has-text-white" href="/dashboard">
                        <i class="fas fa-home"></i>&nbsp; Inicio
                    </a>

                    <?php if (can('view_students')): ?>
                        <a class="navbar-item has-text-white" href="/students">
                            <i class="fas fa-user-graduate"></i>&nbsp; Estudiantes
                        </a>
                    <?php endif; ?>

                    <?php if (can('view_events')): ?>
                        <a class="navbar-item has-text-white" href="/events">
                            <i class="fas fa-calendar-check"></i>&nbsp; Asistencia
                        </a>
                    <?php endif; ?>

                    <?php if (can('view_messages') || can('send_whatsapp')): ?>
                        <a class="navbar-item has-text-white" href="/messages">
                            <i class="fab fa-whatsapp"></i>&nbsp; Mensajería
                        </a>
                    <?php endif; ?>

                    <?php if (can('view_reports')): ?>
                        <a class="navbar-item has-text-white" href="/reports">
                            <i class="fas fa-chart-bar"></i>&nbsp; Reportes
                        </a>
                    <?php endif; ?>

                    <?php if (can('view_courses')): ?>
                        <a class="navbar-item has-text-white" href="/search">
                            <i class="fas fa-search"></i>&nbsp; Buscar
                        </a>
                    <?php endif; ?>

                    <?php if (can('view_bitacora') || isRegente()): ?>
                        <a class="navbar-item has-text-white" href="/bitacora">
                            <i class="fas fa-chalkboard-teacher"></i>&nbsp; Bitácora
                        </a>
                    <?php endif; ?>

                    <?php if (isAdmin()): ?>
                        <a class="navbar-item has-text-white" href="/admin">
                            <i class="fas fa-shield-alt"></i>&nbsp; Administración
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="navbar-end">
                <?php if (isAuthenticated()): ?>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link has-text-white">
                            <i class="fas fa-user"></i>&nbsp; 
                            <?= htmlspecialchars(user()->nombre . ' ' . user()->apellido) ?>
                        </a>
                        
                        <div class="navbar-dropdown is-right">
                            <a class="navbar-item" href="/dashboard">
                                <i class="fas fa-user-cog"></i>&nbsp; Mi Perfil
                            </a>
                            <hr class="navbar-divider">
                            <a class="navbar-item" href="/logout">
                                <i class="fas fa-sign-out-alt"></i>&nbsp; Cerrar Sesión
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="navbar-item has-text-white" href="/login">
                        <i class="fas fa-sign-in-alt"></i>&nbsp; Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', () => {
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
    
    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }
});
</script>
