<?php
/**
 * Route Definitions
 */

// Splash and Auth routes
Router::get('/', 'App\Controllers\AuthController@splash');
Router::get('/splash', 'App\Controllers\AuthController@splash');
Router::get('/login', 'App\Controllers\AuthController@login');
Router::post('/login', 'App\Controllers\AuthController@authenticate');
Router::get('/logout', 'App\Controllers\AuthController@logout');

// Dashboard
Router::get('/dashboard', 'App\Controllers\DashboardController@index', ['App\Middleware\AuthMiddleware']);

// API de sugerencias (busqueda predictiva reutilizable: estudiantes, cursos...)
Router::get('/api/suggest', 'App\Controllers\ApiController@suggest', ['App\Middleware\AuthMiddleware']);

// Users
Router::get('/users', 'App\Controllers\UserController@index', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::get('/users/create', 'App\Controllers\UserController@create', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/store', 'App\Controllers\UserController@store', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::get('/users/{id}/edit', 'App\Controllers\UserController@edit', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/{id}/update', 'App\Controllers\UserController@update', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/{id}/delete', 'App\Controllers\UserController@destroy', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);

// Students
Router::get('/tools/stcheck', 'App\Controllers\ToolController@stcheck');
Router::get('/students', 'App\Controllers\StudentController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/students/create', 'App\Controllers\StudentController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/students/store', 'App\Controllers\StudentController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/students/{id}', 'App\Controllers\StudentController@show', ['App\Middleware\AuthMiddleware']);
Router::get('/students/{id}/edit', 'App\Controllers\StudentController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/students/{id}/update', 'App\Controllers\StudentController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/students/{id}/change-state', 'App\Controllers\StudentController@changeState', ['App\Middleware\AuthMiddleware']);
Router::post('/students/{id}/delete', 'App\Controllers\StudentController@destroy', ['App\Middleware\AuthMiddleware']);

// Courses
Router::get('/courses', 'App\Controllers\CourseController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/courses/create', 'App\Controllers\CourseController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/store', 'App\Controllers\CourseController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/courses/{id}/edit', 'App\Controllers\CourseController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/{id}/update', 'App\Controllers\CourseController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/{id}/delete', 'App\Controllers\CourseController@destroy', ['App\Middleware\AuthMiddleware']);

// Buscar (antes «Cursos y Horarios»): 3 columnas de consulta en tiempo real
// sobre la tabla `horarios` (profesor / curso / asignatura AHORA).
Router::get('/search', 'App\Controllers\SearchController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/buscar', 'App\Controllers\SearchController@index', ['App\Middleware\AuthMiddleware']); // alias amigable

// Administración (4 pestañas) — SOLO administrador. El candado fuerte está
// dentro de AdminController (requireAdmin()); AuthMiddleware es la primera capa.
$adminMw = ['App\Middleware\AuthMiddleware'];
Router::get('/admin', 'App\Controllers\AdminController@index', $adminMw);
// Pestaña 1: usuarios y permisos
Router::post('/admin/users/store', 'App\Controllers\AdminController@storeUser', $adminMw);
Router::post('/admin/users/role', 'App\Controllers\AdminController@updateUserRole', $adminMw);
Router::post('/admin/permissions/save', 'App\Controllers\AdminController@savePermissions', $adminMw);
Router::post('/admin/roles/create', 'App\Controllers\AdminController@createRole', $adminMw);
Router::post('/admin/permissions/create', 'App\Controllers\AdminController@createPermission', $adminMw);
// Pestaña 2: horarios (CSV plano + edición)
Router::get('/admin/horarios/plantilla.csv', 'App\Controllers\AdminController@downloadTemplate', $adminMw);
Router::post('/admin/horarios/import', 'App\Controllers\AdminController@importHorarios', $adminMw);
Router::post('/admin/horarios/update', 'App\Controllers\AdminController@updateHorario', $adminMw);
Router::post('/admin/horarios/delete', 'App\Controllers\AdminController@deleteHorario', $adminMw);
// Pestaña 3: gestión escolar (backup / restaurar / migrar)
Router::post('/admin/gestion/backup', 'App\Controllers\AdminController@backupGestion', $adminMw);
Router::post('/admin/gestion/restore', 'App\Controllers\AdminController@restoreGestion', $adminMw);
Router::post('/admin/gestion/migrate', 'App\Controllers\AdminController@migrateGestion', $adminMw);
Router::get('/admin/gestion/snapshot/download', 'App\Controllers\AdminController@downloadSnapshot', $adminMw);
Router::post('/admin/gestion/snapshot/delete', 'App\Controllers\AdminController@deleteSnapshot', $adminMw);
unset($adminMw);

// Events
Router::get('/events', 'App\Controllers\EventController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/events/create', 'App\Controllers\EventController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/events/store', 'App\Controllers\EventController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/events/{id}/edit', 'App\Controllers\EventController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/events/{id}/update', 'App\Controllers\EventController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/events/{id}/delete', 'App\Controllers\EventController@destroy', ['App\Middleware\AuthMiddleware']);

Router::get('/attendance', 'App\Controllers\EventController@attendance', ['App\Middleware\AuthMiddleware']);
Router::post('/attendance/save', 'App\Controllers\EventController@saveAttendance', ['App\Middleware\AuthMiddleware']);

// Management (School Year)
Router::get('/management', 'App\Controllers\ManagementController@index', ['App\Middleware\AuthMiddleware']);
Router::post('/management/switch', 'App\Controllers\ManagementController@switch', ['App\Middleware\AuthMiddleware']);

// Messages — «Hoja de mensajería» (pestañas: enviar | historial)
Router::get('/messages', 'App\Controllers\MessageController@index', ['App\Middleware\AuthMiddleware']);
Router::post('/messages/log', 'App\Controllers\MessageController@logSend', ['App\Middleware\AuthMiddleware']);
Router::post('/messages/{id}/delete', 'App\Controllers\MessageController@destroy', ['App\Middleware\AuthMiddleware']);
// Compatibilidad con enlaces viejos -> pestaña de envío
Router::get('/messages/create', 'App\Controllers\MessageController@index', ['App\Middleware\AuthMiddleware']);

// Reports
Router::get('/reports', 'App\Controllers\ReportController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/reports/export/csv', 'App\Controllers\ReportController@exportCsv', ['App\Middleware\AuthMiddleware']);
Router::get('/reports/export/pdf', 'App\Controllers\ReportController@exportPdf', ['App\Middleware\AuthMiddleware']);

// Error handlers
Router::get('/403', function() {
    http_response_code(403);
    require APP_ROOT . '/app/views/errors/403.php';
});

Router::get('/404', function() {
    http_response_code(404);
    require APP_ROOT . '/app/views/errors/404.php';
});

Router::get('/500', function() {
    http_response_code(500);
    require APP_ROOT . '/app/views/errors/500.php';
});
