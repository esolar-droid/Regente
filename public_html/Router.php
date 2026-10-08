<?php
/**
 * Simple Router
 * 
 * Handles routing for the application
 */

class Router {
    private static $routes = [];
    private static $prefix = '';
    
    public static function addRoute($method, $path, $handler, $middleware = []) {
        self::$routes[] = [
            'method' => strtoupper($method),
            'path' => self::$prefix . $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }
    
    public static function prefix($prefix, $callback) {
        $oldPrefix = self::$prefix;
        self::$prefix = $prefix;
        $callback();
        self::$prefix = $oldPrefix;
    }
    
    public static function group($middleware, $callback) {
        $oldMiddleware = self::$globalMiddleware ?? [];
        self::$globalMiddleware = $middleware;
        $callback();
        self::$globalMiddleware = $oldMiddleware;
    }
    
    public static function get($path, $handler, $middleware = []) {
        self::addRoute('GET', $path, $handler, $middleware);
    }
    
    public static function post($path, $handler, $middleware = []) {
        self::addRoute('POST', $path, $handler, $middleware);
    }
    
    public static function put($path, $handler, $middleware = []) {
        self::addRoute('PUT', $path, $handler, $middleware);
    }
    
    public static function delete($path, $handler, $middleware = []) {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }
    
    public static function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Remove trailing slash
        $requestUri = rtrim($requestUri, '/');
        if ($requestUri === '') {
            $requestUri = '/';
        }
        
        foreach (self::$routes as $route) {
            $pattern = self::routeToPattern($route['path']);
            if (preg_match($pattern, $requestUri, $matches) && strtoupper($route['method']) === $requestMethod) {
                // Extract parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                // Execute middleware
                $middleware = array_merge($route['middleware'] ?? [], self::$globalMiddleware ?? []);
                foreach ($middleware as $middlewareClass) {
                    if (class_exists($middlewareClass)) {
                        $middlewareInstance = new $middlewareClass();
                        if (method_exists($middlewareInstance, 'handle')) {
                            $middlewareInstance->handle();
                        }
                    }
                }
                
                // Execute handler
                if (is_string($route['handler']) && strpos($route['handler'], '@') !== false) {
                    list($controller, $method) = explode('@', $route['handler']);
                    if (class_exists($controller)) {
                        $controllerInstance = new $controller();
                        if (method_exists($controllerInstance, $method)) {
                            $controllerInstance->$method(...array_values($params));
                            return;
                        }
                    }
                } elseif (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], array_values($params));
                    return;
                }
                
                // Handler not found
                http_response_code(500);
                die('Internal Server Error: Handler not found');
            }
        }
        
        // No route matched
        http_response_code(404);
        if (file_exists(APP_ROOT . '/app/views/errors/404.php')) {
            require APP_ROOT . '/app/views/errors/404.php';
        } else {
            die('404 Not Found');
        }
    }
    
    private static function routeToPattern($path) {
        // Convert route path to regex pattern
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/', '(?P<$1>[^/]*)?', $pattern);
        return '@^' . $pattern . '$@';
    }
}
