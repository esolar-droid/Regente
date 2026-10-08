<?php
/**
 * CSRF Middleware
 * 
 * Generates and validates CSRF tokens for forms
 */

namespace App\Middleware;

class CsrfMiddleware {
    public function handle() {
        // For GET requests, just generate token if not present
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->generateToken();
            return;
        }
        
        // For POST, PUT, DELETE, validate token
        if (!$this->validateToken()) {
            // Log CSRF attack
            error_log("CSRF token validation failed for " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
            
            // Return 403 error
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[CSRF_TOKEN_NAME . '_expiry'] = time() + CSRF_TOKEN_EXPIRY;
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken() {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
        
        if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        // Check if token expired
        if (isset($_SESSION[CSRF_TOKEN_NAME . '_expiry']) && 
            $_SESSION[CSRF_TOKEN_NAME . '_expiry'] < time()) {
            unset($_SESSION[CSRF_TOKEN_NAME]);
            unset($_SESSION[CSRF_TOKEN_NAME . '_expiry']);
            return false;
        }
        
        // Use hash_equals for timing-safe comparison
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Get CSRF token input field for forms
     */
    public static function getTokenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
}
