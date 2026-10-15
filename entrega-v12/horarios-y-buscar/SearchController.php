<?php
/**
 * SearchController — página «Buscar» (antes «Cursos y Horarios»)
 *
 * Tres columnas, cada una con una búsqueda diferente sobre la tabla `horarios`.
 * Las tres buscan qué está ocurriendo AHORA MISMO: franjas del día de hoy cuya
 * hora_inicio <= hora actual < hora_fin.
 *
 *   Columna 1  Buscar profesor : caja predictiva (usuarios rol 'profesor') +
 *              botón buscar → en qué curso/asignatura/hora está dictando ahora.
 *   Columna 2  Buscar curso     : selector de curso + buscar → qué asignatura
 *              se dicta ahora en ese curso y con qué profesor.
 *   Columna 3  Buscar asignatura: selector de asignatura + buscar → profesores
 *              y cursos que reciben esa asignatura a esta hora.
 *
 * Nota: si son las 10:30 y un bloque dice hora_fin = 10:30, ese bloque YA
 * terminó (comparación estricta fin > ahora); el siguiente bloque (inicio
 * <= 10:30) es el activo. Esto coincide con lo deseado para la columna 1.
 *
 * Rutas: GET /search  (?tipo=profesor|curso|asignatura&...)
 */

namespace App\Controllers;

use App\Models\Course;
use App\Models\Horario;
use App\Models\Management;
use App\Models\User;
use App\Middleware\CsrfMiddleware;

class SearchController extends Controller {

    /** Día de hoy en el formato ENUM de `horarios` ('Lunes'..'Viernes'). */
    private static $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /** Roles aceptados como "profesor" (compatibilidad si alguien usa 'docente'). */
    private static $teacherRoles = ['profesor', 'docente'];

    public function index() {
        $this->requireAuth();

        $year = (int)(Management::getActive()->anio ?? date('Y'));

        // Hora "actual": el servidor puede estar en UTC; usamos la zona local
        // configurada (si no hay, America/La_Paz como colegio boliviano).
        $tz = function_exists('date_default_timezone_get') ? date_default_timezone_get() : 'UTC';
        if ($tz === 'UTC' || $tz === '') { @date_default_timezone_set('America/La_Paz'); }
        $nowTs  = time();
        $diaHoy = self::$dias[(int)date('w', $nowTs)];      // 0=Domingo..6=Sábado
        $h = (int)date('G', $nowTs); $m = (int)date('i', $nowTs);
        $horaActualStr = sprintf('%02d:%02d', $h, $m);
        $nowSecs = $h * 3600 + $m * 60;

        $tipo = (string)($_GET['tipo'] ?? '');
        $result = null; // array con lo mostrado tras buscar

        if ($tipo === 'profesor' && !empty($_GET['profesor_id'])) {
            $pid = (int)$_GET['profesor_id'];
            $u = User::find($pid);
            if ($u && in_array((string)$u->rol, self::$teacherRoles, true)) {
                $blocks = Horario::nowBlocks($year, $diaHoy, $nowSecs, 'h.id_profesor = ?', [$pid]);
                $totalHoy = $this->countToday($year, $diaHoy, 'h.id_profesor = ?', [$pid]);
                $result = [
                    'titulo' => trim($u->nombre . ' ' . $u->apellido),
                    'blocks' => $blocks,
                    'totalHoy' => $totalHoy,
                ];
            }
        } elseif ($tipo === 'curso' && !empty($_GET['curso_id'])) {
            $cid = (int)$_GET['curso_id'];
            $c = Course::find($cid);
            if ($c) {
                $blocks = Horario::nowBlocks($year, $diaHoy, $nowSecs, 'h.id_curso = ?', [$cid]);
                $totalHoy = $this->countToday($year, $diaHoy, 'h.id_curso = ?', [$cid]);
                $result = [
                    'titulo' => $c->curso,
                    'blocks' => $blocks,
                    'totalHoy' => $totalHoy,
                ];
            }
        } elseif ($tipo === 'asignatura' && !empty($_GET['asignatura'])) {
            $asig = trim((string)$_GET['asignatura']);
            $blocks = Horario::nowBlocks($year, $diaHoy, $nowSecs, 'h.asignatura = ?', [$asig]);
            $totalHoy = $this->countToday($year, $diaHoy, 'h.asignatura = ?', [$asig]);
            $result = [
                'titulo' => $asig,
                'blocks' => $blocks,
                'totalHoy' => $totalHoy,
            ];
        }

        $this->view('search/index', [
            'csrf_token'   => CsrfMiddleware::generateToken(),
            'tipo'         => $tipo,
            'result'       => $result,
            'profesores'   => $this->professors(),
            'courses'      => Course::orderBy('curso', 'ASC'),
            'subjects'     => Horario::distinctSubjects($year),
            'diaHoy'       => $diaHoy,
            'horaActual'   => $horaActualStr,
            'selProfesor'  => isset($_GET['profesor_id']) ? (int)$_GET['profesor_id'] : 0,
            'selCurso'     => isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0,
            'selAsignatura'=> isset($_GET['asignatura']) ? trim((string)$_GET['asignatura']) : '',
        ]);
    }

    /**
     * Usuarios con rol profesor como arrays simples ['id','nombre'],
     * ordenados por nombre. (Se normaliza aquí para que la vista no dependa
     * de si el modelo devuelve objetos o filas.)
     */
    private function professors() {
        $out = [];
        try {
            foreach (User::query("SELECT id, nombre, apellido FROM usuarios WHERE rol IN ('profesor','docente')") as $u) {
                $id  = (int)($u['id'] ?? 0);
                $nom = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
                if ($id && $nom !== '') $out[] = ['id' => $id, 'nombre' => $nom];
            }
            usort($out, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        } catch (\Throwable $e) {
            error_log('Search professors(): ' . $e->getMessage());
        }
        return $out;
    }

    /** Nº de franjas horarias de ESE elemento hoy (para "X de Y bloques"). */
    private function countToday($year, $dia, $where, $params) {
        try {
            $db = \Database::getConnection();
            $st = $db->prepare(
                "SELECT COUNT(*) FROM horarios h
                 WHERE h.anio = ? AND h.dia = ? AND ($where)"
            );
            $st->execute(array_merge([$year, $dia], $params));
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('Search countToday(): ' . $e->getMessage());
            return 0;
        }
    }
}
