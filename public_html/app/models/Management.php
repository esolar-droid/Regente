<?php
/**
 * Management Model
 * 
 * Represents school years in the 'gestion' table
 */

namespace App\Models;

class Management extends Model {
    protected static $table = 'gestion';
    protected static $primaryKey = 'id';
    
    public $id;
    public $anio;
    public $estado;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get active management (school year)
     */
    public static function getActive() {
        return static::firstWhere('estado', '=', 'activa');
    }
    
    /**
     * Get all management periods
     */
    public static function getAll() {
        return static::all();
    }
    
    /**
     * Set this management as active
     */
    public function activate() {
        try {
            $db = \Database::getConnection();
            
            // Deactivate all other managements
            $db->exec("UPDATE gestion SET estado = 'receso'");
            
            // Activate this one
            $this->estado = 'activa';
            return $this->save();
        } catch (\PDOException $e) {
            error_log("Management activate() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student count for this management
     */
    public function getStudentCount() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT COUNT(*) as count FROM estudiantes WHERE id_gestion = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (\PDOException $e) {
            error_log("Management getStudentCount() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get event count for this management
     */
    public function getEventCount() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT COUNT(*) as count FROM eventos e 
                    JOIN estudiantes s ON e.id_estudiante = s.id 
                    WHERE s.id_gestion = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (\PDOException $e) {
            error_log("Management getEventCount() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get message count for this management
     */
    public function getMessageCount() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT COUNT(*) as count FROM mensajes_enviados m 
                    JOIN estudiantes s ON m.id_estudiante = s.id 
                    WHERE s.id_gestion = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (\PDOException $e) {
            error_log("Management getMessageCount() error: " . $e->getMessage());
            return 0;
        }
    }
}
