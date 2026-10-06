<?php
// Route Configuration

class Router {
    private static $routes = [];
    
    public static function add($method, $path, $handler) {
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }
    
    public static function get($path, $handler) {
        self::add('GET', $path, $handler);
    }
    
    public static function post($path, $handler) {
        self::add('POST', $path, $handler);
    }
    
    public static function put($path, $handler) {
        self::add('PUT', $path, $handler);
    }
    
    public static function delete($path, $handler) {
        self::add('DELETE', $path, $handler);
    }
    
    public static function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                self::callHandler($route['handler']);
                return;
            }
        }
        
        // No route found - try dynamic routes
        self::handleDynamicRoutes($method, $path);
    }
    
    private static function callHandler($handler) {
        if (is_callable($handler)) {
            call_user_func($handler);
        } elseif (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);
            $controllerClass = 'App\\Controllers\\' . $controller;
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                if (method_exists($controllerInstance, $method)) {
                    call_user_func([$controllerInstance, $method]);
                } else {
                    self::notFound();
                }
            } else {
                self::notFound();
            }
        } else {
            self::notFound();
        }
    }
    
    private static function handleDynamicRoutes($method, $path) {
        // Handle routes with parameters like /students/edit/1
        $parts = explode('/', $path);
        
        // Example: /students/edit/1 -> StudentsController@edit with id=1
        if (count($parts) >= 3) {
            $resource = $parts[1];
            $action = $parts[2];
            $id = $parts[3] ?? null;
            
            $controller = ucfirst($resource) . 'Controller';
            $controllerClass = 'App\\Controllers\\' . $controller;
            
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                $methodName = $action;
                
                if (method_exists($controllerInstance, $methodName)) {
                    call_user_func([$controllerInstance, $methodName], $id);
                    return;
                }
            }
        }
        
        self::notFound();
    }
    
    private static function notFound() {
        http_response_code(404);
        require_once APP_ROOT . '/app/views/errors/404.php';
    }
}

// Define routes
Router::get('/', 'AuthController@splash');
Router::get('/login', 'AuthController@login');
Router::post('/login', 'AuthController@authenticate');
Router::get('/logout', 'AuthController@logout');

Router::get('/dashboard', 'DashboardController@index');

// User routes
Router::get('/users', 'UserController@index');
Router::get('/users/create', 'UserController@create');
Router::post('/users/store', 'UserController@store');
Router::get('/users/edit', 'UserController@edit');
Router::post('/users/update', 'UserController@update');
Router::get('/users/delete', 'UserController@delete');
Router::get('/users/roles', 'RoleController@index');

// Student routes
Router::get('/students', 'StudentController@index');
Router::get('/students/create', 'StudentController@create');
Router::post('/students/store', 'StudentController@store');
Router::get('/students/edit', 'StudentController@edit');
Router::post('/students/update', 'StudentController@update');
Router::get('/students/delete', 'StudentController@delete');

// Course routes
Router::get('/courses', 'CourseController@index');
Router::get('/courses/create', 'CourseController@create');
Router::post('/courses/store', 'CourseController@store');

// Report routes
Router::get('/reports/students', 'ReportController@students');

// Export routes
Router::get('/exports/students/csv', 'ExportController@studentsCsv');
Router::get('/exports/students/pdf', 'ExportController@studentsPdf');

// Management routes
Router::get('/management', 'ManagementController@index');
Router::post('/management/change', 'ManagementController@change');

// Error routes
Router::get('/error/403', function() {
    http_response_code(403);
    require_once APP_ROOT . '/app/views/errors/403.php';
});
Router::get('/error/404', function() {
    http_response_code(404);
    require_once APP_ROOT . '/app/views/errors/404.php';
});
