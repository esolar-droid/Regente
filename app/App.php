<?php
// Main Application Class

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
        // Check if tables exist
        $db = \Database::getConnection();
        
        try {
            // Check if usuarios table exists
            $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
            if ($stmt->rowCount() === 0) {
                // Tables don't exist, we might need to create them
                // But for now, we'll just log it
                error_log("Database tables not found. Please ensure the database is properly set up.");
            }
            
            // Seed permissions if empty
            $stmt = $db->query("SELECT COUNT(*) FROM permisos");
            if ($stmt->fetchColumn() === 0) {
                \App\Models\Permission::seed();
            }
            
            // Check if there's at least one user (admin)
            $stmt = $db->query("SELECT COUNT(*) FROM usuarios");
            if ($stmt->fetchColumn() === 0) {
                // Create default admin user (password: admin123)
                $user = new \App\Models\User();
                $user->username = 'admin';
                $user->name = 'Administrador';
                $user->email = 'admin@colmarista.com';
                $user->setPassword('admin123');
                $user->role_id = 1; // Assuming admin role has ID 1
                $user->save();
            }
            
        } catch (PDOException $e) {
            // Tables might not exist yet
            error_log("Database initialization error: " . $e->getMessage());
        }
    }
}
