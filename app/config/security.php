<?php
/**
 * Security Configuration
 */

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS is enabled via Cloudflare
ini_set('session.use_only_cookies', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token
define('CSRF_TOKEN_NAME', 'csrf_token');

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes in seconds

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}");
    if (ini_get('display_errors')) {
        echo "Error interno del servidor.";
    }
});

set_exception_handler(function($exception) {
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    if (ini_get('display_errors')) {
        echo "Error interno del servidor: " . htmlspecialchars($exception->getMessage());
    }
});
