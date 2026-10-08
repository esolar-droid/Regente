<?php
/**
 * Course Model
 * 
 * Represents courses in the 'cursos' table
 */

namespace App\Models;

class Course extends Model {
    protected static $table = 'cursos';
    protected static $primaryKey = 'id';
    
    public $id;
    public $nivel;
    public $grado;
    public $paralelo;
    public $curso;
    
    public function __construct($data = []) {
        parent::__construct($data);
    }
    
    /**
     * Get display name for the course
     */
    public function getDisplayName() {
        return "{$this->nivel} - Grado {$this->grado} Paralelo {$this->paralelo} ({$this->curso})";
    }
    
    /**
     * Get short name for the course
     */
    public function getShortName() {
        return "{$this->nivel} {$this->grado}{$this->paralelo}";
    }
    
    /**
     * Get all students in this course
     */
    public function getStudents() {
        return Student::where('id_curso', '=', $this->id);
    }
    
    /**
     * Get student count for this course
     */
    public function getStudentCount() {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT COUNT(*) as count FROM estudiantes WHERE id_curso = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$this->id]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (\PDOException $e) {
            error_log("Course getStudentCount() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get courses by level
     */
    public static function getByLevel($level) {
        return static::where('nivel', '=', $level);
    }
    
    /**
     * Get courses by grade
     */
    public static function getByGrade($grade) {
        return static::where('grado', '=', $grade);
    }
    
    /**
     * Get all primary courses
     */
    public static function getPrimaryCourses() {
        return static::where('nivel', '=', 'Primaria');
    }
    
    /**
     * Get all secondary courses
     */
    public static function getSecondaryCourses() {
        return static::where('nivel', '=', 'Secundaria');
    }
    
    /**
     * Get course by full identifier (e.g., P1A, S2B)
     */
    public static function findByIdentifier($identifier) {
        return static::firstWhere('curso', '=', $identifier);
    }
}
