<?php
/**
 * Front Controller - Main Entry Point
 * 
 * This file is in public_html/ directory.
 * The app/ directory is at the same level as this file.
 * 
 * Structure:
 * /home/regente2.colmarista.com/public_html/
 * ├ index.php          # <-- This file
 * ├── .htaccess
 * ├── assets/
 * ├─ uploads/
 * └── app/
 *     ── App.php
 *     ├── config/
 *     ├── controllers/
 *     └── ...
 */

// ============================================================================
// CONFIGURACION INICIAL
// ============================================================================

// Define absolute paths
// APP_ROOT = /home/regente2.colmarista.com/public_html/
// The app directory is at the same level as this file

define('APP_ROOT', __DIR__);
define('PUBLIC_ROOT', __DIR__);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production mode

// ============================================================================
// CONFIGURACION DE LOGGING
// ============================================================================

// Configurar directorio de logs
$logDir = APP_ROOT . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');
ini_set('log_errors', 1);

// ============================================================================
// VERIFICACION DE ESTRUCTURA
// ============================================================================

// Verify app directory exists
if (!is_dir(APP_ROOT . '/app')) {
    die("Error: Application directory not found. Expected: " . htmlspecialchars(APP_ROOT . '/app'));
}

// Verify critical directories
$criticalDirs = [
    APP_ROOT . '/app/config',
    APP_ROOT . '/app/models',
    APP_ROOT . '/app/controllers',
    APP_ROOT . '/app/views',
    APP_ROOT . '/app/helpers',
    APP_ROOT . '/app/middleware',
    APP_ROOT . '/assets',
    APP_ROOT . '/uploads',
];

foreach ($criticalDirs as $dir) {
    if (!is_dir($dir)) {
        die("Error: Directory not found: " . htmlspecialchars($dir));
    }
}

// ============================================================================
// CARGA DE AUTOLADERS
// ============================================================================

// Load Composer autoloader if it exists (optional)
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load application autoloader
// This will define APP_ROOT again if not defined, but we already defined it above
// The autoloader expects APP_ROOT to be the public_html directory
require_once APP_ROOT . '/app/config/autoload.php';

// Load main application class
require_once APP_ROOT . '/app/App.php';

// ============================================================================
// EJECUCION DE LA APLICACION
// ============================================================================

// Initialize and run the application
try {
    $app = new App();
    $app->run();
} catch (Exception $e) {
    // Log error
    error_log("Application error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Show user-friendly error in development
    if (ini_get('display_errors')) {
        die("Error: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
    } else {
        // In production, show generic error
        http_response_code(500);
        die("Error interno del servidor. Por favor, inténtelo más tarde.");
    }
}
