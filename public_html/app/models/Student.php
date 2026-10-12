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
    // Campos de estado academico (migracion 001)
    public $fecha_nacimiento;
    public $estado;            // regular|retirado|receso_medico|receso_pedagogico|receso_administrativo|asistencia_virtual
    public $fecha_ini;         // inicio del estado actual
    public $fecha_fin;         // fin del estado actual (NULL = vigente)

    /** Estados validos (deben coincidir con el ENUM de la BD) */
    const STATES = [
        'regular', 'retirado', 'receso_medico', 'receso_pedagogico',
        'receso_administrativo', 'asistencia_virtual',
    ];
    
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

    // ========================================================================
    // Listados paginados/filtrados para la hoja de estudiantes
    // ========================================================================

    /**
     * Filtrar estudiantes con datos del curso. Devuelve ['rows'=>[], 'total'=>int]
     *
     * @param array $f { search, estado, id_curso, page, per_page }
     */
    public static function filter(array $f = []) {
        $page    = max(1, (int)($f['page'] ?? 1));
        $perPage = min(100, max(5, (int)($f['per_page'] ?? 25)));
        $where   = [];
        $params  = [];

        if (!empty($f['search'])) {
            $where[]  = "(e.nombre_completo LIKE ? OR e.codigo_bnb LIKE ?)";
            $s        = '%' . $f['search'] . '%';
            $params[] = $s; $params[] = $s;
        }
        if (!empty($f['estado']) && in_array($f['estado'], self::STATES, true)) {
            $where[]  = "e.estado = ?";
            $params[] = $f['estado'];
        } elseif (($f['estado'] ?? '') === 'activos') {
            $where[] = "e.estado <> 'retirado'";
        }
        if (!empty($f['id_curso'])) {
            $where[]  = "e.id_curso = ?";
            $params[] = (int)$f['id_curso'];
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $db = \Database::getConnection();

            $stmt = $db->prepare("SELECT COUNT(*) FROM estudiantes e $sqlWhere");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT e.*, c.curso AS curso_nombre, c.paralelo AS curso_paralelo
                    FROM estudiantes e
                    LEFT JOIN cursos c ON e.id_curso = c.id
                    $sqlWhere
                    ORDER BY e.nombre_completo ASC
                    LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        } catch (\PDOException $e) {
            error_log("Student filter() error: " . $e->getMessage());
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /** Conteo por estado para los filtros rapidos */
    public static function stateCounts() {
        try {
            $db = \Database::getConnection();
            $stmt = $db->query("SELECT estado, COUNT(*) c FROM estudiantes GROUP BY estado");
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $out[$r['estado']] = (int)$r['c'];
            }
            return $out;
        } catch (\PDOException $e) {
            error_log("Student stateCounts() error: " . $e->getMessage());
            return [];
        }
    }

    /** Estudiantes cuyo cumpleaños es hoy (mes+dia) */
    public static function birthdaysToday() {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT e.*, c.curso AS curso_nombre FROM estudiantes e
                 LEFT JOIN cursos c ON e.id_curso = c.id
                 WHERE e.fecha_nacimiento IS NOT NULL
                   AND DATE_FORMAT(e.fecha_nacimiento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                   AND e.estado <> 'retirado'
                 ORDER BY e.nombre_completo"
            );
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Student birthdaysToday() error: " . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Estados y historial
    // ========================================================================

    /** Historial de estados de un estudiante (mas reciente primero) */
    public static function getStateHistory($studentId) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT h.*, u.nombre, u.apellido
                 FROM estudiante_estados h
                 LEFT JOIN usuarios u ON h.id_usuario = u.id
                 WHERE h.id_estudiante = ?
                 ORDER BY h.fecha_ini DESC, h.id DESC"
            );
            $stmt->execute([(int)$studentId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Student getStateHistory() error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cambiar el estado academico del estudiante manteniendo el historial.
     * Reglas:
     *  - Cierra el asiento vigente (fecha_fin).
     *  - Si el nuevo estado NO es 'regular', marca retirado al cerrar receso/virtual
     *    cuando fecha_fin implica retiro explicito -> handled by caller passing 'retirado'.
     *  - Abre un nuevo asiento vigente.
     */
    public static function changeState($studentId, $newState, $dateIni, $motivo = null, $userId = null) {
        if (!in_array($newState, self::STATES, true)) {
            return false;
        }
        try {
            $db = \Database::getConnection();
            $db->beginTransaction();

            // 1) Cerrar asiento vigente
            $stmt = $db->prepare(
                "UPDATE estudiante_estados SET fecha_fin = ?
                 WHERE id_estudiante = ? AND fecha_fin IS NULL"
            );
            $stmt->execute([$dateIni, (int)$studentId]);

            // 2) Insertar nuevo asiento
            $stmt = $db->prepare(
                "INSERT INTO estudiante_estados (id_estudiante, estado, fecha_ini, fecha_fin, motivo, id_usuario)
                 VALUES (?, ?, ?, NULL, ?, ?)"
            );
            $stmt->execute([(int)$studentId, $newState, $dateIni, $motivo, $userId]);

            // 3) Actualizar campos denormalizados en estudiantes
            $stmt = $db->prepare(
                "UPDATE estudiantes SET estado = ?, fecha_ini = ?, fecha_fin = NULL WHERE id = ?"
            );
            $stmt->execute([$newState, $dateIni, (int)$studentId]);

            $db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Student changeState() error: " . $e->getMessage());
            return false;
        }
    }
}

