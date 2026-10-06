<?php
namespace App\Models;

class Student extends Model {
    protected static $table = 'estudiantes';
    protected static $primaryKey = 'id';
    
    public $id;
    public $name;
    public $last_name;
    public $birth_date;
    public $gender;
    public $address;
    public $phone;
    public $email;
    public $course_id;
    public $registration_date;
    public $status;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        parent::__construct();
    }
    
    // Get student's course
    public function course() {
        return Course::find($this->course_id);
    }
    
    // Get all students with their courses
    public static function allWithCourses() {
        $model = new static();
        $sql = "SELECT e.*, c.name as course_name FROM estudiantes e LEFT JOIN cursos c ON e.course_id = c.id";
        return $model->query($sql);
    }
    
    // Get students by course
    public static function getByCourse($courseId) {
        return static::whereAll('course_id', $courseId);
    }
    
    // Search students by name
    public static function search($name) {
        $model = new static();
        $sql = "SELECT e.*, c.name as course_name FROM estudiantes e LEFT JOIN cursos c ON e.course_id = c.id WHERE e.name LIKE ? OR e.last_name LIKE ?";
        $searchTerm = "%{$name}%";
        return $model->query($sql, [$searchTerm, $searchTerm]);
    }
}
