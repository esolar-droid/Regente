<?php
// Sanitization helper functions

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return sanitize($data);
    }
}

if (!function_exists('sanitizeOutput')) {
    function sanitizeOutput($data) {
        return sanitize($data);
    }
}

if (!function_exists('sanitizeForDB')) {
    function sanitizeForDB($data) {
        if (is_array($data)) {
            return array_map('sanitizeForDB', $data);
        }
        // Remove HTML tags and trim
        return trim(strip_tags($data));
    }
}

if (!function_exists('sanitizeEmail')) {
    function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitizeUrl')) {
    function sanitizeUrl($url) {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitizeInt')) {
    function sanitizeInt($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
}

if (!function_exists('sanitizeFloat')) {
    function sanitizeFloat($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}
