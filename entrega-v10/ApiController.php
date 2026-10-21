<?php
/**
 * ApiController — endpoints JSON reutilizables
 *
 * /api/suggest?type=estudiante&q=juan
 * Devuelve sugerencias para la busqueda predictiva de TODAS las paginas:
 *   - estudiante : nombre + curso (max 10), busca por CUALQUIER palabra del nombre o codigo BNB
 *   - curso      : nombre del curso
 *
 * Formato de respuesta comun (para el helper JS suggest.js):
 *   [{ "label": "...", "sub": "...", "url": "/students/123" }, ...]
 */

namespace App\Controllers;

class ApiController extends Controller {

    public function suggest() {
        header('Content-Type: application/json; charset=utf-8');

        if (!function_exists('isAuthenticated') || !isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'no_autenticado']);
            return;
        }

        $type = strtolower(trim((string)($_GET['type'] ?? 'estudiante')));
        $q    = trim((string)($_GET['q'] ?? ''));

        if (mb_strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        try {
            $db  = \Database::getConnection();
            $out = [];

            if ($type === 'curso') {
                $st = $db->prepare(
                    "SELECT id, curso FROM cursos
                     WHERE curso LIKE ? OR grado LIKE ? OR paralelo LIKE ?
                     ORDER BY curso LIMIT 10"
                );
                $like = '%' . $q . '%';
                $st->execute([$like, $like, $like]);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $out[] = [
                        'label' => $r['curso'],
                        'sub'   => '',
                        'url'   => '/courses/' . (int)$r['id'] . '/edit',
                    ];
                }
            } else {
                // Busqueda por CUALQUIER palabra del nombre (AND de palabras) o codigo BNB.
                // Misma logica que Student::filter multi-palabra, pero ligera (LIMIT 10).
                $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
                $words = array_slice($words, 0, 5); // tope de seguridad
                $cond  = [];
                $params = [];
                foreach ($words as $w) {
                    $cond[]   = "(e.nombre_completo LIKE ? OR e.codigo_bnb LIKE ?)";
                    $like     = '%' . $w . '%';
                    $params[] = $like;
                    $params[] = $like;
                }
                $sql = "SELECT e.id, e.nombre_completo, e.codigo_bnb, c.curso AS curso_nombre
                        FROM estudiantes e
                        LEFT JOIN cursos c ON e.id_curso = c.id
                        WHERE (" . implode(' AND ', $cond) . ")
                        ORDER BY e.nombre_completo LIMIT 10";
                $st = $db->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $out[] = [
                        'label' => $r['nombre_completo'],
                        'sub'   => trim(($r['curso_nombre'] ?? '') . ($r['codigo_bnb'] ? ' · ' . $r['codigo_bnb'] : ''), ' ·'),
                        'url'   => '/students/' . (int)$r['id'],
                    ];
                }
            }

            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[API suggest] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'interno']);
        }
    }
}
