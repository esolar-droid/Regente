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

if (!function_exists('isProfessor')) {
    function isProfessor() {
        return isAuthenticated() && userRole() === 'profesor';
    }
}

/**
 * Devuelve la clase de tag Bulma segun el tipo de evento (asistencia).
 */
if (!function_exists('event_tag_class')) {
    function event_tag_class($tipo) {
        switch (strtolower((string) $tipo)) {
            case 'atraso':   return 'info';
            case 'licencia': return 'warning';
            case 'ausencia':
            case 'falta':    return 'danger';
            default:         return 'light';
        }
    }
}

/**
 * Etiqueta legible para tipos de evento.
 */
if (!function_exists('event_label')) {
    function event_label($tipo) {
        switch (strtolower((string) $tipo)) {
            case 'atraso':   return 'Atraso';
            case 'licencia': return 'Licencia';
            case 'ausencia': return 'Falta';
            case 'salida':   return 'Salida anticipada';
            default:         return ucfirst((string) $tipo);
        }
    }
}
