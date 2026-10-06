<?php
namespace App\Models;

class Management extends Model {
    protected static $table = 'gestion';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $start_date;
    public $end_date;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Get active management
    public static function getActive() {
        return static::where('is_active', 1);
    }
    
    // Set active management
    public static function setActive($managementId) {
        $model = new static();
        
        // Deactivate all
        $stmt = $model->db->prepare("UPDATE gestion SET is_active = 0");
        $stmt->execute();
        
        // Activate selected
        $stmt = $model->db->prepare("UPDATE gestion SET is_active = 1 WHERE id = ?");
        return $stmt->execute([$managementId]);
    }
    
    // Get all managements
    public static function all() {
        return parent::all();
    }
}
