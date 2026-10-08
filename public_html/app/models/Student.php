<?php
/**
 * Student Model
 * 
 * Represents students in the 'estudiantes' table
 */

namespace App\Models;

class Student extends Model {
    protected static $table = 'estudiantes';
    protected static $primaryKey = 'id';
    
    public $id;
    public $nombre_completo;
    public $codigo_bnb;
    public $id_curso;
    public $telefono_estudiante;
    public $telefono_papa;
    public $telefono_mama;
    public $id_gestion;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get course for this student
     */
    public function getCourse() {
        if (empty($this->id_curso)) {
            return null;
        }
        return Course::find($this->id_curso);
    }
    
    /**
     * Get management (school year) for this student
     */
    public function getManagement() {
        if (empty($this->id_gestion)) {
            return null;
        }
        return Management::find($this->id_gestion);
    }
    
    /**
     * Get all events for this student
     */
    public function getEvents() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM eventos WHERE id_estudiante = ? ORDER BY fecha_evento DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, Event::class);
        } catch (\PDOException $e) {
            error_log("Student getEvents() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get event counts for this student
     */
    public function getEventCounts() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT tipo_evento, COUNT(*) as count FROM eventos WHERE id_estudiante = ? GROUP BY tipo_evento";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            return [
                'atraso' => $results['atraso'] ?? 0,
                'licencia' => $results['licencia'] ?? 0,
                'ausencia' => $results['ausencia'] ?? 0,
            ];
        } catch (\PDOException $e) {
            error_log("Student getEventCounts() error: " . $e->getMessage());
            return ['atraso' => 0, 'licencia' => 0, 'ausencia' => 0];
        }
    }
    
    /**
     * Get messages sent to this student
     */
    public function getMessages() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM mensajes_enviados WHERE id_estudiante = ? ORDER BY fecha_envio DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, Message::class);
        } catch (\PDOException $e) {
            error_log("Student getMessages() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get students by course
     */
    public static function getByCourse($courseId) {
        return static::where('id_curso', '=', $courseId);
    }
    
    /**
     * Get students by management (school year)
     */
    public static function getByManagement($managementId) {
        return static::where('id_gestion', '=', $managementId);
    }
    
    /**
     * Search students by name or BNB code
     */
    public static function search($query) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM estudiantes WHERE nombre_completo LIKE ? OR codigo_bnb LIKE ?";
            $search = '%' . $query . '%';
            $stmt = $db->prepare($sql);
            $stmt->execute([$search, $search]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Student search() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all active students
     */
    public static function getActiveStudents() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT e.* FROM estudiantes e JOIN gestion g ON e.id_gestion = g.id WHERE g.estado = 'activa'";
            $stmt = $db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Student getActiveStudents() error: " . $e->getMessage());
            return [];
        }
    }
}
