<?php
/**
 * Front Controller - Main Entry Point
 * 
 * This file must be in the public/ directory.
 * The app/ directory must be at the same level as public/.
 * 
 * Structure:
 * /home/regente2.colmarista.com/
 * ├── public/          # This file is here
 * │   ├── index.php   # <-- This file
 * │   ├── .htaccess
 * │   ├── assets/
 * │   └── uploads/
 * └── app/            # Application logic
 *     ├── App.php
 *     ├── config/
 *     ├── controllers/
 *     └── ...
 */

// Define absolute paths
$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    $appRoot = dirname(__DIR__, 1);
}

define('APP_ROOT', $appRoot);
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
