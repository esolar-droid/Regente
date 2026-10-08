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

// Users
Router::get('/users', 'App\Controllers\UserController@index', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::get('/users/create', 'App\Controllers\UserController@create', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/store', 'App\Controllers\UserController@store', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::get('/users/{id}/edit', 'App\Controllers\UserController@edit', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/{id}/update', 'App\Controllers\UserController@update', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);
Router::post('/users/{id}/delete', 'App\Controllers\UserController@destroy', ['App\Middleware\AuthMiddleware', 'App\Middleware\PermissionMiddleware']);

// Students
Router::get('/students', 'App\Controllers\StudentController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/students/create', 'App\Controllers\StudentController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/students/store', 'App\Controllers\StudentController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/students/{id}/edit', 'App\Controllers\StudentController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/students/{id}/update', 'App\Controllers\StudentController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/students/{id}/delete', 'App\Controllers\StudentController@destroy', ['App\Middleware\AuthMiddleware']);

// Courses
Router::get('/courses', 'App\Controllers\CourseController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/courses/create', 'App\Controllers\CourseController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/store', 'App\Controllers\CourseController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/courses/{id}/edit', 'App\Controllers\CourseController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/{id}/update', 'App\Controllers\CourseController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/courses/{id}/delete', 'App\Controllers\CourseController@destroy', ['App\Middleware\AuthMiddleware']);

// Events
Router::get('/events', 'App\Controllers\EventController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/events/create', 'App\Controllers\EventController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/events/store', 'App\Controllers\EventController@store', ['App\Middleware\AuthMiddleware']);
Router::get('/events/{id}/edit', 'App\Controllers\EventController@edit', ['App\Middleware\AuthMiddleware']);
Router::post('/events/{id}/update', 'App\Controllers\EventController@update', ['App\Middleware\AuthMiddleware']);
Router::post('/events/{id}/delete', 'App\Controllers\EventController@destroy', ['App\Middleware\AuthMiddleware']);

// Management (School Year)
Router::get('/management', 'App\Controllers\ManagementController@index', ['App\Middleware\AuthMiddleware']);
Router::post('/management/switch', 'App\Controllers\ManagementController@switch', ['App\Middleware\AuthMiddleware']);

// Messages
Router::get('/messages', 'App\Controllers\MessageController@index', ['App\Middleware\AuthMiddleware']);
Router::get('/messages/create', 'App\Controllers\MessageController@create', ['App\Middleware\AuthMiddleware']);
Router::post('/messages/store', 'App\Controllers\MessageController@store', ['App\Middleware\AuthMiddleware']);

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
