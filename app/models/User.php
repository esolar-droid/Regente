<?php
namespace App\Models;

class User extends Model {
    protected static $table = 'usuarios';
    protected static $primaryKey = 'id';
    
    public $id;
    public $username;
    public $password;
    public $email;
    public $name;
    public $role_id;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Find user by username
    public static function findByUsername($username) {
        return static::where('username', $username);
    }
    
    // Find user by email
    public static function findByEmail($email) {
        return static::where('email', $email);
    }
    
    // Get user's role
    public function role() {
        return Role::find($this->role_id);
    }
    
    // Check if user has a specific permission
    public function hasPermission($permissionName) {
        $role = $this->role();
        if (!$role) {
            return false;
        }
        
        $permission = Permission::where('name', $permissionName);
        if (!$permission) {
            return false;
        }
        
        // Check if role has permission
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM role_permissions 
             WHERE role_id = ? AND permission_id = ?"
        );
        $stmt->execute([$role->id, $permission->id]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Hash password
    public function setPassword($password) {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }
    
    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
    
    // Get all users with their roles
    public static function allWithRoles() {
        $model = new static();
        $sql = "SELECT u.*, r.name as role_name FROM usuarios u LEFT JOIN roles r ON u.role_id = r.id";
        return $model->query($sql);
    }
}
