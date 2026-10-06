<?php
namespace App\Middleware;

class SecurityHeadersMiddleware {
    
    public static function setHeaders() {
        // Prevent MIME-sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Prevent XSS
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent Clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://colmarista.com; font-src 'self'; connect-src 'self'");
        
        // HSTS (forzar HTTPS)
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Permissions Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }
}
