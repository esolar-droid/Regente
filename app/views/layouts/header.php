<header>
    <nav class="navbar is-primary" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item" href="/dashboard">
                <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
                     alt="Colegio Marista" 
                     style="max-height: 40px;"
                     class="logo">
                <span class="has-text-white has-text-weight-bold ml-3">SISTEMA PARA REGENTES</span>
            </a>
            
            <a role="button" class="navbar-burger has-text-white" aria-label="menu" aria-expanded="false" data-target="navbarMain">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>
        
        <div id="navbarMain" class="navbar-menu">
            <div class="navbar-start">
                <?php if (isAuthenticated()): ?>
                    <a href="/dashboard" class="navbar-item has-text-white">
                        <i class="fas fa-home"></i>&nbsp; Inicio
                    </a>
                    
                    <?php if (can('view_students')): ?>
                        <a href="/students" class="navbar-item has-text-white">
                            <i class="fas fa-user-graduate"></i>&nbsp; Estudiantes
                        </a>
                    <?php endif; ?>
                    
                    <?php if (can('view_courses')): ?>
                        <a href="/courses" class="navbar-item has-text-white">
                            <i class="fas fa-book"></i>&nbsp; Cursos
                        </a>
                    <?php endif; ?>
                    
                    <?php if (can('view_events')): ?>
                        <a href="/events" class="navbar-item has-text-white">
                            <i class="fas fa-calendar"></i>&nbsp; Eventos
                        </a>
                    <?php endif; ?>
                    
                    <?php if (can('view_messages')): ?>
                        <div class="navbar-item has-dropdown is-hoverable">
                            <a class="navbar-link has-text-white">
                                <i class="fas fa-envelope"></i>&nbsp; Mensajes
                            </a>
                            <div class="navbar-dropdown">
                                <a href="/messages" class="navbar-item">Mensajes Enviados</a>
                                <a href="/messages/whatsapp" class="navbar-item">WhatsApp</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (can('view_reports')): ?>
                        <a href="/reports/students" class="navbar-item has-text-white">
                            <i class="fas fa-chart-bar"></i>&nbsp; Reportes
                        </a>
                    <?php endif; ?>
                    
                    <?php if (can('view_users') || can('view_roles') || can('change_management')): ?>
                        <div class="navbar-item has-dropdown is-hoverable">
                            <a class="navbar-link has-text-white">
                                <i class="fas fa-cog"></i>&nbsp; Administración
                            </a>
                            <div class="navbar-dropdown">
                                <?php if (can('view_users')): ?>
                                    <a href="/users" class="navbar-item">Usuarios</a>
                                <?php endif; ?>
                                <?php if (can('view_roles')): ?>
                                    <a href="/users/roles" class="navbar-item">Roles y Permisos</a>
                                <?php endif; ?>
                                <?php if (can('change_management')): ?>
                                    <a href="/management" class="navbar-item">Gestión Escolar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="navbar-end">
                <?php if (isAuthenticated()): ?>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link has-text-white">
                            <i class="fas fa-user-circle"></i>&nbsp; 
                            <?= sanitize(user()->name) ?>
                        </a>
                        <div class="navbar-dropdown is-right">
                            <a href="/profile" class="navbar-item">Perfil</a>
                            <hr class="navbar-divider">
                            <a href="/logout" class="navbar-item has-text-danger">
                                <i class="fas fa-sign-out-alt"></i>&nbsp; Cerrar Sesión
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="navbar-item has-text-white">
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
        
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    });
</script>
