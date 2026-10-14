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
            
            // 'falta' es el valor oficial desde oct-2026; se suman restos
            // legados de 'ausencia' (registros antiguos ya renombrados en BD).
            $faltas = ($results['falta'] ?? 0) + ($results['ausencia'] ?? 0);
            return [
                'atraso'   => $results['atraso'] ?? 0,
                'licencia' => $results['licencia'] ?? 0,
                'falta'    => $faltas,
                'total'    => array_sum($results),
            ];
        } catch (\PDOException $e) {
            error_log("Event getStatistics() error: " . $e->getMessage());
            return ['atraso' => 0, 'licencia' => 0, 'falta' => 0, 'total' => 0];
        }
    }

    // ====================================================================
    // v11 — ASISTENCIA: helpers de grilla masiva y alta individual
    // ====================================================================

    /** Mapa tipo_evento corto ('A','L','F') => valor ENUM físico. */
    public static function shortToType($short) {
        switch (strtoupper((string)$short)) {
            case 'A': return 'atraso';
            case 'L': return 'licencia';
            case 'F': return 'falta';
            default:  return null;
        }
    }

    /** Valor ENUM físico => letra corta para la grilla. */
    public static function typeToShort($tipo) {
        switch (strtolower((string)$tipo)) {
            case 'atraso':   return 'A';
            case 'licencia': return 'L';
            case 'ausencia':
            case 'falta':    return 'F';
            default:         return '';
        }
    }

    /**
     * Eventos de UN día para muchos estudiantes en una sola consulta.
     * Devuelve [id_estudiante => ['tipo'=>'A|L|F','just'=>string]].
     */
    public static function dayMarks(array $studentIds, $date) {
        $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
        if (!$studentIds || !$date) return [];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $db->prepare(
                "SELECT id_estudiante, tipo_evento, justificacion
                 FROM eventos WHERE fecha_evento = ? AND id_estudiante IN ($ph)"
            );
            $stmt->execute(array_merge([$date], $studentIds));
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['id_estudiante']] = [
                    'tipo' => self::typeToShort($r['tipo_evento']),
                    'just' => (string)($r['justificacion'] ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Event dayMarks(): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Inserta o actualiza (upsert) el evento de estudiante+fecha.
     * Si $short es '' / null elimina cualquier evento de ese día (vuelve a P).
     * Sobrescribe sin rastro (decisión del proyecto: auditoría = sobreescribir).
     */
    public static function upsertDay($studentId, $date, $short, $just = '', $userId = null) {
        $studentId = (int)$studentId;
        if (!$studentId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) return false;

        try {
            $db = \Database::getConnection();

            if (!self::shortToType($short)) {
                $stmt = $db->prepare("DELETE FROM eventos WHERE id_estudiante = ? AND fecha_evento = ?");
                $stmt->execute([$studentId, $date]);
                return true;
            }

            $stmt = $db->prepare("SELECT id FROM eventos WHERE id_estudiante = ? AND fecha_evento = ? LIMIT 1");
            $stmt->execute([$studentId, $date]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $u = $db->prepare("UPDATE eventos SET tipo_evento = ?, justificacion = ?, id_usuario_registro = ?, fecha_registro = NOW() WHERE id = ?");
                return $u->execute([self::shortToType($short), $just ?: null, $userId, $row['id']]);
            }
            $i = $db->prepare("INSERT INTO eventos (id_estudiante, tipo_evento, fecha_evento, justificacion, id_usuario_registro, fecha_registro) VALUES (?,?,?,?,?,NOW())");
            return $i->execute([$studentId, self::shortToType($short), $date, $just ?: null, $userId]);
        } catch (\Throwable $e) {
            error_log('Event upsertDay(): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Alta individual con RANGO de fechas: pinta la letra elegida en cada día
     * hábil del rango. Con $preview=true NO escribe: devuelve cuántos días ya
     * tienen registro (para el aviso de confirmación de sobrescritura).
     */
    public static function applyRange($studentId, $start, $end, $short, $just = '', $userId = null, $preview = false) {
        $studentId = (int)$studentId;
        if (!$studentId || !strtotime((string)$start) || !strtotime((string)$end)) {
            return ['ok' => false, 'error' => 'Fechas inválidas', 'total' => 0, 'conflictos' => []];
        }
        if (!self::shortToType($short)) {
            return ['ok' => false, 'error' => 'Tipo inválido', 'total' => 0, 'conflictos' => []];
        }
        $s = date('Y-m-d', strtotime($start));
        $e = date('Y-m-d', strtotime($end));
        if ($s > $e) { [$s, $e] = [$e, $s]; }

        $feriados = Feriado::mapForDates(self::datesBetween($s, $e));
        $conflictos = [];
        $total = 0;
        try {
            $db = \Database::getConnection();
            $chk = $db->prepare("SELECT COUNT(*) FROM eventos WHERE id_estudiante = ? AND fecha_evento BETWEEN ? AND ?");
            $chk->execute([$studentId, $s, $e]);
            $conflictos = [['desde' => $s, 'hasta' => $e, 'n' => (int)$chk->fetchColumn()]];
        } catch (\Throwable $ex) {
            error_log('Event applyRange check(): ' . $ex->getMessage());
        }

        if ($preview) {
            return ['ok' => true, 'preview' => true, 'total' => $total, 'conflictos' => $conflictos, 'error' => null];
        }

        foreach (self::datesBetween($s, $e) as $d) {
            if (isset($feriados[$d])) continue;   // feriado: se salta
            if (self::upsertDay($studentId, $d, $short, $just, $userId)) $total++;
        }
        return ['ok' => true, 'total' => $total, 'conflictos' => [], 'error' => null];
    }

    /** Lista de fechas ISO entre dos fechas (inclusive), máx 370 días. */
    public static function datesBetween($from, $to) {
        $out = [];
        $ts = strtotime($from); $te = strtotime($to);
        if ($ts === false || $te === false || $te < $ts) return $out;
        for ($i = 0; $i <= 370 && $ts + $i * 86400 <= $te; $i++) {
            $out[] = date('Y-m-d', $ts + $i * 86400);
        }
        return $out;
    }

    /** Estudiantes activos de un curso en la gestión activa (nómina). */
    public static function rosterByCourse($courseId) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT e.id, e.nombre_completo, e.codigo_bnb
                 FROM estudiantes e
                 JOIN gestion g ON g.anio = YEAR(CURDATE()) AND g.estado = 'activa'
                 WHERE e.id_curso = ? AND e.id_gestion = g.id AND e.estado <> 'retirado'
                 ORDER BY e.nombre_completo"
            );
            $stmt->execute([(int)$courseId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Event rosterByCourse(): ' . $e->getMessage());
            return [];
        }
    }
}
