<?php
namespace App\Middleware;

class RateLimitMiddleware {
    
    public static function check($ip, $action, $maxAttempts = MAX_LOGIN_ATTEMPTS, $windowSeconds = LOGIN_ATTEMPT_WINDOW) {
        $key = "rate_limit:{$ip}:{$action}";
        
        // Use file-based storage for simplicity (can be replaced with Redis)
        $file = APP_ROOT . '/app/logs/rate_limits.log';
        $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        
        $attempts = 0;
        $lastAttempt = 0;
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 3 && $parts[0] === $key) {
                $attempts = (int)$parts[1];
                $lastAttempt = (int)$parts[2];
                break;
            }
        }
        
        $currentTime = time();
        
        // Reset if window has passed
        if ($currentTime - $lastAttempt > $windowSeconds) {
            $attempts = 0;
        }
        
        // Increment attempts
        $attempts++;
        
        // Update log
        $newLines = [];
        $found = false;
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 3 && $parts[0] === $key) {
                $newLines[] = "{$key}|{$attempts}|{$currentTime}";
                $found = true;
            } else {
                $newLines[] = $line;
            }
        }
        
        if (!$found) {
            $newLines[] = "{$key}|{$attempts}|{$currentTime}";
        }
        
        file_put_contents($file, implode("\n", $newLines));
        
        // Check if limit exceeded
        if ($attempts > $maxAttempts) {
            // Log the blocked attempt
            $logMessage = date('Y-m-d H:i:s') . " - Rate limit exceeded for IP: {$ip}, action: {$action}\n";
            file_put_contents(APP_ROOT . '/app/logs/security.log', $logMessage, FILE_APPEND);
            
            // Block for the remaining window time
            $remainingTime = max(0, $windowSeconds - ($currentTime - $lastAttempt));
            header("HTTP/1.1 429 Too Many Requests");
            die("Demasiados intentos. Por favor, espera {$remainingTime} segundos.");
        }
    }
}
