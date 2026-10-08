<?php
/**
 * Permission Model
 * 
 * Represents permissions in the 'permisos' table
 */

namespace App\Models;

class Permission extends Model {
    protected static $table = 'permisos';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $description;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Seed default permissions
     */
    public static function seed() {
        try {
            $db = \Database::getConnection();
            
            // Check if permissions already exist
            $stmt = $db->query("SELECT COUNT(*) FROM permisos");
            if ($stmt->fetchColumn() > 0) {
                return; // Already seeded
            }
            
            // Create roles first
            $roles = [
                ['name' => 'administrador', 'description' => 'Administrador del sistema con todos los permisos'],
                ['name' => 'regente', 'description' => 'Regente con acceso a gestión de estudiantes y eventos'],
                ['name' => 'profesor', 'description' => 'Profesor con acceso limitado'],
            ];
            
            foreach ($roles as $role) {
                $db->exec("INSERT IGNORE INTO roles (name, description) VALUES ('{$role['name']}', '{$role['description']}')");
            }
            
            // Default permissions
            $permissions = [
                // Dashboard
                ['name' => 'view_dashboard', 'description' => 'Ver dashboard'],
                
                // Users
                ['name' => 'view_users', 'description' => 'Ver lista de usuarios'],
                ['name' => 'create_user', 'description' => 'Crear usuarios'],
                ['name' => 'edit_user', 'description' => 'Editar usuarios'],
                ['name' => 'delete_user', 'description' => 'Eliminar usuarios'],
                
                // Students
                ['name' => 'view_students', 'description' => 'Ver lista de estudiantes'],
                ['name' => 'create_student', 'description' => 'Crear estudiantes'],
                ['name' => 'edit_student', 'description' => 'Editar estudiantes'],
                ['name' => 'delete_student', 'description' => 'Eliminar estudiantes'],
                
                // Courses
                ['name' => 'view_courses', 'description' => 'Ver lista de cursos'],
                ['name' => 'create_course', 'description' => 'Crear cursos'],
                ['name' => 'edit_course', 'description' => 'Editar cursos'],
                ['name' => 'delete_course', 'description' => 'Eliminar cursos'],
                
                // Events
                ['name' => 'view_events', 'description' => 'Ver lista de eventos'],
                ['name' => 'create_event', 'description' => 'Crear eventos'],
                ['name' => 'edit_event', 'description' => 'Editar eventos'],
                ['name' => 'delete_event', 'description' => 'Eliminar eventos'],
                
                // Messages
                ['name' => 'view_messages', 'description' => 'Ver mensajes'],
                ['name' => 'create_message', 'description' => 'Crear mensajes'],
                ['name' => 'send_whatsapp', 'description' => 'Enviar mensajes por WhatsApp'],
                
                // Reports
                ['name' => 'view_reports', 'description' => 'Ver reportes'],
                ['name' => 'export_csv', 'description' => 'Exportar a CSV'],
                ['name' => 'export_pdf', 'description' => 'Exportar a PDF'],
                
                // Management
                ['name' => 'view_management', 'description' => 'Ver gestión'],
                ['name' => 'change_management', 'description' => 'Cambiar gestión'],
            ];
            
            foreach ($permissions as $permission) {
                $db->exec("INSERT IGNORE INTO permisos (name, description) VALUES ('{$permission['name']}', '{$permission['description']}')");
            }
            
            // Assign permissions to roles
            // Administrator: all permissions
            $adminPermissions = $db->query("SELECT id FROM permisos")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($adminPermissions as $permissionId) {
                $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, $permissionId)");
            }
            
            // Regente: academic management permissions
            $regentePermissions = [
                'view_dashboard', 'view_students', 'create_student', 'edit_student', 'delete_student',
                'view_courses', 'view_events', 'create_event', 'edit_event', 'delete_event',
                'view_messages', 'create_message', 'send_whatsapp', 'view_reports',
                'export_csv', 'export_pdf', 'view_management', 'change_management'
            ];
            foreach ($regentePermissions as $permName) {
                $permId = $db->query("SELECT id FROM permisos WHERE name = '$permName'")->fetchColumn();
                if ($permId) {
                    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (2, $permId)");
                }
            }
            
            // Profesor: limited permissions
            $profesorPermissions = [
                'view_dashboard', 'view_students', 'view_courses',
                'view_events', 'view_messages', 'view_reports'
            ];
            foreach ($profesorPermissions as $permName) {
                $permId = $db->query("SELECT id FROM permisos WHERE name = '$permName'")->fetchColumn();
                if ($permId) {
                    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (3, $permId)");
                }
            }
            
        } catch (\PDOException $e) {
            error_log("Permission seed() error: " . $e->getMessage());
        }
    }
    
    /**
     * Get permission by name
     */
    public static function findByName($name) {
        return static::firstWhere('name', '=', $name);
    }
}
