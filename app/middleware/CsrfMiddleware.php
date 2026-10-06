<?php
namespace App\Middleware;

class CsrfMiddleware {
    
    public static function generateToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    public static function validateToken() {
        if (!isset($_POST[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        if ($_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
            return false;
        }
        
        // Clear token after use (optional)
        unset($_SESSION[CSRF_TOKEN_NAME]);
        return true;
    }
}
