<?php
/**
 * Permission Middleware
 * 
 * Verifies that the user has the required permission
 */

namespace App\Middleware;

class PermissionMiddleware {
    private $permission;
    
    public function __construct($permission = null) {
        $this->permission = $permission;
    }
    
    public function handle() {
        if (!isAuthenticated()) {
            header('Location: /login?error=unauthorized');
            exit;
        }
        
        // Get the required permission from the route or use default
        $requiredPermission = $this->permission;
        
        // If permission is not set in constructor, try to get it from route
        if (empty($requiredPermission)) {
            // Extract permission from request (e.g., /users/create -> create_user)
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $requiredPermission = $this->extractPermissionFromPath($path);
        }
        
        // Check if user has the required permission
        if (!can($requiredPermission)) {
            // Log unauthorized access attempt
            error_log("Unauthorized access attempt by user " . (userId() ?? 'unknown') . " to " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
            
            // Redirect to 403 or dashboard
            header('Location: /403');
            exit;
        }
    }
    
    /**
     * Extract permission from URL path
     */
    private function extractPermissionFromPath($path) {
        $parts = explode('/', trim($path, '/'));
        
        if (count($parts) >= 2) {
            $resource = $parts[0];
            $action = $parts[1] ?? 'view';
            
            // Map actions to permission names
            $actionMap = [
                'index' => 'view',
                'create' => 'create',
                'store' => 'create',
                'edit' => 'edit',
                'update' => 'edit',
                'delete' => 'delete',
                'destroy' => 'delete',
            ];
            
            $permissionAction = $actionMap[$action] ?? $action;
            return $permissionAction . '_' . $resource;
        }
        
        return 'view_dashboard';
    }
}
