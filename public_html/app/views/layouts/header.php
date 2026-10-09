<header>
    <nav class="navbar is-fixed-top" role="navigation" aria-label="Navegación principal">
        <div class="navbar-brand">
            <a class="navbar-item brand-mark" href="/dashboard" aria-label="Ir al inicio">
                <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" alt="Colegio Marista">
                <span>SISTEMA PARA REGENTES</span>
            </a>
            <a role="button" class="navbar-burger has-text-white" aria-label="Abrir menú" aria-expanded="false" data-target="mainNavbar">
                <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
            </a>
        </div>

        <div id="mainNavbar" class="navbar-menu">
            <?php if (isAuthenticated()): ?>
                <div class="navbar-start">
                    <a class="navbar-item" href="/dashboard"><i class="fas fa-house"></i><span>Inicio</span></a>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link"><i class="fas fa-users"></i><span>Comunidad educativa</span></a>
                        <div class="navbar-dropdown">
                            <a class="navbar-item" href="/students"><i class="fas fa-user-graduate"></i><span>Estudiantes</span></a>
                            <a class="navbar-item" href="/courses"><i class="fas fa-chalkboard"></i><span>Cursos</span></a>
                        </div>
                    </div>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link"><i class="fas fa-clipboard-check"></i><span>Asistencia</span></a>
                        <div class="navbar-dropdown">
                            <a class="navbar-item" href="/events"><i class="fas fa-calendar-days"></i><span>Registrar novedades</span></a>
                            <a class="navbar-item" href="/reports"><i class="fas fa-chart-column"></i><span>Consultar reportes</span></a>
                        </div>
                    </div>
                    <a class="navbar-item" href="/messages"><i class="fab fa-whatsapp"></i><span>Mensajería</span></a>
                    <?php if (isAdmin() || isRegente()): ?>
                        <div class="navbar-item has-dropdown is-hoverable">
                            <a class="navbar-link"><i class="fas fa-sliders"></i><span>Administración</span></a>
                            <div class="navbar-dropdown">
                                <a class="navbar-item" href="/users"><i class="fas fa-user-gear"></i><span>Usuarios</span></a>
                                <a class="navbar-item" href="/management"><i class="fas fa-calendar-check"></i><span>Gestión escolar</span></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="navbar-end">
                    <div class="navbar-item user-menu has-dropdown is-hoverable">
                        <a class="navbar-link"><span class="user-avatar"><i class="fas fa-user"></i></span><span><?= htmlspecialchars(user()->nombre . ' ' . user()->apellido) ?></span></a>
                        <div class="navbar-dropdown is-right">
                            <div class="navbar-item user-role"><small><?= htmlspecialchars(ucfirst(userRole() ?? 'Usuario')) ?></small></div>
                            <hr class="navbar-divider">
                            <a class="navbar-item" href="/logout"><i class="fas fa-right-from-bracket"></i><span>Cerrar sesión</span></a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</header>
