<?php
/**
 * Horario Model — tabla `horarios` (migración 003)
 *
 * Curso, día (L-V), periodo (rango de horas), asignatura, profesor.
 * Por ahora se usa para saber EN CUÁLES CURSOS DICTA CLASES un profesor
 * (filtrado de la hoja de mensajería). La futura página «Cursos y
 * horarios» y su edición desde Administración reutilizarán este modelo.
 */

namespace App\Models;

class Horario extends Model {
    protected static $table = 'horarios';
    protected static $primaryKey = 'id';

    public $id;
    public $id_curso;
    public $id_profesor;
    public $dia;
    public $hora_inicio;
    public $hora_fin;
    public $asignatura;
    public $anio;

    /**
     * IDs de los cursos donde el profesor tiene horas en el año dado.
     * Si la tabla `horarios` está vacía para ese profesor, devuelve []:
     * quien llame decidirá el fallback (p. ej. curso de sus estudiantes).
     */
    public static function courseIdsForProfessor($userId, $year = null) {
        $year = $year ?: (int)date('Y');
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT DISTINCT id_curso FROM horarios WHERE id_profesor = ? AND anio = ?"
            );
            $stmt->execute([(int)$userId, (int)$year]);
            return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            error_log('Horario courseIdsForProfessor(): ' . $e->getMessage());
            return [];
        }
    }

    /** Cursos (filas de `cursos`) donde dicta el profesor, ordenados por nombre. */
    public static function coursesForProfessor($userId, $year = null) {
        $ids = self::courseIdsForProfessor($userId, $year);
        if (!$ids) return [];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("SELECT * FROM cursos WHERE id IN ($ph) ORDER BY curso");
            $stmt->execute($ids);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Horario coursesForProfessor(): ' . $e->getMessage());
            return [];
        }
    }
}
