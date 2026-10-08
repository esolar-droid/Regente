<?php
/**
 * Sanitization Helpers
 */

if (!function_exists('sanitize')) {
    function sanitize($value) {
        if (is_array($value)) {
            return array_map('sanitize', $value);
        }
        
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value) {
        if (is_array($value)) {
            return array_map('sanitizeInput', $value);
        }
        
        if (is_string($value)) {
            // Remove tags and trim
            $value = strip_tags(trim($value));
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }
}

if (!function_exists('sanitizeForDatabase')) {
    function sanitizeForDatabase($value) {
        if (is_array($value)) {
            return array_map('sanitizeForDatabase', $value);
        }
        
        if (is_string($value)) {
            // Remove potentially harmful characters
            $value = preg_replace('/[;\'\"<>\/\\]/', '', trim($value));
            return $value;
        }
        
        return $value;
    }
}

if (!function_exists('sanitizeUrl')) {
    function sanitizeUrl($url) {
        if (is_string($url)) {
            return filter_var(trim($url), FILTER_SANITIZE_URL);
        }
        return $url;
    }
}

if (!function_exists('sanitizeEmail')) {
    function sanitizeEmail($email) {
        if (is_string($email)) {
            return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        }
        return $email;
    }
}

if (!function_exists('sanitizePhone')) {
    function sanitizePhone($phone) {
        if (is_string($phone)) {
            // Keep only digits and plus sign
            return preg_replace('/[^0-9+]/', '', trim($phone));
        }
        return $phone;
    }
}

if (!function_exists('sanitizeArray')) {
    function sanitizeArray(array $data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[sanitize($key)] = sanitize($value);
        }
        return $sanitized;
    }
}
