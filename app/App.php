<?php
/**
 * Main Application Class
 * 
 * This class initializes the application and handles routing
 */

class App {
    public function __construct() {
        // Initialize security headers
        \App\Middleware\SecurityHeadersMiddleware::setHeaders();
        
        // Check if database tables exist and seed if needed
        $this->initializeDatabase();
    }
    
    public function run() {
        // Dispatch routes
        \Router::dispatch();
    }
    
    private function initializeDatabase() {
        try {
            $db = \Database::getConnection();
            
            // Check if roles table exists
            $stmt = $db->query("SHOW TABLES LIKE 'roles'");
            if ($stmt->rowCount() === 0) {
                // Roles table doesn't exist, seed default permissions
                \App\Models\Permission::seed();
            }
            
            // Check if there's at least one user (admin)
            $stmt = $db->query("SELECT COUNT(*) FROM usuarios");
            if ($stmt->fetchColumn() === 0) {
                // Create default admin user (password: admin123)
                $user = new \App\Models\User();
                $user->nombre = 'Admin';
                $user->apellido = 'Sistema';
                $user->usuario = 'admin';
                $user->contrasena = 'admin123'; // Temporary plain text
                $user->rol = 'administrador';
                $user->role_id = 1;
                $user->estado = 'activo';
                $user->save();
            }
            
        } catch (PDOException $e) {
            // Tables might not exist yet
            error_log("Database initialization error: " . $e->getMessage());
        }
    }
}
