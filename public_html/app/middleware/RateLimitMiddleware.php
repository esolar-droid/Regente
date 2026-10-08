<?php
/**
 * Rate Limit Middleware
 * 
 * Limits login attempts to prevent brute force attacks
 */

namespace App\Middleware;

class RateLimitMiddleware {
    public function handle() {
        // Only apply rate limiting to login page
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($path !== '/login' && $path !== '/') {
            return;
        }
        
        // Check if this is a POST request (login attempt)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'rate_limit_login_' . $ip;
        
        // Initialize or increment attempt counter
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
        }
        
        $attempts = $_SESSION[$key]['attempts'];
        $firstAttempt = $_SESSION[$key]['first_attempt'];
        $window = RATE_LIMIT_LOGIN_WINDOW;
        
        // Check if window has expired
        if (time() - $firstAttempt > $window) {
            // Reset counter
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
            return;
        }
        
        // Check if limit exceeded
        if ($attempts >= RATE_LIMIT_LOGIN_ATTEMPTS) {
            // Log rate limit exceeded
            error_log("Rate limit exceeded for IP: $ip");
            
            // Increment attempt counter even when blocked
            $_SESSION[$key]['attempts']++;
            
            // Return error
            http_response_code(429);
            die('Demasiados intentos de inicio de sesión. Por favor, inténtelo más tarde.');
        }
        
        // Increment attempt counter
        $_SESSION[$key]['attempts']++;
    }
    
    /**
     * Reset rate limit for an IP
     */
    public static function reset($ip) {
        $key = 'rate_limit_login_' . $ip;
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Clear rate limit on successful login
     */
    public static function clearOnSuccess() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'rate_limit_login_' . $ip;
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}
