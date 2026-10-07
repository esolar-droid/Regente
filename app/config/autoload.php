<?php
/**
 * Autoloader for the application
 * 
 * This file loads all classes and configuration files
 */

// Autoload classes in the App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/app/';
    
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

// Load configuration files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/routes.php';

// Load helpers
require_once APP_ROOT . '/app/helpers/auth.php';
require_once APP_ROOT . '/app/helpers/permissions.php';
require_once APP_ROOT . '/app/helpers/validation.php';
require_once APP_ROOT . '/app/helpers/sanitize.php';
require_once APP_ROOT . '/app/helpers/recaptcha.php';
