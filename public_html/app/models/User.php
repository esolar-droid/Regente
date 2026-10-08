<?php
/**
 * User Model
 * 
 * Represents users in the 'usuarios' table
 */

namespace App\Models;

class User extends Model {
    protected static $table = 'usuarios';
    protected static $primaryKey = 'id';
    
    public $id;
    public $nombre;
    public $apellido;
    public $usuario;
    public $contrasena;
    public $rol;
    public $role_id;
    public $estado;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Verify user password (temporary: plain text comparison)
     * Will be migrated to bcrypt later
     */
    public function verifyPassword($password) {
        // Temporary: plain text comparison
        // TODO: Migrate to bcrypt when ready
        return $this->contrasena === $password;
    }
    
    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permissionName) {
        if ($this->role_id === null) {
            return false;
        }
        
        try {
            $db = \Database::getConnection();
            $sql = "SELECT COUNT(*) as count FROM role_permissions rp 
                    JOIN permisos p ON rp.permission_id = p.id 
                    WHERE rp.role_id = ? AND p.name = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->role_id, $permissionName]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (\PDOException $e) {
            error_log("User hasPermission() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all permissions for this user
     */
    public function getPermissions() {
        if ($this->role_id === null) {
            return [];
        }
        
        try {
            $db = \Database::getConnection();
            $sql = "SELECT p.name as permission FROM role_permissions rp 
                    JOIN permisos p ON rp.permission_id = p.id 
                    WHERE rp.role_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->role_id]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            error_log("User getPermissions() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user by username
     */
    public static function findByUsername($username) {
        return static::firstWhere('usuario', '=', $username);
    }
    
    /**
     * Get all active users
     */
    public static function getActiveUsers() {
        return static::where('estado', '=', 'activo');
    }
    
    /**
     * Get users by role
     */
    public static function getByRole($role) {
        return static::where('rol', '=', $role);
    }
}
