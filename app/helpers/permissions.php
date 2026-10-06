<?php
// Permission helper functions

if (!function_exists('can')) {
    function can($permission) {
        if (!isAuthenticated()) {
            return false;
        }
        
        $user = user();
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
        return can('edit_roles') || can('edit_user'); // Example: Admin can edit roles/users
    }
}
