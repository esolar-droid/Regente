<?php
/**
 * Bitacora Model — v17 «Bitácora del profesorado»
 *
 * Registros de actividad/novedad asociados a un PROFESOR (usuario con rol
 * profesor/docente). A diferencia de la asistencia de estudiantes, NO se
 * rige por el calendario académico ni por feriados: cualquier día es válido.
 *
 * Convención pactada: si al registrar no se especifica fecha, se asume por
 * defecto la fecha actual (hoy) — ver normalizeDate().
 */

namespace App\Models;

class Bitacora extends Model {
    protected static $table = 'bitacora';
    protected static $primaryKey = 'id';

    public $id;
    public $id_profesor;
    public $tipo;
    public $titulo;
    public $detalle;
    public $fecha;
    public $id_usuario_registro;
    public $created_at;
    public $updated_at;

    /** Tipos válidos (coinciden con el ENUM de la migración 005). */
    const TIPOS = [
        'actividad'    => 'Actividad',
        'novedad'      => 'Novedad',
        'reunion'      => 'Reunión',
        'capacitacion' => 'Capacitación',
        'otro'         => 'Otro',
    ];

    public static function isValidTipo($t) {
        return isset(self::TIPOS[(string)$t]);
    }

    /**
     * Regla de negocio: fecha vacía/inválida => hoy.
     * Estos registros NO están restringidos por el calendario académico.
     */
    public static function normalizeDate($date) {
        $date = trim((string)$date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    }

    /**
     * Listado con filtros combinables:
     *   q      -> búsqueda LIBRE por nombre del profesor Y detalle/título del evento
     *   desde, hasta -> rango de fechas (opcional)
     *   tipo   -> filtro por tipo (opcional)
     *   id_profesor -> filtro por profesor exacto (opcional)
     * Orden: fecha DESC (más reciente primero), luego id DESC.
     */
    public static function filter(array $f) {
        $where  = [];
        $params = [];

        if (!empty($f['q'])) {
            // Búsqueda en texto libre sobre: nombre completo del profesor,
            // título y detalle del registro.
            $like = '%' . str_replace(['%','_'], ['\%','\_'], trim((string)$f['q'])) . '%';
            $where[] = "(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) LIKE ?
                         OR b.titulo LIKE ? OR b.detalle LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['desde'])) {
            $where[] = 'b.fecha >= ?'; $params[] = $f['desde'];
        }
        if (!empty($f['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['hasta'])) {
            $where[] = 'b.fecha <= ?'; $params[] = $f['hasta'];
        }
        if (!empty($f['tipo']) && self::isValidTipo($f['tipo'])) {
            $where[] = 'b.tipo = ?'; $params[] = $f['tipo'];
        }
        if (!empty($f['id_profesor'])) {
            $where[] = 'b.id_profesor = ?'; $params[] = (int)$f['id_profesor'];
        }

        $sql = "SELECT b.*,
                       CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) AS profesor_nombre,
                       CONCAT(COALESCE(ru.nombre,''), ' ', COALESCE(ru.apellido,'')) AS registra_nombre
                FROM bitacora b
                LEFT JOIN usuarios u  ON u.id  = b.id_profesor
                LEFT JOIN usuarios ru ON ru.id = b.id_usuario_registro
                " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') .
                " ORDER BY b.fecha DESC, b.id DESC";

        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Bitacora filter(): ' . $e->getMessage());
            return [];
        }
    }

    /** Conteo rápido para estadísticas de la página. */
    public static function countAll() {
        try {
            $db = \Database::getConnection();
            $stmt = $db->query("SELECT COUNT(*) n FROM bitacora");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($row['n'] ?? 0);
        } catch (\Throwable $e) { return 0; }
    }

    public static function countBetween($from, $to) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) n FROM bitacora WHERE fecha BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($row['n'] ?? 0);
        } catch (\Throwable $e) { return 0; }
    }

    /** Inserta un registro; devuelve el id o 0 en fallo. */
    public static function create(array $d) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO bitacora (id_profesor, tipo, titulo, detalle, fecha, id_usuario_registro)
                 VALUES (?,?,?,?,?,?)"
            );
            $ok = $stmt->execute([
                (int)$d['id_profesor'],
                $d['tipo'] ?? 'actividad',
                $d['titulo'] ?? '',
                ($d['detalle'] ?? '') !== '' ? $d['detalle'] : null,
                self::normalizeDate($d['fecha'] ?? ''),
                isset($d['id_usuario_registro']) ? (int)$d['id_usuario_registro'] : null,
            ]);
            return $ok ? (int)$db->lastInsertId() : 0;
        } catch (\Throwable $e) {
            error_log('Bitacora create(): ' . $e->getMessage());
            return 0;
        }
    }

    /** Actualiza un registro existente. */
    public static function change($id, array $d) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "UPDATE bitacora SET id_profesor=?, tipo=?, titulo=?, detalle=?, fecha=? WHERE id=?"
            );
            return $stmt->execute([
                (int)$d['id_profesor'],
                $d['tipo'] ?? 'actividad',
                $d['titulo'] ?? '',
                ($d['detalle'] ?? '') !== '' ? $d['detalle'] : null,
                self::normalizeDate($d['fecha'] ?? ''),
                (int)$id,
            ]);
        } catch (\Throwable $e) {
            error_log('Bitacora change(): ' . $e->getMessage());
            return false;
        }
    }

    public static function remove($id) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("DELETE FROM bitacora WHERE id = ?");
            return $stmt->execute([(int)$id]);
        } catch (\Throwable $e) {
            error_log('Bitacora remove(): ' . $e->getMessage());
            return false;
        }
    }
}
