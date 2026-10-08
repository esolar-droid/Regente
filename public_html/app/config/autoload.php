<?php
/**
 * Autoloader for the application
 * 
 * This file loads all classes and configuration files
 */

// Define APP_ROOT if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..'));
}

// Autoload classes in the App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load Router class (not in App namespace)
spl_autoload_register(function ($class) {
    if ($class === 'Router') {
        $file = APP_ROOT . '/Router.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Load Database class (not in App namespace)
spl_autoload_register(function ($class) {
    if ($class === 'Database') {
        $file = APP_ROOT . '/Database.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Load configuration files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/routes.php';

// Load helpers
require_once APP_ROOT . '/helpers/auth.php';
require_once APP_ROOT . '/helpers/permissions.php';
require_once APP_ROOT . '/helpers/validation.php';
require_once APP_ROOT . '/helpers/sanitize.php';
require_once APP_ROOT . '/helpers/recaptcha.php';
