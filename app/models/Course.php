<?php
namespace App\Models;

class Course extends Model {
    protected static $table = 'cursos';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $description;
    public $level;
    public $teacher;
    public $schedule;
    public $classroom;
    public $status;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Get all students in this course
    public function students() {
        return Student::getByCourse($this->id);
    }
    
    // Get all courses with student count
    public static function allWithStudentCount() {
        $model = new static();
        $sql = "SELECT c.*, COUNT(e.id) as student_count 
                FROM cursos c 
                LEFT JOIN estudiantes e ON c.id = e.course_id 
                GROUP BY c.id";
        return $model->query($sql);
    }
}
