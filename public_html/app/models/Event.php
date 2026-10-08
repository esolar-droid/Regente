<?php
/**
 * Event Model
 * 
 * Represents events in the 'eventos' table
 */

namespace App\Models;

class Event extends Model {
    protected static $table = 'eventos';
    protected static $primaryKey = 'id';
    
    public $id;
    public $id_estudiante;
    public $tipo_evento;
    public $fecha_evento;
    public $justificacion;
    public $id_usuario_registro;
    public $fecha_registro;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get student for this event
     */
    public function getStudent() {
        if (empty($this->id_estudiante)) {
            return null;
        }
        return Student::find($this->id_estudiante);
    }
    
    /**
     * Get user who registered this event
     */
    public function getUser() {
        if (empty($this->id_usuario_registro)) {
            return null;
        }
        return User::find($this->id_usuario_registro);
    }
    
    /**
     * Get events by type
     */
    public static function getByType($type) {
        return static::where('tipo_evento', '=', $type);
    }
    
    /**
     * Get events by date
     */
    public static function getByDate($date) {
        return static::where('fecha_evento', '=', $date);
    }
    
    /**
     * Get events by date range
     */
    public static function getByDateRange($startDate, $endDate) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM eventos WHERE fecha_evento BETWEEN ? AND ? ORDER BY fecha_evento DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Event getByDateRange() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get events by student and date range
     */
    public static function getByStudentAndDateRange($studentId, $startDate, $endDate) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM eventos WHERE id_estudiante = ? AND fecha_evento BETWEEN ? AND ? ORDER BY fecha_evento DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$studentId, $startDate, $endDate]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Event getByStudentAndDateRange() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get today's events
     */
    public static function getTodaysEvents() {
        $today = date('Y-m-d');
        return static::where('fecha_evento', '=', $today);
    }
    
    /**
     * Get events by user (registrant)
     */
    public static function getByUser($userId) {
        return static::where('id_usuario_registro', '=', $userId);
    }
    
    /**
     * Get event statistics
     */
    public static function getStatistics($managementId = null) {
        try {
            $db = \Database::getConnection();
            
            $where = '';
            $params = [];
            
            if ($managementId !== null) {
                $where = "JOIN estudiantes s ON e.id_estudiante = s.id WHERE s.id_gestion = ?";
                $params = [$managementId];
            }
            
            $sql = "SELECT tipo_evento, COUNT(*) as count FROM eventos e $where GROUP BY tipo_evento";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            return [
                'atraso' => $results['atraso'] ?? 0,
                'licencia' => $results['licencia'] ?? 0,
                'ausencia' => $results['ausencia'] ?? 0,
                'total' => array_sum($results),
            ];
        } catch (\PDOException $e) {
            error_log("Event getStatistics() error: " . $e->getMessage());
            return ['atraso' => 0, 'licencia' => 0, 'ausencia' => 0, 'total' => 0];
        }
    }
}
