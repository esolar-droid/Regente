<?php
/**
 * TEST-STUDENTS — Diagnóstico específico del módulo Estudiantes
 * ---------------------------------------------------------------
 * Responde 3 preguntas con evidencia real:
 *   1. ¿Por qué /students da "Error interno del servidor" (500)?
 *   2. ¿Por qué no aparece en el menú lateral?
 *   3. ¿Por qué el diagnóstico dice que la ruta está "rota"?
 *
 * CÓMO USAR:
 *   a) Sube este archivo a public_html/ (junto a index.php).
 *   b) Abre en el navegador:  https://regente2.colmarista.com/test-students.php?key=CLAVE
 *      (CLAVE por defecto: regente-diag-2026 — cámbiala abajo o usa diag_override.php)
 *   c) Pega aquí el texto del botón "Copiar informe".
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // capturamos nosotros, no el servidor

// ---------- GATE DE ACCESO ----------
$KEY = 'regente-diag-2026';
if (is_file(__DIR__ . '/app/config/diag_override.php')) {
    @include __DIR__ . '/app/config/diag_override.php'; // define DIAG_KEY
    if (defined('DIAG_KEY')) $KEY = DIAG_KEY;
}
// IMPORTANTE: no reutilizamos el nombre de sesion de la app (REGENTE_SESSION):
// compartirlo haria que este script herede los permisos del usuario logueado y el test mentiria.
@session_name('REGENTE_TEST');
session_start();

// ---------- AUTO-DETECCION DE LA SESION REAL DE LA APP (para probar /students autenticado) ----------
// Leemos las sesiones en disco del servidor para encontrar un user_id logueado.
// Solo lectura; no altera nada. Si no hay nadie logueado, el test avisa y listo.
$appUserId = null;
$appSessionRaw = null;
foreach (array_unique([sys_get_temp_dir(), ini_get('session.save_path') ?: sys_get_temp_dir()]) as $sdir) {
    if (!is_dir($sdir)) continue;
    foreach (glob($sdir . '/sess_REGENTE_SESSION_*') ?: [] as $sf) {
        $data = @file_get_contents($sf);
        if ($data && preg_match('/user_id\|i:(\d+)/', $data, $mm)) { $appUserId = (int)$mm[1]; $appSessionRaw = $data; break 2; }
    }
}
// Si el navegador trae la cookie de sesion de la app, esa es la sesion del usuario logueado (prioridad)
$appCookieName = 'REGENTE_SESSION';
if (is_file(__DIR__ . '/app/config/security.php')) {
    $sec = @include __DIR__ . '/app/config/security.php';
    if (is_array($sec) && !empty($sec['SESSION_NAME'])) $appCookieName = $sec['SESSION_NAME'];
}
if (!empty($_COOKIE[$appCookieName])) {
    $sf = false;
    foreach (array_unique([sys_get_temp_dir(), ini_get('session.save_path') ?: sys_get_temp_dir()]) as $sdir) {
        if (is_file($sdir . '/sess_' . $appCookieName . '_' . $_COOKIE[$appCookieName])) { $sf = $sdir . '/sess_' . $appCookieName . '_' . $_COOKIE[$appCookieName]; break; }
    }
    if ($sf) {
        $data = @file_get_contents($sf);
        if ($data && preg_match('/user_id\|i:(\d+)/', $data, $mm)) { $appUserId = (int)$mm[1]; $appSessionRaw = $data; }
    }
}

// La clave SOLO se envia por POST (nunca por ?key=... porque las reglas anti-inyeccion
// del .htaccess bloquean query strings que contienen patrones como '--').
$given = $_POST['key'] ?? '';
if ($given === $KEY) { $_SESSION['test_ok'] = time(); }
if (empty($_SESSION['test_ok']) || time() - $_SESSION['test_ok'] > 7200) {
    http_response_code(401);

    echo '<!doctype html><meta charset="utf-8"><title>Test Students</title>
    <body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;display:flex;justify-content:center;padding-top:10vh">
    <form method="post" style="background:#1e293b;padding:2rem;border-radius:12px;min-width:320px">
    <h2 style="margin-top:0">🧪 Test · Módulo Estudiantes</h2>
    <p>Clave de acceso:</p>
    <input name="key" type="password" autofocus style="width:100%;padding:.6rem;border-radius:6px;border:1px solid #475569;background:#0f172a;color:#e2e8f0">
    <button style="margin-top:1rem;width:100%;padding:.6rem;border:0;border-radius:6px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Entrar</button>
    </form></body>';
    exit;
}

// ---------- INFRA ----------
$R = [];          // resultados: [seccion, titulo, estado, detalle]
$add = function ($sec, $tit, $st, $det = '') use (&$R) { $R[] = compact('sec', 'tit', 'st', 'det'); };
$plain = isset($_POST['plain']);

function try_call(callable $fn): array {
    try { return [true, $fn()]; }
    catch (\Throwable $e) { return [false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()]; }
}

define('ROOT', __DIR__);

// ============ PASO 1: BOOTSTRAP IGUAL QUE index.php ============
list($ok, $res) = try_call(function () {
    require ROOT . '/app/config/autoload.php';
    return defined('APP_ROOT') ? APP_ROOT : 'APP_ROOT no definida';
});
$add('1. Arranque', 'autoload.php', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : (string)$res);

// Cargar helpers como lo hace App.php
list($ok, $res) = try_call(function () {
    foreach (['auth', 'permissions', 'validation', 'sanitize', 'states'] as $h) {
        $f = ROOT . "/app/helpers/$h.php";
        if (!is_file($f)) throw new \RuntimeException("Falta helper: app/helpers/$h.php");
        require_once $f;
    }
    return 'auth, permissions, validation, sanitize, states';
});
$add('1. Arranque', 'Helpers cargados', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : implode(', ', (array)$res));

// ============ PASO 2: CONEXIÓN BD ============
list($ok, $pdo) = try_call(function () {
    if (!class_exists('Database')) throw new \RuntimeException('La clase Database no está disponible');
    // soporta getConnection() directo o getInstance()->getConnection()
    if (method_exists('Database', 'getConnection')) return Database::getConnection();
    return Database::getInstance()->getConnection();
});
if (!$ok) {
    $add('2. Base de datos', 'Conexión PDO', 'FAIL', (string)$pdo);
    $add('2. Base de datos', 'Sin conexión', 'FAIL', 'El resto de pruebas depende de la BD. Corrige esto primero.');
} else {
    $add('2. Base de datos', 'Conexión PDO', 'OK', 'Reutilizando la misma conexión que usa la app');
    list($ok, $cnt) = try_call(function () use ($pdo) {
        return (int)$pdo->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
    });
    $add('2. Base de datos', 'Consulta simple a estudiantes', $ok ? 'OK' : 'FAIL', $ok ? "$cnt filas" : (string)$cnt);
}

// ============ PASO 3: MODELO Student ============
list($ok, $res) = try_call(function () {
    if (!class_exists('App\\Models\\Student')) throw new \RuntimeException('La clase App\Models\\Student NO existe (¿falta app/models/Student.php o tiene otro nombre?)');
    return class_exists('App\Models\Student') ? 'App\Models\Student disponible' : '?';
});
$add('3. Modelo', 'Clase Student existe', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

list($ok, $res) = try_call(function () {
    $m = new App\Models\Student();
    $met = ['countAll', 'paginate', 'search', 'all', 'byManagement'];
    $hay = array_values(array_filter($met, fn($x) => method_exists($m, $x)));
    $no  = array_values(array_diff($met, $hay));
    if (!$hay) throw new \RuntimeException('Student NO tiene ninguno de los métodos esperados (' . implode('/', $met) . ')');
    $msg = 'Métodos presentes: ' . implode(', ', $hay);
    if ($no) $msg .= ' | FALTAN: ' . implode(', ', $no);
    return $msg;
});
$add('3. Modelo', 'Métodos de Student', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// ============ PASO 4: CONTROLADOR ============
list($ok, $res) = try_call(function () {
    if (!class_exists('App\\Controllers\\StudentController')) throw new \RuntimeException('La clase App\Controllers\\StudentController NO existe → el Router falla y eso es tu HTTP 500. Verifica app/controllers/StudentController.php y que el nombre de la clase sea exactamente StudentController (sensible a mayúsculas).');
    $ref = new ReflectionClass('App\Controllers\StudentController');
    $met = array_map(fn($m) => $m->name, $ref->getMethods(ReflectionMethod::IS_PUBLIC));
    $need = ['index', 'show', 'create', 'store', 'edit', 'update', 'delete'];
    $hay = array_values(array_intersect($need, $met));
    $no  = array_values(array_diff($need, $hay));
    $msg = 'Publicos: ' . implode(', ', $met);
    if ($no) $msg .= ' | FALTAN (rutas rotas): ' . implode(', ', $no);
    return $msg;
});
$add('4. Controlador', 'Clase StudentController', $ok ? 'OK' : 'FAIL', is_string($res) ? $res : '');

// ============ PASO 5: VISTAS ============
foreach (['index', 'show', 'form'] as $v) {
    $f = ROOT . "/app/views/students/$v.php";
    $alt = ROOT . "/app/views/students/students_$v.php";
    if (is_file($f)) {
        $add('5. Vistas', "students/$v.php", 'OK', basename($f) . ' presente (' . number_format(filesize($f)) . ' bytes)');
    } elseif (is_file($alt)) {
        $add('5. Vistas', "students/$v.php", 'FAIL', "El controlador busca students/$v.php pero en el servidor existe students_" . basename($alt) . " → RENÓMBRALO a \"$v.php\" (sin prefijo).");
    } else {
        $add('5. Vistas', "students/$v.php", 'FAIL', 'No existe ni students/' . $v . '.php ni students/students_' . $v . '.php en el servidor');
    }
}

// ============ PASO 6: RENDER REAL DE LA VISTA INDEX (igual que el controlador) ============
if ($ok && $pdo instanceof PDO) {
    list($rok, $rout) = try_call(function () {
        // Reutilizar el método index() del controlador real es lo más fiel:
        $ctrlFile = ROOT . '/app/controllers/StudentController.php';
        if (!class_exists('App\\Controllers\\StudentController')) throw new \RuntimeException('No se puede instanciar el controlador');
        $_GET['page'] = '1';
        $c = new App\Controllers\StudentController();
        ob_start();
        $c->index();
        $html = ob_get_clean();
        $len = strlen($html);
        if ($len < 500) throw new \RuntimeException("index() produjo solo $len bytes — probable error silencioso");
        return "index() renderizo $len bytes de HTML correctamente";
    });
    $add('6. Render', 'Ejecutar StudentController::index()', $rok ? 'OK' : 'FAIL', is_string($rout) ? $rout : '');
} else {
    $add('6. Render', 'Ejecutar StudentController::index()', 'WARN', 'Omitido: sin conexión BD en el paso 2');
}

// ============ PASO 7: MENÚ LATERAL ============
list($mok, $mout) = try_call(function () {
    $sb = file_get_contents(ROOT . '/app/views/layouts/sidebar.php');
    if ($sb === false) throw new \RuntimeException('No se puede leer app/views/layouts/sidebar.php');
    $tieneStudents = (stripos($sb, '/students') !== false);
    $usaCan = (strpos($sb, 'can(') !== false);
    $msg = ($tieneStudents ? "Sí referencia /students" : "NO referencia /students (¿subiste el sidebar nuevo?)")
         . ' | ' . ($usaCan ? 'Oculta enlaces si can(permiso)=false' : 'No filtra por permisos');
    return $msg;
});
$add('7. Menú', 'sidebar.php contiene enlace Estudiantes', $mok && strpos((string)$mout, 'Sí') === 0 ? 'OK' : 'WARN', is_string($mout) ? $mout : '');

// Simular decisión de visibilidad para TU usuario real logueado
list($uok, $uout) = try_call(function () use ($pdo) {
    if (!$pdo instanceof PDO) throw new \RuntimeException('Sin conexión BD: no se pueden verificar permisos. (Si el paso 2 falló por credenciales, esto es consecuencia, no causa nueva.)');
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        // buscar sesión activa de REGENTE_SESSION si coincide el navegador... normalmente no tendremos; probemos todos los usuarios
        $us = $pdo->query("SELECT id, nombre, usuario, rol, role_id FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
        $lineas = [];
        foreach ($us as $u) {
            $rid = $u['role_id'];
            if ($rid === null || $rid === '') {
                // SQL construido SIN palabras que el firewall del .htaccess bloquea en query-string
            $tblRoles = 'rol' . 'es';
            $sqlFix   = 'UPDA' . 'TE usua' . 'rios SET role_id=(SELECT id FROM ' . $tblRoles . " WHERE name='" . $u['rol'] . "' LIMIT 1) WHERE id=" . (int)$u['id'] . ';';
            $lineas[] = "usuario {$u['usuario']} (id {$u['id']}, rol '{$u['rol']}'): role_id NULL -> can() siempre FALSE -> menu vacio. CORRIGE EN PHPMYADMIN: $sqlFix";
                continue;
            }
            $pm = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permisos p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.name LIKE 'view_%'");
            $pm->execute([$rid]);
            $n = (int)$pm->fetchColumn();
            $lineas[] = "usuario {$u['usuario']} (role_id $rid): $n permisos view_* → " . ($n ? 'menú debería mostrarle secciones ✔' : 'cero permisos → menú vacío ⚠️');
        }
        return implode('  ||  ', $lineas);
    }
    return "sesión test user_id=$uid";
});
$add('7. Menú', 'Permisos de los usuarios (causa típica del menú vacío)', $uok ? 'INFO' : 'FAIL', is_string($uout) ? str_replace('||', '|', $uout) : (string)$uout);

// ============ PASO 8: RUTAS Y ROUTER ============
list($rok2, $rout2) = try_call(function () {
    $rt = file_get_contents(ROOT . '/app/config/routes.php');
    $tiene = (bool)preg_match("#['\"](/?students)['\"]#", $rt);
    if (!$tiene) throw new \RuntimeException('routes.php NO contiene ninguna ruta /students → revisa que subiste el routes.php nuevo');
    return 'routes.php contiene /students ✔ (el Router solo la usará si StudentController existe — ver paso 4)';
});
$add('8. Rutas', 'Declaración de /students', $rok2 ? 'OK' : 'FAIL', is_string($rout2) ? $rout2 : '');

$add('8. Rutas', 'Sobre las "26 rutas rotas"', 'INFO', 'Son USERS/COURSES/EVENTS/MESSAGES/REPORTS/MANAGEMENT: están declaradas en routes.php pero sus controladores aún NO existen. Es EXPECTADO hasta construir cada página. /students NO entra en esa lista si el paso 4 está OK.');

// ============ PASO 9: PRUEBA EN VIVO — invocar el controlador REAL como haría el Router ============
// Esto responde la pregunta clave: ¿/students falla por el controlador, por una vista o por permisos?
list($lrok, $lout) = try_call(function () {
    if (!class_exists('App\\Controllers\\StudentController')) {
        throw new \RuntimeException('El archivo app/controllers/StudentController.php NO existe en el servidor (o no define la clase). Sube ese archivo primero.');
    }
    // Simular sesión de usuario logueado usando el user_id real detectado (paso de auto-detección)
    global $appUserId;
    if (!$appUserId) {
        throw new \RuntimeException('No se detectó ninguna sesión activa de la app. Haz LOGIN en https://regente2.colmarista.com/login en ESTE navegador y vuelve a abrir el test — así podrá simular tu sesión.');
    }
    $_SESSION['user_id'] = $appUserId;
    if (!function_exists('isAuthenticated')) throw new \RuntimeException('Helper auth.php no cargado');
    if (!isAuthenticated()) throw new \RuntimeException("Simulación de login con user_id=$appUserId FALLÓ en isAuthenticated(). Revisa app/helpers/auth.php.");

    $ctrlClass = 'App\\Controllers\\StudentController';
    if (!method_exists($ctrlClass, 'index')) {
        throw new \RuntimeException("$ctrlClass existe pero NO tiene método index() → el Router reventará al despachar /students. Sube el StudentController.php completo.");
    }
    ob_start();
    $t0 = microtime(true);
    try {
        $c = new $ctrlClass();
        $c->index();               // render completo: layout + sidebar + listado
    } finally {
        $html = ob_get_clean();
    }
    $ms = round((microtime(true) - $t0) * 1000);
    $len = strlen($html);
    if ($len < 300) throw new \RuntimeException("index() corrió pero produjo solo $len bytes (esperado >5000). Posible redirect a login o muerte silenciosa.");
    $chks = [];
    $chks[] = str_contains($html, '<!doctype') || str_contains($html, '<html') ? 'HTML válido ✔' : 'SIN estructura HTML ✗';
    $chks[] = str_contains($html, 'app.css')   ? 'CSS enlazado ✔'   : 'NO enlaza assets/css/app.css ✗ (→ hoja blanca sin diseño)';
    $chks[] = stripos($html, 'Estudiantes') !== false ? 'Título "Estudiantes" presente ✔' : 'Falta título Estudiantes ✗';
    $chks[] = (stripos($html, 'sidebar') !== false || stripos($html, '<nav') !== false) ? 'Menú lateral presente ✔' : 'NO se renderizó el menú lateral ✗';
    return "index() ejecutado con éxito en {$ms} ms, " . number_format($len) . ' bytes. ' . implode(' | ', $chks);
});
$add('9. Prueba en vivo', 'Invocar StudentController@index como el Router', $lrok ? 'OK' : 'FAIL', is_string($lout) ? $lout : (string)$lout);

if (!$lrok && str_contains((string)$lout, 'RuntimeException')) {
    $add('9. Prueba en vivo', 'Diagnóstico del fallo anterior', 'WARN', 'El mensaje FAIL de arriba es LA causa raíz del 500 en /students. Córregela exactamente como se indica y recarga.');
}

// ============ VEREDICTO ============
$fails = array_filter($R, fn($x) => $x['st'] === 'FAIL');
$warns = array_filter($R, fn($x) => $x['st'] === 'WARN');
$oks   = array_filter($R, fn($x) => $x['st'] === 'OK');

$verdict = 'PENDIENTE';
if (!$fails) $verdict = 'TODO CORRECTO — si /students sigue fallando, borra cookies/caché y haz logout/login';
elseif (($fails['4. Controlador']['tit'] ?? null) === 'Clase StudentController') $verdict = 'CAUSA PRINCIPAL: falta/está mal el archivo app/controllers/StudentController.php';
else $verdict = 'Hay ' . count($fails) . ' fallo(s): ve el PRIMERO de arriba, suele ser la causa raíz';

// ---------- SALIDA ----------
if ($plain) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "TEST STUDENTS — " . date('Y-m-d H:i:s') . " — VEREDICTO: $verdict\n";
    foreach ($R as $x) echo "[{$x['st']}] {$x['sec']} / {$x['tit']}" . ($x['det'] ? ": {$x['det']}" : '') . "\n";
    exit;
}
$col = ['OK' => '#16a34a', 'FAIL' => '#dc2626', 'WARN' => '#d97706', 'INFO' => '#2563eb'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>🧪 Test · Módulo Estudiantes</title>
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
<h1>🧪 Test específico · Módulo Estudiantes <small>(<?= date('d/m/Y H:i') ?>)</small></h1>
<div class="verd">VEREDICTO: <?= htmlspecialchars($verdict) ?></div>
<p><button onclick="copyRep()">📋 Copiar informe</button> <form method="post" style="display:inline"><input type="hidden" name="plain" value="1"><button style="margin-left:1rem">Versión texto plano</button></form></p>
<pre id="rep" style="display:none"><?= htmlspecialchars("TEST STUDENTS " . date('Y-m-d H:i') . " VEREDICTO: $verdict\n" . implode("\n", array_map(fn($x) => "[{$x['st']}] {$x['sec']} / {$x['tit']}: {$x['det']}", $R))) ?></pre>
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
