<?php
/**
 * Autoloader for the application
 * 
 * This file loads all classes and configuration files
 */

// Define APP_ROOT if not already defined
// NOTE: APP_ROOT is already defined in index.php as the public_html directory
// So __DIR__ is public_html/app/config, and __DIR__ . '/..' is public_html/app
// But we want APP_ROOT to be public_html, so we go up one more level
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../'));
}

// ============================================================================
// AUTOLADER PARA CLASSES App\*
// ============================================================================

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/app/';
    
    // Verificar si es una clase del namespace App
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Obtener el path relativo
    $relativeClass = substr($class, $len);
    
    // Convertir namespace a path de archivo
    // Ejemplo: "Controllers\AuthController" -> "controllers/AuthController.php"
    $filePath = str_replace('\\', '/', $relativeClass);
    
    // Dividir en partes
    $parts = explode('/', $filePath);
    $filename = array_pop($parts);
    
    // Convertir directorios a minúsculas (para Linux)
    // pero preservar el case del filename
    $dirPath = implode('/', array_map('strtolower', $parts));
    
    // Construir el path completo
    $file = $baseDir . $dirPath . '/' . $filename . '.php';
    
    if (file_exists($file)) {
        require $file;
        return;
    }
    
    // Intentar con el filename en minúsculas (fallback para algunos casos)
    $fileLower = $baseDir . $dirPath . '/' . strtolower($filename) . '.php';
    if (file_exists($fileLower)) {
        require $fileLower;
        return;
    }
    
    // Intentar con todo en minúsculas (último recurso)
    $fileAllLower = $baseDir . strtolower($filePath) . '.php';
    if (file_exists($fileAllLower)) {
        require $fileAllLower;
    }
});

// ============================================================================
// AUTOLADER PARA CLASSES SIN NAMESPACE (Router, Database)
// ============================================================================

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
        $file = APP_ROOT . '/app/config/database.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// ============================================================================
// CARGA DE ARCHIVOS DE CONFIGURACION
// ============================================================================

// Load configuration files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/routes.php';

// ============================================================================
// CARGA DE HELPERS
// ============================================================================

// Load helpers
require_once APP_ROOT . '/app/helpers/auth.php';
require_once APP_ROOT . '/app/helpers/permissions.php';
require_once APP_ROOT . '/app/helpers/validation.php';
require_once APP_ROOT . '/app/helpers/sanitize.php';
require_once APP_ROOT . '/app/helpers/recaptcha.php';

// ============================================================================
// CARGA EXPLICITA DE CLASSES BASE (para evitar problemas de herencia)
// ============================================================================

// Cargar la clase base Controller antes de que se necesite
if (!class_exists('App\Controllers\Controller')) {
    $controllerFile = APP_ROOT . '/app/controllers/Controller.php';
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
    }
}

// Cargar la clase base Model antes de que se necesite
if (!class_exists('App\Models\Model')) {
    $modelFile = APP_ROOT . '/app/models/Model.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
    }
}
