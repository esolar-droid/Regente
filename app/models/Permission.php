<?php
namespace App\Models;

class Permission extends Model {
    protected static $table = 'permisos';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $description;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Find permission by name
    public static function findByName($name) {
        return static::where('name', $name);
    }
    
    // Get all permissions
    public static function all() {
        return parent::all();
    }
    
    // Seed default permissions
    public static function seed() {
        $model = new static();
        $defaultPermissions = [
            ['name' => 'view_dashboard', 'description' => 'Ver dashboard'],
            ['name' => 'view_users', 'description' => 'Ver lista de usuarios'],
            ['name' => 'create_user', 'description' => 'Crear usuarios'],
            ['name' => 'edit_user', 'description' => 'Editar usuarios'],
            ['name' => 'delete_user', 'description' => 'Eliminar usuarios'],
            ['name' => 'view_roles', 'description' => 'Ver roles y permisos'],
            ['name' => 'edit_roles', 'description' => 'Editar roles y permisos'],
            ['name' => 'view_students', 'description' => 'Ver lista de estudiantes'],
            ['name' => 'create_student', 'description' => 'Crear estudiantes'],
            ['name' => 'edit_student', 'description' => 'Editar estudiantes'],
            ['name' => 'delete_student', 'description' => 'Eliminar estudiantes'],
            ['name' => 'view_courses', 'description' => 'Ver lista de cursos'],
            ['name' => 'create_course', 'description' => 'Crear cursos'],
            ['name' => 'edit_course', 'description' => 'Editar cursos'],
            ['name' => 'delete_course', 'description' => 'Eliminar cursos'],
            ['name' => 'view_events', 'description' => 'Ver lista de eventos'],
            ['name' => 'create_event', 'description' => 'Crear eventos'],
            ['name' => 'edit_event', 'description' => 'Editar eventos'],
            ['name' => 'delete_event', 'description' => 'Eliminar eventos'],
            ['name' => 'view_messages', 'description' => 'Ver mensajes'],
            ['name' => 'send_messages', 'description' => 'Enviar mensajes'],
            ['name' => 'view_reports', 'description' => 'Ver reportes'],
            ['name' => 'export_students', 'description' => 'Exportar estudiantes'],
            ['name' => 'change_management', 'description' => 'Cambiar gestión escolar'],
        ];
        
        foreach ($defaultPermissions as $perm) {
            $existing = static::where('name', $perm['name']);
            if (!$existing) {
                $permission = new Permission();
                $permission->name = $perm['name'];
                $permission->description = $perm['description'];
                $permission->save();
            }
        }
    }
}
