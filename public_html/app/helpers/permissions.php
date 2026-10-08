<?php
/**
 * Permission Helpers
 */

if (!function_exists('can')) {
    function can($permission) {
        if (!isAuthenticated()) {
            return false;
        }
        
        $user = user();
        if ($user === null) {
            return false;
        }
        
        return $user->hasPermission($permission);
    }
}

if (!function_exists('cannot')) {
    function cannot($permission) {
        return !can($permission);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return userRole() === 'administrador' || userRoleId() === 1;
    }
}

if (!function_exists('isRegente')) {
    function isRegente() {
        return userRole() === 'regente' || userRoleId() === 2;
    }
}

if (!function_exists('isProfesor')) {
    function isProfesor() {
        return userRole() === 'profesor' || userRoleId() === 3;
    }
}

if (!function_exists('hasRole')) {
    function hasRole($role) {
        return userRole() === $role;
    }
}

if (!function_exists('hasAnyRole')) {
    function hasAnyRole(array $roles) {
        return in_array(userRole(), $roles);
    }
}
