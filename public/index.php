<?php
// Front Controller - Main Entry Point

// Define application constants
define('APP_ROOT', dirname(__DIR__, 1));
define('PUBLIC_ROOT', __DIR__);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Require autoloader
require_once APP_ROOT . '/app/config/autoload.php';

// Initialize the application
$app = new App();
$app->run();
