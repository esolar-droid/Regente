<?php
namespace App\Controllers;

/**
 * Herramienta interna de verificacion del modulo Estudiantes.
 * Se ejecuta DENTRO del bootstrap real de la app (misma sesion, helpers y BD),
 * por eso el firewall del hosting ya no la redirige al dashboard.
 * URL: https://regente2.colmarista.com/tools/stcheck?k=CLAVE[&plain=1]
 */
class ToolController extends Controller
{
    public function stcheck(): void
    {
        $KEY = 'regente-diag-2026';
        if (is_file(APP_ROOT . '/app/config/diag_override.php')) {
            @include APP_ROOT . '/app/config/diag_override.php';
            if (defined('DIAG_KEY')) $KEY = DIAG_KEY;
        }
        $k = $_GET['k'] ?? '';
        if ($k !== $KEY) {
            http_response_code(403);
            echo '<h2>Acceso denegado</h2><p>A&ntilde;ade <code>?k=CLAVE</code> a la URL.</p>';
            return;
        }

        $R = [];
        $add = function ($sec, $tit, $st, $det = '') use (&$R) { $R[] = compact('sec', 'tit', 'st', 'det'); };
        $tcall = function (callable $f): array {
            try {
                ob_start();
                $v = $f();
                $out = ob_get_clean();
                return [true, is_string($v) ? $v : 'OK', $out];
            } catch (\Throwable $e) {
                $out = ob_get_clean();
                return [false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), $out];
            }
        };

        // A. Sesion real del navegador
        [$ok, $d] = $tcall(function () {
            if (!function_exists('isAuthenticated') || !isAuthenticated()) {
                throw new \RuntimeException('No autenticado. Inicia sesion en /login y recarga esta pagina.');
            }
            $u = user();
            return 'Logueado como ' . ($u['usuario'] ?? '?') . ' (id ' . (userId() ?? '?') . ', rol ' . (userRole() ?? '-') . ', role_id ' . (userRoleId() ?? 'NULL') . ')';
        });
        $add('A. Sesion', 'Usuario actual', $ok ? 'OK' : 'FAIL', $d);

        // B. Permisos can()
        [$ok, $d] = $tcall(function () {
            if (!function_exists('can')) throw new \RuntimeException('Helper can() no cargado');
            $ps = ['view_students', 'view_events', 'view_reports', 'manage_users', 'send_messages'];
            $out = [];
            foreach ($ps as $p) $out[] = "$p=" . (can($p) ? 'SI' : 'NO');
            return implode(', ', $out);
        });
        $add('B. Permisos', 'can() por permiso', $ok ? 'OK' : 'FAIL', $d);

        // C. Base de datos
        [$ok, $d] = $tcall(function () {
            $pdo = Database::getConnection();
            $n = (int)$pdo->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn();
            $g = $pdo->query("SELECT anio FROM gestion WHERE estado='activa' LIMIT 1")->fetchColumn();
            return "Conectado. estudiantes=$n, gestion activa=" . ($g ?: 'NINGUNA');
        });
        $add('C. Base de datos', 'Consulta del modulo', $ok ? 'OK' : 'FAIL', $d);

        // D. Render en vivo del controlador (simula sesion si el test se abre sin login)
        [$ok, $d, $htmlOut] = $tcall(function () {
            if (!class_exists('App\\Controllers\\StudentController')) {
                throw new \RuntimeException('Clase StudentController NO existe en el servidor (revisa app/controllers/StudentController.php, nombre exacto y namespace)');
            }
            $simulada = false;
            if (!isAuthenticated()) {
                $pdo = Database::getConnection();
                $u = $pdo->query("SELECT id FROM usuarios ORDER BY FIELD(rol,'admin','regente','profesor') LIMIT 1")->fetchColumn();
                if (!$u) throw new \RuntimeException('No hay usuario en BD para simular la prueba. Inicia sesion en /login y recarga.');
                $_SESSION['user_id'] = (int)$u;
                $simulada = true;
            }
            $_GET['page'] = '1';
            $c = new \App\Controllers\StudentController();
            $c->index();
            return ($simulada ? '[prueba con sesion simulada de un usuario admin] ' : '[prueba con TU sesion real] ') . 'render OK';
        });
        if ($ok) {
            $len = strlen($htmlOut);
            $chks = [];
            $chks[] = ($len > 5000 ? 'HTML generado: ' . number_format($len) . ' bytes OK' : "HTML muy corto ($len bytes) - posible redirect o muerte silenciosa");
            $chks[] = str_contains($htmlOut, 'app.css') ? 'CSS enlazado OK' : 'SIN CSS (causa hoja blanca sin diseño)';
            $chks[] = (stripos($htmlOut, 'sidebar') !== false || stripos($htmlOut, '<nav') !== false) ? 'Menu presente OK' : 'SIN menu en el HTML';
            $d = 'Render completo: ' . implode(' | ', $chks);
        }
        $add('D. Render en vivo', 'StudentController@index()', $ok ? 'OK' : 'FAIL', $d);

        // E. sidebar fuente
        [$ok, $d] = $tcall(function () {
            $sb = @file_get_contents(APP_ROOT . '/app/views/layouts/sidebar.php');
            if ($sb === false) throw new \RuntimeException('No se puede leer app/views/layouts/sidebar.php');
            return (stripos($sb, '/students') !== false ? 'sidebar referencia /students OK' : 'sidebar NO referencia /students (sube el sidebar nuevo)') . ' | ' . strlen($sb) . ' bytes';
        });
        $add('E. Menu lateral', 'Contenido de sidebar.php', $ok ? 'OK' : 'WARN', $d);

        $fails = array_filter($R, fn($x) => $x['st'] === 'FAIL');
        $verdict = $fails ? 'FALLOS: revisa el PRIMERO arriba, es la causa raiz' : 'MODULO ESTUDIANTES OPERATIVO';

        if (isset($_GET['plain'])) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "STCHECK " . date('Y-m-d H:i') . " - VEREDICTO: $verdict\n";
            foreach ($R as $x) echo "[{$x['st']}] {$x['sec']} / {$x['tit']}: {$x['det']}\n";
            return;
        }

        $col = ['OK' => '#16a34a', 'FAIL' => '#dc2626', 'WARN' => '#d97706', 'INFO' => '#2563eb'];
        echo '<!doctype html><meta charset="utf-8"><title>StCheck</title>';
        echo '<body style="font-family:system-ui;background:#f1f5f9;margin:0"><div style="max-width:900px;margin:1rem auto;padding:0 1rem">';
        echo '<h2>StCheck - Modulo Estudiantes ' . date('d/m/Y H:i') . '</h2>';
        echo '<div style="padding:.8rem;border-radius:8px;color:#fff;font-weight:700;background:' . ($fails ? '#dc2626' : '#16a34a') . '">' . htmlspecialchars($verdict) . '</div>';
        echo '<p><a href="?k=' . htmlspecialchars($KEY) . '&amp;plain=1" style="background:#0f172a;color:#fff;padding:.5rem 1rem;border-radius:6px;text-decoration:none">Informe texto plano (seleccionar y copiar)</a></p>';
        foreach ($R as $x) {
            echo '<div style="background:#fff;border-left:6px solid ' . $col[$x['st']] . ';border-radius:8px;padding:.6rem .9rem;margin:.5rem 0">';
            echo '<b>' . htmlspecialchars($x['sec'] . ' / ' . $x['tit']) . ' [' . $x['st'] . ']</b><br><small>' . nl2br(htmlspecialchars($x['det'])) . '</small></div>';
        }
        echo '</div></body>';
    }
}
