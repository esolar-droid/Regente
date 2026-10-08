<?php
/**
 * Front Controller - Main Entry Point
 * 
 * This file is in public_html/ directory.
 * The app/ directory is at the same level as this file.
 * 
 * Structure:
 * /home/regente2.colmarista.com/public_html/
 * ├── index.php          # <-- This file
 * ├── .htaccess
 * ├── assets/
 * ├── uploads/
 * └── app/
 *     ├── App.php
 *     ├── config/
 *     ├── controllers/
 *     └── ...
 */

// Define absolute paths
// APP_ROOT = /home/regente2.colmarista.com/public_html/
// The app directory is at the same level as this file

define('APP_ROOT', __DIR__);
define('PUBLIC_ROOT', __DIR__);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production mode

// Verify app directory exists
if (!is_dir(APP_ROOT . '/app')) {
    die("Error: Application directory not found. Expected: " . htmlspecialchars(APP_ROOT . '/app'));
}

// Load Composer autoloader if it exists (optional)
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load application autoloader
require_once APP_ROOT . '/app/config/autoload.php';

// Load main application class
require_once APP_ROOT . '/app/App.php';

// Initialize and run the application
try {
    $app = new App();
    $app->run();
} catch (Exception $e) {
    // Log error
    error_log("Application error: " . $e->getMessage());
    
    // Show user-friendly error in development
    if (ini_get('display_errors')) {
        die("Error: " . htmlspecialchars($e->getMessage()));
    } else {
        // In production, show generic error
        http_response_code(500);
        die("Error interno del servidor. Por favor, inténtelo más tarde.");
    }
}
