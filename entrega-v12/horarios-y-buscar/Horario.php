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

    /**
     * Página «Buscar»: franjas HORARIAS del día de hoy que contienen la hora
     * actual, con todo lo que ocurre en ellas (cursos, asignaturas, profesores).
     * Un mismo bloque (misma hora_inicio/hora_fin) puede tener varias filas:
     * p. ej. dos paralelos a la misma hora o un profesor con grupos espejo.
     *
     * Devuelve una fila por bloque horario coincidente:
     *   { hora_inicio, hora_fin, items:[{id_horario, id_curso, curso, asignatura,
     *     id_profesor, profesor}] }
     *
     * `$where` es una condición SQL opcional sobre las columnas del JOIN
     * (ej. "h.id_profesor = ?"), con `$params` ligados. La comparación de hora
     * usa TIME_TO_SEC para evitar problemas de zona/formato.
     */
    public static function nowBlocks($year, $diaHoy, $nowSecs, $where = '', $params = []) {
        try {
            $db = \Database::getConnection();
            $sql = "SELECT h.id, h.id_curso, h.id_profesor, h.asignatura,
                           h.hora_inicio, h.hora_fin,
                           c.curso, u.nombre, u.apellido
                    FROM horarios h
                    LEFT JOIN cursos c  ON h.id_curso   = c.id
                    LEFT JOIN usuarios u ON h.id_profesor = u.id
                    WHERE h.anio = ? AND h.dia = ?
                      AND TIME_TO_SEC(h.hora_inicio) <= ?
                      AND TIME_TO_SEC(h.hora_fin)   >  ?";
            if ($where !== '') $sql .= " AND ($where)";
            $sql .= " ORDER BY h.hora_inicio, c.curso, h.asignatura";
            $st = $db->prepare($sql);
            $st->execute(array_merge([$year, $diaHoy, $nowSecs, $nowSecs], $params));
            $blocks = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $key = substr($r['hora_inicio'], 0, 5) . '-' . substr($r['hora_fin'], 0, 5);
                if (!isset($blocks[$key])) {
                    $blocks[$key] = [
                        'hora_inicio' => substr($r['hora_inicio'], 0, 5),
                        'hora_fin'    => substr($r['hora_fin'], 0, 5),
                        'items'       => [],
                    ];
                }
                $blocks[$key]['items'][] = [
                    'id_horario'  => (int)$r['id'],
                    'id_curso'    => (int)$r['id_curso'],
                    'curso'       => $r['curso'] ?? '(curso eliminado)',
                    'asignatura'  => $r['asignatura'],
                    'id_profesor' => (int)$r['id_profesor'],
                    'profesor'    => trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
                ];
            }
            ksort($blocks);
            return array_values($blocks);
        } catch (\Throwable $e) {
            error_log('Horario nowBlocks(): ' . $e->getMessage());
            return [];
        }
    }

    /** Distintas asignaturas registradas en `horarios` (para el selector Buscar). */
    public static function distinctSubjects($year) {
        try {
            $db = \Database::getConnection();
            $st = $db->prepare("SELECT DISTINCT asignatura FROM horarios WHERE anio = ? ORDER BY asignatura");
            $st->execute([(int)$year]);
            return $st->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            error_log('Horario distinctSubjects(): ' . $e->getMessage());
            return [];
        }
    }
}
