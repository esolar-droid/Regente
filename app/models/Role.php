<?php
namespace App\Models;

class Role extends Model {
    protected static $table = 'roles';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $description;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Get all permissions for this role
    public function permissions() {
        $sql = "SELECT p.* FROM permissions p 
                JOIN role_permissions rp ON p.id = rp.permission_id 
                WHERE rp.role_id = ?";
        return $this->query($sql, [$this->id]);
    }
    
    // Check if role has a permission
    public function hasPermission($permissionName) {
        $permission = Permission::where('name', $permissionName);
        if (!$permission) {
            return false;
        }
        
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM role_permissions 
             WHERE role_id = ? AND permission_id = ?"
        );
        $stmt->execute([$this->id, $permission->id]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Add permission to role
    public function addPermission($permissionId) {
        $stmt = $this->db->prepare(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)"
        );
        return $stmt->execute([$this->id, $permissionId]);
    }
    
    // Remove permission from role
    public function removePermission($permissionId) {
        $stmt = $this->db->prepare(
            "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?"
        );
        return $stmt->execute([$this->id, $permissionId]);
    }
    
    // Get all roles with their permissions
    public static function allWithPermissions() {
        $model = new static();
        $sql = "SELECT r.*, GROUP_CONCAT(p.name SEPARATOR ', ') as permissions 
                FROM roles r 
                LEFT JOIN role_permissions rp ON r.id = rp.role_id 
                LEFT JOIN permissions p ON rp.permission_id = p.id 
                GROUP BY r.id";
        return $model->query($sql);
    }
}
