<?php
/**
 * Role Model
 * 
 * Represents roles in the 'roles' table
 */

namespace App\Models;

class Role extends Model {
    protected static $table = 'roles';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $description;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get role by name
     */
    public static function findByName($name) {
        return static::firstWhere('name', '=', $name);
    }
    
    /**
     * Get all permissions for this role
     */
    public function getPermissions() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT p.* FROM role_permissions rp 
                    JOIN permisos p ON rp.permission_id = p.id 
                    WHERE rp.role_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, Permission::class);
        } catch (\PDOException $e) {
            error_log("Role getPermissions() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if role has a specific permission
     */
    public function hasPermission($permissionName) {
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if ($permission->name === $permissionName) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Add permission to role
     */
    public function addPermission($permissionId) {
        try {
            $db = \Database::getConnection();
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            return $stmt->execute([$this->id, $permissionId]);
        } catch (\PDOException $e) {
            error_log("Role addPermission() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission($permissionId) {
        try {
            $db = \Database::getConnection();
            $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
            $stmt = $db->prepare($sql);
            return $stmt->execute([$this->id, $permissionId]);
        } catch (\PDOException $e) {
            error_log("Role removePermission() error: " . $e->getMessage());
            return false;
        }
    }
}
