<?php
namespace App\Middleware;

class SecurityMiddleware {
    
    public static function applyAll() {
        // Apply all security middlewares
        SecurityHeadersMiddleware::setHeaders();
        self::checkSessionSecurity();
        self::checkCsrfToken();
    }
    
    public static function checkSessionSecurity() {
        if (!isset($_SESSION['user'])) {
            return;
        }
        
        // Check if IP has changed
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            error_log("Session security: IP changed from {$_SESSION['ip']} to {$_SERVER['REMOTE_ADDR']}");
            AuthMiddleware::logout();
        }
        
        // Check if User Agent has changed
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            error_log("Session security: User Agent changed");
            AuthMiddleware::logout();
        }
        
        // Check session timeout (30 minutes)
        if (isset($_SESSION['logged_in_at'])) {
            $sessionTimeout = 1800; // 30 minutes in seconds
            if (time() - $_SESSION['logged_in_at'] > $sessionTimeout) {
                error_log("Session security: Session timeout");
                AuthMiddleware::logout();
            }
        }
    }
    
    public static function checkCsrfToken() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
                error_log("CSRF protection: Token missing");
                die("Error: Token de seguridad no válido.");
            }
            
            if ($_POST['csrf_token'] !== $_SESSION[CSRF_TOKEN_NAME]) {
                error_log("CSRF protection: Token mismatch");
                die("Error: Token de seguridad no válido.");
            }
            
            // Clear token after use (optional)
            unset($_SESSION[CSRF_TOKEN_NAME]);
        }
    }
    
    public static function checkHttps() {
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            // Redirect to HTTPS if not already
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: {$redirectUrl}", true, 301);
                exit;
            }
        }
    }
    
    public static function logSecurityEvent($event, $details = []) {
        $logMessage = date('Y-m-d H:i:s') . " - {$event}";
        
        if (!empty($details)) {
            $logMessage .= " - " . json_encode($details);
        }
        
        $logMessage .= "\n";
        file_put_contents(APP_ROOT . '/app/logs/security.log', $logMessage, FILE_APPEND);
    }
}
