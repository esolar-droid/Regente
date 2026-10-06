<?php
namespace App\Middleware;

use App\Models\User;

class PermissionMiddleware {
    
    public static function check($permission) {
        AuthMiddleware::check();
        
        if (!isset($_SESSION['user']) || !$_SESSION['user']->hasPermission($permission)) {
            header("Location: /error/403");
            exit;
        }
    }
    
    public static function checkMultiple($permissions) {
        AuthMiddleware::check();
        
        if (!isset($_SESSION['user'])) {
            header("Location: /login");
            exit;
        }
        
        foreach ($permissions as $permission) {
            if ($_SESSION['user']->hasPermission($permission)) {
                return;
            }
        }
        
        header("Location: /error/403");
        exit;
    }
}
