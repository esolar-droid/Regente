<?php
/**
 * Message Model
 * 
 * Represents messages in the 'mensajes_enviados' table
 */

namespace App\Models;

class Message extends Model {
    protected static $table = 'mensajes_enviados';
    protected static $primaryKey = 'id';
    
    public $id;
    public $id_estudiante;
    public $destinatario;
    public $mensaje;
    public $id_usuario_envio;
    public $fecha_envio;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get student for this message
     */
    public function getStudent() {
        if (empty($this->id_estudiante)) {
            return null;
        }
        return Student::find($this->id_estudiante);
    }
    
    /**
     * Get sender (user who sent the message)
     */
    public function getSender() {
        if (empty($this->id_usuario_envio)) {
            return null;
        }
        return User::find($this->id_usuario_envio);
    }
    
    /**
     * Get messages by recipient type
     */
    public static function getByRecipient($recipient) {
        return static::where('destinatario', '=', $recipient);
    }
    
    /**
     * Get messages by student
     */
    public static function getByStudent($studentId) {
        return static::where('id_estudiante', '=', $studentId);
    }
    
    /**
     * Get messages by sender
     */
    public static function getBySender($userId) {
        return static::where('id_usuario_envio', '=', $userId);
    }
    
    /**
     * Get messages by date range
     */
    public static function getByDateRange($startDate, $endDate) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM mensajes_enviados WHERE fecha_envio BETWEEN ? AND ? ORDER BY fecha_envio DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Message getByDateRange() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get today's messages
     */
    public static function getTodaysMessages() {
        $today = date('Y-m-d');
        try {
            $db = \Database::getConnection();
            $sql = "SELECT * FROM mensajes_enviados WHERE DATE(fecha_envio) = ? ORDER BY fecha_envio DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$today]);
            return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
        } catch (\PDOException $e) {
            error_log("Message getTodaysMessages() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get message count by recipient type for a student
     */
    public static function getCountByRecipientForStudent($studentId) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT destinatario, COUNT(*) as count FROM mensajes_enviados WHERE id_estudiante = ? GROUP BY destinatario";
            $stmt = $db->prepare($sql);
            $stmt->execute([$studentId]);
            $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            return [
                'papa' => $results['papa'] ?? 0,
                'mama' => $results['mama'] ?? 0,
            ];
        } catch (\PDOException $e) {
            error_log("Message getCountByRecipientForStudent() error: " . $e->getMessage());
            return ['papa' => 0, 'mama' => 0];
        }
    }
}
