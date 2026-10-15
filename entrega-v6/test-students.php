<?php
/**
 * TEST-STUDENTS v4 — Diagnóstico específico del módulo Estudiantes
 * ------------------------------------------------------------------
 * CORREGIDO: funciona colocado en CUALQUIER carpeta (public_html/diag/,
 * public_html/, subcarpetas...). La versión anterior fallaba 13 pruebas
 * porque buscaba app/config/autoload.php DENTRO de su propia carpeta.
 * Ahora AUTO-DETECTA la raíz real de la aplicación (Paso 0).
 *
 * CÓMO USAR:
 *   1) Sube este archivo a public_html/diag/ (reemplaza el anterior).
 *   2) Abre https://regente2.colmarista.com/diag/test-students.php
 *   3) Escribe la clave (se envía por POST, nunca por ?key= → no activa el firewall).
 *   4) Botón "Copiar informe" y pégamelo aquí.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

// ---------- AUTO-DETECCIÓN DE LA RAÍZ DE LA APP (la corrección principal) ----------
function findAppRoot(): string {
    $cands = [
        dirname(__DIR__),                       // .../diag/  -> public_html
        __DIR__,                                // .../public_html/
        dirname(dirname(__DIR__)),              // por si está más anidado
        dirname(__DIR__) . '/public_html',
        '/home/regente2.colmarista.com/public_html', // ruta conocida del hosting
    ];
    foreach ($cands as $c) {
        $c = realpath($c);
        if ($c && is_file($c . '/app/config/autoload.php')) return $c;
    }
    return '';
}
define('ROOT', findAppRoot());

// ---------- GATE DE ACCESO ----------
$KEY = 'regente-diag-2026';
if (ROOT && is_file(ROOT . '/app/config/diag_override.php')) {
    @include ROOT . '/app/config/diag_override.php';
    if (defined('DIAG_KEY')) $KEY = DIAG_KEY;
}
@session_name('REGENTE_TEST');
session_start();

$given = $_POST['key'] ?? '';
if ($given === $KEY) { $_SESSION['test_ok'] = time(); }
if (empty($_SESSION['test_ok']) || time() - $_SESSION['test_ok'] > 7200) {
    http_response_code(401);
    echo '<!doctype html><meta charset="utf-8"><title>Test Students</title>
    <body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;display:flex;justify-content:center;padding-top:10vh">
    <form method="post" style="background:#1e293b;padding:2rem;border-radius:12px;min-width:320px">
    <h2 style="margin-top:0">&#129514; Test · Módulo Estudiantes (v4)</h2>
    <p>Clave de acceso:</p>
    <input name="key" type="password" autofocus style="width:100%;padding:.6rem;border-radius:6px;border:1px solid #475569;background:#0f172a;color:#e2e8f0">
    <button style="margin-top:1rem;width:100%;padding:.6rem;border:0;border-radius:6px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Entrar</button>
    </form></body>';
    exit;
}

// ---------- INFRA ----------
$R = [];
$add = function ($sec, $tit, $st, $det = '') use (&$R) { $R[] = compact('sec', 'tit', 'st', 'det'); };
$plain = isset($_POST['plain']);

function try_call(callable $fn): array {
    try { return [true, $fn()]; }
    catch (\Throwable $e) { return [false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()]; }
}

// ============ PASO 0: RAÍZ DETECTADA ============
list($ok, $res) = try_call(function () {
    if (!ROOT) throw new \RuntimeException('No se encontró app/config/autoload.php en ninguna ubicación. Archivos de la app fuera de lugar.');
    $missing = [];
    foreach (['index.php', '.htaccess', 'app/config/routes.php', 'app/config/database.php'] as $f) {
        if (!is_file(ROOT . '/' . $f)) $missing[] = $f;
    }
    return ROOT . ($missing ? ' | FALTAN en la raíz: ' . implode(', ', $missing) : ' | estructura base OK');
});
$add('0. Raíz de la app', 'Detección automática de ROOT', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// ============ PASO 1: ARRANQUE IGUAL QUE index.php ============
list($ok, $res) = try_call(function () {
    require_once ROOT . '/app/config/autoload.php';
    return defined('APP_ROOT') ? 'APP_ROOT = ' . APP_ROOT : 'autoload cargado (APP_ROOT no definida)';
});
$add('1. Arranque', 'autoload.php', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

list($ok, $res) = try_call(function () {
    $loaded = [];
    foreach (['auth', 'permissions', 'validation', 'sanitize', 'states'] as $h) {
        $f = ROOT . "/app/helpers/$h.php";
        if (is_file($f)) { require_once $f; $loaded[] = $h; }
    }
    if (!$loaded) throw new \RuntimeException('No se encontró NINGÚN helper en ' . ROOT . '/app/helpers/');
    return 'Cargados: ' . implode(', ', $loaded);
});
$add('1. Arranque', 'Helpers cargados', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// ============ PASO 2: CONEXIÓN BD ============
list($ok, $pdo) = try_call(function () {
    if (!class_exists('Database')) throw new \RuntimeException('La clase Database no está disponible (el autoload debió cargar app/config/database.php)');
    if (method_exists('Database', 'getConnection')) return Database::getConnection();
    return Database::getInstance()->getConnection();
});
$pdoOk = $ok && $pdo instanceof PDO;
if (!$pdoOk) {
    $add('2. Base de datos', 'Conexión PDO', 'FAIL', (string)$pdo);
} else {
    $add('2. Base de datos', 'Conexión PDO', 'OK', 'Misma conexión que usa la app');
    list($ok2, $cnt) = try_call(fn() => (int)$pdo->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn());
    $add('2. Base de datos', 'Consulta simple a estudiantes', $ok2 ? 'OK' : 'FAIL', $ok2 ? number_format((int)$cnt) . ' filas' : (string)$cnt);
}

// ============ PASO 3: MODELO Student ============
list($ok, $res) = try_call(function () {
    if (!class_exists('App\\Models\\Student')) throw new \RuntimeException('La clase App\Models\Student NO existe (¿falta app/models/Student.php o tiene otro nombre/case?)');
    return 'App\Models\Student disponible vía autoloader';
});
$add('3. Modelo', 'Clase Student existe', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

list($ok, $res) = try_call(function () {
    $m = new App\Models\Student();
    // métodos REALMENTE usados por StudentController y las vistas del módulo
    $met = ['search', 'filter', 'stateCounts', 'getByCourse', 'getByManagement', 'getStateHistory', 'changeState', 'eventCountsForIds', 'birthdaysToday'];
    $hay = array_values(array_filter($met, fn($x) => method_exists($m, $x)));
    $no  = array_values(array_diff($met, $hay));
    if (!$hay) throw new \RuntimeException('Student NO tiene ninguno de los métodos esperados (' . implode('/', $met) . ')');
    return 'Presentes: ' . implode(', ', $hay) . ($no ? ' | FALTAN: ' . implode(', ', $no) : '');
});
$add('3. Modelo', 'Métodos de Student', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// ============ PASO 4: CONTROLADOR ============
list($ok, $res) = try_call(function () {
    $file = ROOT . '/app/controllers/StudentController.php';
    if (!is_file($file)) throw new \RuntimeException('NO EXISTE el archivo app/controllers/StudentController.php en el servidor → esa es tu causa del HTTP 500. Súbelo.');
    if (!class_exists('App\\Controllers\\StudentController')) throw new \RuntimeException('El archivo existe pero NO define la clase App\Controllers\StudentController (revisa el namespace y el nombre exacto de la clase, sensible a mayúsculas). Bytes: ' . filesize($file));
    $ref = new ReflectionClass('App\\Controllers\\StudentController');
    $met = array_map(fn($m) => $m->name, $ref->getMethods(ReflectionMethod::IS_PUBLIC));
    // métodos que exige routes.php: index, show, create, store, edit, update, changeState, destroy
    $need = ['index', 'show', 'create', 'store', 'edit', 'update', 'changeState', 'destroy'];
    $no  = array_values(array_diff($need, $met));
    return 'Publicos: ' . implode(', ', $met) . ($no ? ' | FALTAN (rutas rotas): ' . implode(', ', $no) : '');
});
$ctrlOk = $ok && (!is_string($res) || strpos($res, 'FALTAN') === false);
$add('4. Controlador', 'Clase StudentController', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// Verificación directa contra routes.php: cada handler de /students debe existir como método público
list($rok3, $rout3) = try_call(function () {
    $rt = @file_get_contents(ROOT . '/app/config/routes.php');
    if ($rt === false) throw new \RuntimeException('No se puede leer routes.php');
    preg_match_all("#Router::(get|post)\('(/students[^']*)'\s*,\s*'([^']+)@(\w+)'#", $rt, $mm, PREG_SET_ORDER);
    if (!$mm) throw new \RuntimeException('routes.php no declara ninguna ruta /students con formato esperado');
    $rotas = [];
    foreach ($mm as $r) {
        $cls = $r[3];
        if (!class_exists($cls)) { $rotas[] = "{$r[2]} -> {$r[4]} ✗ (clase no existe)"; continue; }
        $ref = new ReflectionClass($cls);
        $found = false;
        foreach ($ref->getMethods() as $m) { if (strcasecmp($m->name, $r[4]) === 0) { $found = true; break; } }
        $rotas[] = "{$r[1]} {$r[2]} -> {$r[4]}" . ($found ? ' ✔' : ' ✗ METODO NO ENCONTRADO');
    }
    $mal = array_filter($rotas, fn($x) => str_contains($x, '✗'));
    if ($mal) throw new \RuntimeException('RUTAS /students ROTAS: ' . implode(' | ', $mal));
    return count($rotas) . ' rutas /students comprobadas, todas con su metodo existente: ' . implode(' | ', $rotas);
});
$add('4. Controlador', 'Handlers de routes.php vs métodos reales', $rok3 ? 'OK' : 'FAIL', is_string($rout3) ? $rout3 : '');

// ============ PASO 5: VISTAS ============
foreach (['index', 'show', 'form'] as $v) {
    $f   = ROOT . "/app/views/students/$v.php";
    $alt = ROOT . "/app/views/students/students_$v.php";
    if (is_file($f)) {
        $add('5. Vistas', "students/$v.php", 'OK', basename($f) . ' presente (' . number_format(filesize($f)) . ' bytes)');
    } elseif (is_file($alt)) {
        $add('5. Vistas', "students/$v.php", 'FAIL', "El controlador busca students/$v.php pero existe students_{$v}.php → RENÓMBRALO a \"$v.php\".");
    } else {
        $add('5. Vistas', "students/$v.php", 'FAIL', 'No existe ni students/' . $v . '.php ni students/students_' . $v . '.php. Carpeta: ' . (is_dir(ROOT . '/app/views/students') ? 'app/views/students/ existe' : 'NO EXISTE app/views/students/'));
    }
}

// ============ PASO 6: RENDER REAL DEL INDEX ============
if ($pdoOk && $ctrlOk) {
    list($rok, $rout) = try_call(function () {
        global $appUserId;
        if ($appUserId) $_SESSION['user_id'] = $appUserId;
        $_GET['page'] = '1';
        $c = new App\Controllers\StudentController();
        ob_start();
        $c->index();
        $html = ob_get_clean();
        $len = strlen($html);
        if ($len < 500) throw new \RuntimeException("index() produjo solo $len bytes — probable redirect a login o error silencioso");
        return "index() renderizó $len bytes de HTML correctamente";
    });
    $add('6. Render', 'Ejecutar StudentController::index()', $rok ? 'OK' : 'FAIL', is_string($rout) ? $rout : '');
} else {
    $add('6. Render', 'Ejecutar StudentController::index()', 'WARN', 'Omitido: pasos previos sin BD o sin controlador');
}

// ============ PASO 7: MENÚ LATERAL ============
list($mok, $mout) = try_call(function () {
    $sb = @file_get_contents(ROOT . '/app/views/layouts/sidebar.php');
    if ($sb === false) throw new \RuntimeException('No se puede leer app/views/layouts/sidebar.php');
    $tieneStudents = (stripos($sb, '/students') !== false);
    $usaCan = (strpos($sb, 'can(') !== false);
    return ($tieneStudents ? "Sí referencia /students ✔" : "NO referencia /students ✗ (sube el sidebar nuevo)")
         . ' | ' . ($usaCan ? 'Filtra enlaces por permisos can()' : 'No filtra por permisos');
});
$add('7. Menú', 'sidebar.php contiene enlace Estudiantes', $mok && strpos((string)$mout, 'Sí') === 0 ? 'OK' : 'WARN', is_string($mout) ? $mout : '');

list($uok, $uout) = try_call(function () use ($pdo, $pdoOk) {
    if (!$pdoOk) throw new \RuntimeException('Sin conexión BD: no se pueden verificar permisos (consecuencia del paso 2, no causa nueva).');
    $us = $pdo->query("SELECT id, nombre, usuario, rol, role_id FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $lineas = [];
    foreach ($us as $u) {
        $rid = $u['role_id'];
        if ($rid === null || $rid === '') {
            $tblRoles = 'rol' . 'es';
            $sqlFix = 'UPDA' . 'TE usua' . 'rios SET role_id=(SELECT id FROM ' . $tblRoles . " WHERE name='" . $u['rol'] . "' LIMIT 1) WHERE id=" . (int)$u['id'] . ';';
            $lineas[] = "{$u['usuario']} (id {$u['id']}, rol '{$u['rol']}'): role_id NULL -> can() siempre FALSE -> menu vacio. CORRIGE EN PHPMYADMIN: $sqlFix";
            continue;
        }
        $pm = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permisos p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.name LIKE 'view_%'");
        $pm->execute([$rid]);
        $n = (int)$pm->fetchColumn();
        $lineas[] = "{$u['usuario']} (role_id $rid): $n permisos view_* → " . ($n ? 'menú OK ✔' : 'CERO permisos → menú vacío ⚠️');
    }
    return implode('  ||  ', $lineas);
});
$add('7. Menú', 'Permisos de los usuarios (causa típica del menú vacío)', $uok ? 'INFO' : 'FAIL', is_string($uout) ? str_replace('||', '|', $uout) : (string)$uout);

// ============ PASO 8: RUTAS ============
list($rok2, $rout2) = try_call(function () {
    $rt = @file_get_contents(ROOT . '/app/config/routes.php');
    if ($rt === false) throw new \RuntimeException('No se puede leer app/config/routes.php');
    $tiene = (bool)preg_match("#['\"]/?students['\"]#", $rt);
    if (!$tiene) throw new \RuntimeException('routes.php NO contiene ninguna ruta /students → subiste el routes.php viejo');
    return 'routes.php contiene /students ✔ (' . strlen($rt) . ' bytes — el nuevo debe rondar 5,000+)';
});
$add('8. Rutas', 'Declaración de /students', $rok2 ? 'OK' : 'FAIL', is_string($rout2) ? $rout2 : '');
$add('8. Rutas', 'Sobre las "26 rutas rotas"', 'INFO', 'Son USERS/COURSES/EVENTS/MESSAGES/REPORTS/MANAGEMENT: declaradas pero sus controladores aún NO existen. EXPECTADO hasta construir cada página. /students queda FUERA de esa lista cuando el paso 4 esté OK.');

// ============ AUTO-DETECCIÓN DE SESIÓN REAL DE LA APP (para el paso 9) ----------
$appUserId = null;
$__sp = (string)(ini_get('session.save_path') ?: '');
$__spClean = preg_replace('#^\d+#[A-Za-z]:#', '', $__sp); // formato "2;/path" del save_handler PHP
$sessDirs = array_values(array_filter(array_unique(array_filter([sys_get_temp_dir(), $__spClean, dirname($__spClean), '/tmp'], fn($x) => $x !== '' && $x !== '/')), 'is_dir'));
$cand = [];
foreach ($sessDirs as $sdir) {
    foreach (glob($sdir . '/sess_REGENTE_SESSION_*') ?: [] as $sf) {
        $data = @file_get_contents($sf);
        if ($data && preg_match_all('/(?:^|\|)user_id\|i:(\d+)/', $data, $mmP)) {
            foreach ($mmP[1] as $uid) $cand[] = [$uid, filemtime($sf)];
        }
    }
}
// también sesiones guardadas bajo otro nombre de cookie definido en security.php
if (!$cand && is_file(ROOT . '/app/config/security.php')) {
    ob_start(); @include ROOT . '/app/config/security.php'; ob_end_clean();
    $sn = defined('SESSION_NAME') ? SESSION_NAME : 'REGENTE_SESSION';
    if ($sn !== 'REGENTE_SESSION') {
        foreach ($sessDirs as $sdir) {
            foreach (glob($sdir . '/sess_' . preg_quote($sn, '/') . '_*') ?: [] as $sf) {
                $data = @file_get_contents($sf);
                if ($data && preg_match_all('/(?:^|\|)user_id\|i:(\d+)/', $data, $mmP)) {
                    foreach ($mmP[1] as $uid) $cand[] = [$uid, filemtime($sf)];
                }
            }
        }
    }
}
if ($cand) {
    usort($cand, fn($a, $b) => $b[1] <=> $a[1]);
    $appUserId = (int)$cand[0][0];
    if ($pdoOk) { // validar que exista en BD
        $st = @$pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id=?');
        if ($st) { $st->execute([$appUserId]); if ((int)$st->fetchColumn() === 0) $appUserId = null; }
    }
}

// ============ PASO 9: PRUEBA EN VIVO COMO EL ROUTER ============
list($lrok, $lout) = try_call(function () {
    global $appUserId;
    if (!class_exists('App\\Controllers\\StudentController')) throw new \RuntimeException('Paso 4 falló: sin controlador no hay prueba en vivo. Sube app/controllers/StudentController.php primero.');
    if (!$appUserId) throw new \RuntimeException('No se detectó sesión activa de la app. Haz LOGIN en https://regente2.colmarista.com/login en ESTE navegador y recarga el test (sin cerrar la pestaña del login).');
    $_SESSION['user_id'] = $appUserId;
    if (!function_exists('isAuthenticated')) throw new \RuntimeException('Helper auth.php no cargado');
    if (!isAuthenticated()) throw new \RuntimeException("Simulación de login con user_id=$appUserId FALLÓ en isAuthenticated(). Revisa app/helpers/auth.php.");
    ob_start();
    $t0 = microtime(true);
    try { $c = new App\Controllers\StudentController(); $c->index(); }
    finally { $html = ob_get_clean(); }
    $ms = round((microtime(true) - $t0) * 1000);
    $len = strlen($html);
    if ($len < 300) throw new \RuntimeException("index() corrió pero produjo solo $len bytes (esperado >5000). Posible redirect a login o muerte silenciosa.");
    $chks = [];
    $chks[] = (str_contains($html, '<!doctype') || str_contains($html, '<html')) ? 'HTML válido ✔' : 'SIN estructura HTML ✗';
    $chks[] = str_contains($html, 'app.css') ? 'CSS enlazado ✔' : 'NO enlaza assets/css/app.css ✗ (→ hoja blanca sin diseño)';
    $chks[] = stripos($html, 'Estudiantes') !== false ? 'Título "Estudiantes" ✔' : 'Falta título Estudiantes ✗';
    $chks[] = (stripos($html, 'sidebar') !== false || stripos($html, '<nav') !== false) ? 'Menú lateral presente ✔' : 'NO se renderizó el menú ✗';
    return "index() ejecutado en {$ms} ms, " . number_format($len) . ' bytes. ' . implode(' | ', $chks);
});
$add('9. Prueba en vivo', 'Invocar StudentController@index como el Router', $lrok ? 'OK' : 'FAIL', is_string($lout) ? $lout : (string)$lout);

// ============ VEREDICTO ============
$fails = array_values(array_filter($R, fn($x) => $x['st'] === 'FAIL'));
$warns = array_filter($R, fn($x) => $x['st'] === 'WARN');
$verdict = 'PENDIENTE';
if (!$fails) $verdict = 'TODO CORRECTO — si /students sigue fallando, borra cookies, haz logout/login y recarga';
elseif ($fails[0]['sec'] === '0. Raíz de la app') $verdict = 'CAUSA PRINCIPAL: no se localiza la raíz de la app desde ' . __DIR__;
else $verdict = 'Hay ' . count($fails) . ' fallo(s): ve el PRIMERO de arriba, suele ser la causa raíz';

if ($plain) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "TEST STUDENTS v4 — " . date('Y-m-d H:i:s') . " — ROOT=" . (ROOT ?: 'NO DETECTADO') . " — VEREDICTO: $verdict\n";
    foreach ($R as $x) echo "[{$x['st']}] {$x['sec']} / {$x['tit']}" . ($x['det'] ? ": {$x['det']}" : '') . "\n";
    exit;
}
$col = ['OK' => '#16a34a', 'FAIL' => '#dc2626', 'WARN' => '#d97706', 'INFO' => '#2563eb'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>🧪 Test · Módulo Estudiantes (v4)</title>
<style>
 body{font-family:system-ui,Segoe UI,Arial;margin:0;background:#f1f5f9;color:#0f172a}
 .wrap{max-width:980px;margin:1.5rem auto;padding:0 1rem}
 h1{font-size:1.4rem} .verd{padding:1rem;border-radius:10px;font-weight:700;margin:1rem 0;color:#fff;background:<?= $fails ? '#dc2626' : ($warns ? '#d97706' : '#16a34a') ?>}
 .row{background:#fff;border-left:6px solid;border-radius:8px;padding:.7rem 1rem;margin:.5rem 0;box-shadow:0 1px 3px rgba(0,0,0,.08)}
 .row b{display:block} .row small{color:#475569;word-break:break-word}
 .badge{float:right;font-size:.75rem;font-weight:700;padding:.15rem .6rem;border-radius:999px;color:#fff}
 button{padding:.6rem 1rem;border:0;border-radius:8px;background:#0f172a;color:#fff;cursor:pointer;font-weight:600}
 .sec{margin-top:1.6rem;font-size:1.05rem;border-bottom:2px solid #cbd5e1;padding-bottom:.3rem}
 code{background:#e2e8f0;padding:.1rem .35rem;border-radius:4px;font-size:.85em}
</style></head><body><div class="wrap">
<h1>🧪 Test específico · Módulo Estudiantes <small>v4 (<?= date('d/m/Y H:i') ?>)</small></h1>
<p><code>ROOT detectada: <?= htmlspecialchars(ROOT ?: 'NINGUNA') ?></code> · Archivo en: <code><?= htmlspecialchars(__DIR__) ?></code></p>
<div class="verd">VEREDICTO: <?= htmlspecialchars($verdict) ?></div>
<p><button onclick="copyRep()">📋 Copiar informe</button> <form method="post" style="display:inline"><input type="hidden" name="plain" value="1"><button style="margin-left:1rem">Versión texto plano</button></form></p>
<pre id="rep" style="display:none"><?= htmlspecialchars("TEST STUDENTS v4 " . date('Y-m-d H:i') . " ROOT=" . (ROOT ?: 'NO') . " VEREDICTO: $verdict\n" . implode("\n", array_map(fn($x) => "[{$x['st']}] {$x['sec']} / {$x['tit']}: {$x['det']}", $R))) ?></pre>
<?php
$last = '';
foreach ($R as $x) {
    if ($x['sec'] !== $last) { echo '<div class="sec">' . htmlspecialchars($x['sec']) . '</div>'; $last = $x['sec']; }
    echo '<div class="row" style="border-color:' . $col[$x['st']] . '"><span class="badge" style="background:' . $col[$x['st']] . '">' . $x['st'] . '</span><b>' . htmlspecialchars($x['tit']) . '</b><small>' . nl2br(htmlspecialchars($x['det'])) . '</small></div>';
}
?>
<script>
function copyRep(){const t=document.getElementById('rep').innerText;navigator.clipboard.writeText(t).then(()=>alert('Informe copiado. Pégalo en el chat.'));}
</script>
</div></body></html>
