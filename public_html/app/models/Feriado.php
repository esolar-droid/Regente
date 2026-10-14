<?php
/**
 * Feriado Model — días inhábiles (tabla `feriados`)
 *
 * Gestionados desde Administración. Un feriado bloquea el registro de
 * asistencia en la grilla masiva y en el alta individual.
 */

namespace App\Models;

class Feriado extends Model {
    protected static $table = 'feriados';
    protected static $primaryKey = 'id';

    /** Feriados del año escolar activo (o todos si no hay gestión). */
    public static function forYear($year) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT id, fecha, nombre FROM feriados WHERE anio = ? ORDER BY fecha");
            $stmt->execute([(int)$year]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Feriado forYear(): ' . $e->getMessage());
            return [];
        }
    }

    /** Mapa [fecha => nombre] para días concretos (grilla semanal). */
    public static function mapForDates(array $dates) {
        $dates = array_values(array_filter($dates));
        if (!$dates) return [];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($dates), '?'));
            $stmt = $db->prepare("SELECT fecha, nombre FROM feriados WHERE fecha IN ($ph)");
            $stmt->execute($dates);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $out[$r['fecha']] = $r['nombre'];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Feriado mapForDates(): ' . $e->getMessage());
            return [];
        }
    }

    public static function exists($date) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ?");
            $stmt->execute([$date]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            error_log('Feriado exists(): ' . $e->getMessage());
            return false;
        }
    }

    public static function add($date, $nombre) {
        try {
            $db = \Database::getConnection();
            $y = (int)substr($date, 0, 4);
            $stmt = $db->prepare("INSERT INTO feriados (fecha, nombre, anio) VALUES (?,?,?)");
            $stmt->execute([$date, $nombre, $y]);
            return (int)$db->lastInsertId();
        } catch (\PDOException $e) {
            // duplicate key => ya existe
            error_log('Feriado add(): ' . $e->getMessage());
            return 0;
        }
    }

    public static function remove($id) {
        try {
            $db = \Database::getConnection();
            return $db->prepare("DELETE FROM feriados WHERE id = ?")->execute([(int)$id]);
        } catch (\Throwable $e) {
            error_log('Feriado remove(): ' . $e->getMessage());
            return false;
        }
    }
}
