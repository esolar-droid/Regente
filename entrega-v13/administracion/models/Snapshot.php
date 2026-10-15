<?php
/**
 * Snapshot Model — respaldo interno de gestiones ("cambio de ropa")
 *
 * Cada snapshot guarda el JSON comprimido (gzip) de TODAS las tablas de
 * datos de una gestión escolar. Restaurar = vaciar las tablas y recargar
 * el contenido del snapshot. La estructura del sistema no cambia, solo
 * la "vestidura" de datos.
 */

namespace App\Models;

class Snapshot extends Model {
    protected static $table = 'snapshots';
    protected static $primaryKey = 'id';

    public $id;
    public $anio;
    public $nombre;
    public $tamano_bytes;
    public $tabla_stats;
    public $creado_por;
    public $creado_en;

    /** Tablas incluidas en un snapshot (orden de restauración importa). */
    const TABLES = [
        'gestion',
        'cursos',
        'estudiantes',
        'eventos',
        'mensajes_enviados',
        'feriados',
        'horarios',
        'estudiante_estados',
    ];

    public function __construct($data = []) {
        parent::__construct($data);
    }

    public static function allList() {
        return static::query(
            "SELECT id, anio, nombre, tamano_bytes, tabla_stats, creado_por, creado_en
             FROM snapshots ORDER BY creado_en DESC"
        );
    }

    /**
     * Capturar el estado actual de todas las tablas de datos como snapshot.
     * @return array ['ok'=>bool,'id'=>int|null,'error'=>string|null,'stats'=>array]
     */
    public static function capture($anio, $userId) {
        try {
            $db = \Database::getConnection();
            $payload = [];
            $stats = [];
            foreach (self::TABLES as $t) {
                // Nombre de tabla fijo (const), sin interpolación de usuario.
                $rows = $db->query("SELECT * FROM `$t`")->fetchAll(\PDO::FETCH_ASSOC);
                $payload[$t] = $rows;
                $stats[$t] = count($rows);
            }
            $json = json_encode([
                'version'   => 1,
                'anio'      => (int)$anio,
                'capturado' => date('c'),
                'tablas'    => $payload,
            ], JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return ['ok' => false, 'error' => 'No se pudo serializar el respaldo.', 'id' => null, 'stats' => []];
            }
            $gz = function_exists('gzencode') ? gzencode($json, 9) : $json;

            $stmt = $db->prepare(
                "INSERT INTO snapshots (anio, nombre, data, tamano_bytes, tabla_stats, creado_por)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)$anio,
                'Gestión ' . (int)$anio,
                $gz,
                strlen($gz),
                json_encode($stats),
                $userId ? (int)$userId : null,
            ]);
            return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'error' => null, 'stats' => $stats];
        } catch (\Throwable $e) {
            error_log('Snapshot::capture: ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage(), 'stats' => []];
        }
    }

    /**
     * Restaurar una gestión desde su snapshot: BORRA el contenido actual de
     * las tablas de datos y lo reemplaza por el del respaldo. Todo en una
     * transacción: si algo falla, no se pierde nada.
     * @return array ['ok'=>bool,'error'=>string|null,'stats'=>array]
     */
    public static function restore($id) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT data FROM snapshots WHERE id = ?");
            $stmt->execute([(int)$id]);
            $blob = $stmt->fetchColumn();
            if ($blob === false || $blob === null) {
                return ['ok' => false, 'error' => 'Snapshot no encontrado.', 'stats' => []];
            }
            $json = function_exists('gzdecode') ? @gzdecode($blob) : $blob;
            if (!$json) $json = $blob; // snapshot sin gzip
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['tablas'])) {
                return ['ok' => false, 'error' => 'El respaldo está dañado (JSON inválido).', 'stats' => []];
            }

            $stats = [];
            $db->exec("SET FOREIGN_KEY_CHECKS=0");
            try {
                $db->beginTransaction();
                // Borrar en orden inverso (hijos primero) e insertar en orden directo.
                foreach (array_reverse(self::TABLES) as $t) {
                    $db->exec("DELETE FROM `$t`");
                }
                foreach (self::TABLES as $t) {
                    $rows = $data['tablas'][$t] ?? [];
                    $stats[$t] = count($rows);
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $sql = "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES ("
                             . implode(',', array_fill(0, count($cols), '?')) . ")";
                        $ins = $db->prepare($sql);
                        $ins->execute(array_values($row));
                    }
                }
                $db->commit();
            } catch (\Throwable $ex) {
                if ($db->inTransaction()) $db->rollBack();
                throw $ex;
            } finally {
                $db->exec("SET FOREIGN_KEY_CHECKS=1");
            }
            return ['ok' => true, 'error' => null, 'stats' => $stats];
        } catch (\Throwable $e) {
            error_log('Snapshot::restore: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'stats' => []];
        }
    }

    /** Descargar el snapshot como archivo .json.gz legible por phpMyAdmin/CLI. */
    public static function raw($id) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT anio, nombre, data FROM snapshots WHERE id = ?");
            $stmt->execute([(int)$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            error_log('Snapshot::raw: ' . $e->getMessage());
            return null;
        }
    }

    public static function remove($id) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("DELETE FROM snapshots WHERE id = ?");
            return $stmt->execute([(int)$id]);
        } catch (\Throwable $e) {
            error_log('Snapshot::remove: ' . $e->getMessage());
            return false;
        }
    }
}
