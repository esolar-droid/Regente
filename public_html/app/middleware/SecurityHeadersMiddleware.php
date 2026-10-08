<?php
/**
 * Security Headers Middleware
 * 
 * Sets security-related HTTP headers
 */

namespace App\Middleware;

class SecurityHeadersMiddleware {
    public static function setHeaders() {
        // Set security headers
        foreach (SECURITY_HEADERS as $header => $value) {
            header("$header: $value");
        }
        
        // Additional security headers
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    }
}
