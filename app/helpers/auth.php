<?php
// Authentication helper functions

if (!function_exists('user')) {
    function user() {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return isset($_SESSION['user']);
    }
}

if (!function_exists('userId')) {
    function userId() {
        return $_SESSION['user']->id ?? null;
    }
}

if (!function_exists('userRole')) {
    function userRole() {
        return $_SESSION['user']->role() ?? null;
    }
}
