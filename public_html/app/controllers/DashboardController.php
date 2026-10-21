<?php
/**
 * Dashboard Controller — v17 rediseño tipo panel de control
 *
 * Filas: 1) reloj + calendario Google | 2) accesos rapidos |
 * 3) graficos A/L/F por curso (P n vs S n) | 4) barras acumuladas |
 * 5) rankings de eventos top-20.
 */

namespace App\Controllers;

use App\Models\Student;
use App\Models\Course;
use App\Models\Event;
use App\Models\User;
use App\Models\Management;
use App\Models\Report;

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
            $stats['total_faltas'] = $eventStats['falta'];

            // Get active management
            $activeManagement = Management::getActive();
            $stats['active_management'] = $activeManagement ? $activeManagement->anio : 'Ninguna';
        } else {
            // Professors see limited stats
            $stats['total_courses'] = count(Course::all());
        }

        // ============================================================
        // v17 · Datos para el nuevo dashboard (regla: solo gestion activa)
        // ============================================================
        $dash = ['chartLabels' => [], 'primaria' => [], 'secundaria' => [],
                 'barsPrimaria' => null, 'barsSecundaria' => null,
                 'rank' => [], 'hasData' => false];
        if (isAdmin() || isRegente()) {
            $mg = Report::activeManagement();          // ['id'=>int|null,'anio'=>int]
            $mid = $mg['id'];

            // Pares de cursos alineados por grado+paralelo: P1A <-> S1A ... P6C <-> S6C
            $coursesP = Report::courses('Primaria');   // ORDER BY grado, paralelo
            $coursesS = Report::courses('Secundaria');
            // Clave canonica: primer digito del grado + letra del paralelo.
            // Tolerante a formatos '1ro'/'1°'/1 => coincide igual en ambos niveles.
            $keyOf = function ($c) {
                $g = preg_replace('/\D/', '', (string)($c['grado'] ?? ''));      // digitos del grado
                $p = strtoupper(substr(preg_replace('/\s+/', '', (string)($c['paralelo'] ?? '')), 0, 1));
                return $g . '-' . $p;
            };
            $mapS = [];
            foreach ($coursesS as $c) $mapS[$keyOf($c)] = $c;
            $pairs = [];
            foreach ($coursesP as $cp) {
                $cs = $mapS[$keyOf($cp)] ?? null;
                $pairs[] = ['p' => $cp, 's' => $cs];
            }

            $totals = Report::courseEventTotals($mid); // [id_curso] => ['A','L','F']
            $cTot = fn ($cid, $k) => $cid ? (($totals[(int)$cid][$k] ?? 0)) : 0;

            foreach ($pairs as $pr) {
                $lbl = htmlspecialchars((string)($pr['p']['curso'] ?? '?'), ENT_QUOTES, 'UTF-8');
                $dash['chartLabels'][] = $lbl;
                $dash['primaria']['A'][]  = $cTot($pr['p']['id'] ?? null, 'A');
                $dash['primaria']['L'][]  = $cTot($pr['p']['id'] ?? null, 'L');
                $dash['primaria']['F'][]  = $cTot($pr['p']['id'] ?? null, 'F');
                $dash['secundaria']['A'][] = $cTot($pr['s']['id'] ?? null, 'A');
                $dash['secundaria']['L'][] = $cTot($pr['s']['id'] ?? null, 'L');
                $dash['secundaria']['F'][] = $cTot($pr['s']['id'] ?? null, 'F');
                if (!empty($pr['p']['curso'])) $dash['hasData'] = true;
            }

            // Fila 4: barras acumuladas A/L/F por curso (un grafico por nivel)
            foreach (['Primaria', 'Secundaria'] as $nivel) {
                $labels = $a = $l = $f = [];
                foreach (Report::courses($nivel) as $c) {
                    $labels[] = (string)$c['curso'];
                    $t = $totals[(int)$c['id']] ?? ['A' => 0, 'L' => 0, 'F' => 0];
                    $a[] = $t['A']; $l[] = $t['L']; $f[] = $t['F'];
                }
                $block = ['labels' => $labels, 'A' => $a, 'L' => $l, 'F' => $f];
                if ($nivel === 'Primaria') $dash['barsPrimaria'] = $block;
                else $dash['barsSecundaria'] = $block;
            }

            // Fila 5: rankings top-20 (nombre, curso, cantidad) por nivel y tipo
            foreach (['Primaria', 'Secundaria'] as $nivel) {
                foreach (['atraso', 'licencia', 'falta'] as $tipo) {
                    $dash['rank'][$nivel][$tipo] = Report::studentRankingByLevel($tipo, $nivel, 20, $mid);
                }
            }
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
            'dash' => $dash,
            'recentEvents' => $recentEvents,
            'recentMessages' => $recentMessages,
            'user' => $user,
        ]);
    }
}
