<?php
/**
 * Authentication Middleware
 * 
 * Verifies that the user is authenticated
 */

namespace App\Middleware;

class AuthMiddleware {
    public function handle() {
        if (!isAuthenticated()) {
            // Store the requested URL for redirect after login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            
            // Redirect to login
            header('Location: /login?error=unauthorized');
            exit;
        }
        
        // Optional: Validate session integrity
        $this->validateSession();
    }
    
    /**
     * Validate session integrity (IP and User Agent)
     */
    private function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
            return;
        }
        
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // If IP or User Agent changed, invalidate session
        if ($_SESSION['ip_address'] !== $currentIp || $_SESSION['user_agent'] !== $currentUserAgent) {
            session_unset();
            session_destroy();
            
            header('Location: /login?error=session_invalid');
            exit;
        }
    }
}
