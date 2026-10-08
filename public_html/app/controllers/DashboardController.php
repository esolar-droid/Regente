<?php
/**
 * Dashboard Controller
 * 
 * Handles the main dashboard view
 */

namespace App\Controllers;

use App\Models\Student;
use App\Models\Course;
use App\Models\Event;
use App\Models\User;
use App\Models\Management;

class DashboardController extends Controller {
    
    public function index() {
        $this->requireAuth();
        
        $user = $this->user();
        
        // Get statistics
        $stats = [];
        
        // Only admins and regentes can see all stats
        if (isAdmin() || isRegente()) {
            $stats['total_students'] = count(Student::all());
            $stats['total_courses'] = count(Course::all());
            $stats['total_users'] = count(User::all());
            
            // Get event statistics
            $eventStats = Event::getStatistics();
            $stats['total_events'] = $eventStats['total'];
            $stats['total_atrasos'] = $eventStats['atraso'];
            $stats['total_licencias'] = $eventStats['licencia'];
            $stats['total_ausencias'] = $eventStats['ausencia'];
            
            // Get active management
            $activeManagement = Management::getActive();
            $stats['active_management'] = $activeManagement ? $activeManagement->anio : 'Ninguna';
        } else {
            // Professors see limited stats
            $stats['total_courses'] = count(Course::all());
        }
        
        // Get recent events (limit 10)
        $recentEvents = Event::query(
            "SELECT e.*, s.nombre_completo as student_name, s.codigo_bnb as student_code, 
                    c.curso as course_code
             FROM eventos e
             JOIN estudiantes s ON e.id_estudiante = s.id
             JOIN cursos c ON s.id_curso = c.id
             ORDER BY e.fecha_registro DESC
             LIMIT 10"
        );
        
        // Get recent messages (limit 10)
        $recentMessages = [];
        if (can('view_messages')) {
            $recentMessages = \App\Models\Message::query(
                "SELECT m.*, s.nombre_completo as student_name, s.codigo_bnb as student_code,
                        u.nombre as sender_name, u.apellido as sender_lastname
                 FROM mensajes_enviados m
                 JOIN estudiantes s ON m.id_estudiante = s.id
                 JOIN usuarios u ON m.id_usuario_envio = u.id
                 ORDER BY m.fecha_envio DESC
                 LIMIT 10"
            );
        }
        
        $this->view('dashboard/index', [
            'stats' => $stats,
            'recentEvents' => $recentEvents,
            'recentMessages' => $recentMessages,
            'user' => $user,
        ]);
    }
}
