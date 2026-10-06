<?php
namespace App\Middleware;

class AuthMiddleware {
    
    public static function check() {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: /login");
            exit;
        }
        
        // Optional: Check IP and User Agent for session hijacking
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            self::logout();
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::logout();
        }
    }
    
    public static function logout() {
        $_SESSION = [];
        session_destroy();
        header("Location: /login?error=session_expired");
        exit;
    }
}
