<?php
/**
 * ============================================================================
 * HERRAMIENTA DE DIAGNOSTICO - Sistema Regente (Colegio Marista)
 * ============================================================================
 *
 * Pagina autocontenida que verifica TODO el sistema y muestra resultados
 * visuales claros: entorno, estructura de archivos, autoloader, conexion DB,
 * esquema de tablas/columnas esperado vs real, rutas del Router, helpers,
 * middlewares, sesiones/seguridad, logs y vistas.
 *
 * USO:
 *   1) Abre en el navegador:  https://tudominio.com/diagnostico.php
 *   2) Protegida por clave. La clave se define aqui abajo (DIAG_KEY) o mejor
 *      aun en el archivo NO-PUBLICO app/config/diag_override.php:
 *          <?php define('DIAG_KEY', 'tu-clave-secreta');
 *      (el directorio app/ esta bloqueado por .htaccess, a diferencia de la raiz)
 *   3) Al abrir la pagina aparece un recuadro para escribir la clave; tambien
 *      puedes pasarla por URL:  /diagnostico.php?key=TU_CLAVE
 *   4) Al final hay botones: "Copiar informe" (texto listo para pegar en el
 *      chat) y "Descargar informe .txt".
 *
 * SEGURO DE EJECUTAR: solo LEE. No modifica datos; como maximo crea los
 * directorios logs/ y uploads/ si faltan (igual que index.php) y escribe un
 * archivo de prueba temporal que elimina enseguida.
 *
 * Modo compacto sin HTML (para CLI/cron):
 *      php diagnostico.php --plain --key=TU_CLAVE
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // capturamos todo nosotros mismos
date_default_timezone_set('America/Bogota');

// ----------------------------------------------------------------------------
// CONFIGURACION DE LA HERRAMIENTA
// ----------------------------------------------------------------------------
if (!defined('DIAG_KEY')) {
    // Cambia esta clave, o sobrescribela en app/config/diag_override.php
    define('DIAG_KEY', 'regente-diag-2026');
}
define('DIAG_SESSION_TTL', 7200); // 2 horas de acceso tras validar la clave

$override = __DIR__ . '/app/config/diag_override.php';
if (is_file($override)) {
    require_once $override; // permite redefinir DIAG_KEY fuera del documento publico
}

// ----------------------------------------------------------------------------
// CAPTURA GLOBAL DE FALLOS (nada debe "romper" la pagina en silencio)
// ----------------------------------------------------------------------------
$GLOBALS['diag_fatal'] = null;
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $GLOBALS['diag_fatal'] = $e;
    }
});

// ----------------------------------------------------------------------------
// CLASE DE REPORTE
// ----------------------------------------------------------------------------
class Diag
{
    public static $checks = [];     // todas las pruebas
    public static $logLines = [];   // log plano para copiar/exportar
    public static $section = '';

    public static function section($title)
    {
        self::$section = $title;
        self::$logLines[] = '';
        self::$logLines[] = '### ' . strtoupper($title);
    }

    /** status: pass | fail | warn | info */
    public static function check($name, $status, $message = '', $hint = '')
    {
        self::$checks[] = [
            'section' => self::$section,
            'name'    => $name,
            'status'  => $status,
            'message' => $message,
            'hint'    => $hint,
        ];
        $icon = ['pass' => '[OK]  ', 'fail' => '[FAIL]', 'warn' => '[WARN]', 'info' => '[INFO]'][$status] ?? '[?]';
        self::$logLines[] = sprintf('%s %s%s: %s', $icon, self::$section ? self::$section . ' / ' : '', $name, str_replace("\n", ' | ', $message));
        if ($hint) {
            self::$logLines[] = '       -> Sugerencia: ' . $hint;
        }
    }

    /** Ejecuta una prueba envolviendo en try/catch: nunca revienta la pagina */
    public static function run($name, callable $fn, $okMsg = null)
    {
        try {
            $result = $fn();
            if (is_array($result)) {
                [$status, $msg, $hint] = array_pad($result, 3, '');
            } else {
                $status = $result ? 'pass' : 'fail';
                $msg = is_string($result) ? $result : ($okMsg ?: 'OK');
                $hint = '';
            }
            self::check($name, $status, $msg, $hint);
            return $result;
        } catch (Throwable $e) {
            self::check(
                $name,
                'fail',
                get_class($e) . ': ' . $e->getMessage() . '  (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
                'Revisar la traza incluida arriba.'
            );
            return null;
        }
    }

    public static function counts()
    {
        $c = ['pass' => 0, 'fail' => 0, 'warn' => 0, 'info' => 0];
        foreach (self::$checks as $chk) {
            if (isset($c[$chk['status']])) $c[$chk['status']]++;
        }
        return $c;
    }

    public static function fullText()
    {
        $c = self::counts();
        $lines = [];
        $lines[] = '======================================================================';
        $lines[] = 'INFORME DE DIAGNOSTICO - SISTEMA REGENTE';
        $lines[] = 'Fecha: ' . date('Y-m-d H:i:s') . '  Host: ' . ($_SERVER['HTTP_HOST'] ?? 'CLI');
        $lines[] = 'PHP: ' . PHP_VERSION . '  OS: ' . PHP_OS;
        $lines[] = 'RESULTADO: ' . $c['pass'] . ' correctos, ' . $c['warn'] . ' advertencias, ' . $c['fail'] . ' errores';
        $lines[] = '======================================================================';
        $lines = array_merge($lines, self::$logLines);
        if ($GLOBALS['diag_fatal'] ?? null) {
            $lines[] = 'FATAL: ' . $GLOBALS['diag_fatal']['message'];
        }
        return implode("\n", $lines);
    }

    public static function renderHtml()
    {
        $c = self::counts();
        $verdict = $c['fail'] > 0
            ? ['danger', 'Se detectaron ERRORES que pueden romper el sistema. Revisa las filas rojas y pega este informe en el chat.']
            : ($c['warn'] > 0
                ? ['warning', 'El sistema funciona, pero hay advertencias que conviene atender.']
                : ['success', 'Todo en orden. El sistema paso todas las verificaciones.']);
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnostico - Sistema Regente</title>
<style>
:root{--azul:#133b64;--naranja:#f39200;}
*{box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,Arial,sans-serif;margin:0;background:#eef1f5;color:#222}
.wrap{max-width:1150px;margin:0 auto;padding:20px}
header{background:var(--azul);color:#fff;padding:18px 24px;border-bottom:4px solid var(--naranja)}
header h1{margin:0;font-size:22px}
header p{margin:4px 0 0;font-size:13px;opacity:.85}
.cards{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}
.card{flex:1;min-width:130px;background:#fff;border-radius:10px;padding:14px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.08);border-top:4px solid #ccc}
.card b{display:block;font-size:26px}
.card.pass{border-top-color:#28a745}.card.pass b{color:#28a745}
.card.fail{border-top-color:#dc3545}.card.fail b{color:#dc3545}
.card.warn{border-top-color:#ffc107}.card.warn b{color:#b8860b}
.card.info{border-top-color:#17a2b8}.card.info b{color:#17a2b8}
.verdict{border-radius:10px;padding:14px 18px;font-weight:600;margin:10px 0 20px}
.verdict.success{background:#d4edda;color:#155724;border-left:6px solid #28a745}
.verdict.warning{background:#fff3cd;color:#856404;border-left:6px solid #ffc107}
.verdict.danger{background:#f8d7da;color:#721c24;border-left:6px solid #dc3545}
h2.sec{color:var(--azul);margin:26px 0 6px;font-size:17px;border-bottom:2px solid var(--naranja);padding-bottom:4px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.07);margin-bottom:8px;font-size:13.5px}
th{background:var(--azul);color:#fff;text-align:left;padding:8px 10px;font-weight:600}
td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top}
tr:last-child td{border-bottom:none}
tr.s-fail td{background:#fdecea}
tr.s-warn td{background:#fff8dd}
.badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700;color:#fff;white-space:nowrap}
.b-pass{background:#28a745}.b-fail{background:#dc3545}.b-warn{background:#d39e00}.b-info{background:#17a2b8}
.hint{color:#7a5b00;font-size:12.5px;display:block;margin-top:3px}
pre.plain{background:#1e1e1e;color:#dcdcdc;padding:14px;border-radius:8px;font-size:12px;max-height:420px;overflow:auto;white-space:pre-wrap}
.toolbar{margin:16px 0;display:flex;gap:10px;flex-wrap:wrap}
.btn{cursor:pointer;border:none;border-radius:6px;padding:9px 16px;font-size:13.5px;font-weight:600;color:#fff;background:var(--azul)}
.btn.orange{background:var(--naranja)}
footer{margin:26px 0 10px;font-size:12px;color:#777;text-align:center}
.toggle{font-size:13px;color:var(--azul);cursor:pointer;margin:6px 0}
@media print{.toolbar,.toggle{display:none}}
</style>
</head>
<body>
<header>
  <h1>Diagnostico del Sistema Regente</h1>
  <p>Generado: <?= date('Y-m-d H:i:s') ?> · Host: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'CLI') ?> · PHP <?= PHP_VERSION ?></p>
</header>
<div class="wrap">

<?php if ($GLOBALS['diag_fatal'] ?? null): ?>
  <div class="verdict danger">FATAL: <?= htmlspecialchars($GLOBALS['diag_fatal']['message']) ?> en <?= htmlspecialchars(basename($GLOBALS['diag_fatal']['file'])) ?>:<?= $GLOBALS['diag_fatal']['line'] ?></div>
<?php endif; ?>

<div class="cards">
  <div class="card pass"><b><?= $c['pass'] ?></b>Correctos</div>
  <div class="card warn"><b><?= $c['warn'] ?></b>Advertencias</div>
  <div class="card fail"><b><?= $c['fail'] ?></b>Errores</div>
  <div class="card info"><b><?= $c['info'] ?></b>Informativos</div>
</div>

<div class="verdict <?= $verdict[0] ?>"><?= $verdict[1] ?></div>

<div class="toolbar">
  <button class="btn" onclick="copyReport(this)">Copiar informe (texto)</button>
  <button class="btn orange" onclick="downloadReport()">Descargar .txt</button>
  <button class="btn" onclick="toggleOnlyProblems(this)">Mostrar solo problemas</button>
  <button class="btn" onclick="window.print()">Imprimir / PDF</button>
</div>

<?php
$cur = null;
foreach (self::$checks as $chk) {
    if ($chk['section'] !== $cur) {
        if ($cur !== null) echo '</tbody></table>';
        $cur = $chk['section'];
        echo '<h2 class="sec">' . htmlspecialchars($cur) . '</h2>';
        echo '<table><thead><tr><th style="width:120px">Estado</th><th style="width:230px">Prueba</th><th>Detalle</th></tr></thead><tbody>';
    }
    $s = $chk['status'];
    $label = ['pass' => 'CORRECTO', 'fail' => 'ERROR', 'warn' => 'ADVERTENCIA', 'info' => 'INFO'][$s];
    echo '<tr class="s-' . $s . '" data-status="' . $s . '">';
    echo '<td><span class="badge b-' . $s . '">' . $label . '</span></td>';
    echo '<td><b>' . htmlspecialchars($chk['name']) . '</b></td>';
    echo '<td>' . nl2br(htmlspecialchars($chk['message']));
    if ($chk['hint']) echo '<span class="hint">&#9432; ' . htmlspecialchars($chk['hint']) . '</span>';
    echo '</td></tr>';
}
?>
</tbody></table>

<div class="toggle" onclick="var p=document.getElementById('plain');p.style.display=p.style.display==='none'?'block':'none'">▸ Ver informe en texto plano</div>
<pre class="plain" id="plain" style="display:none"><?= htmlspecialchars(self::fullText()) ?></pre>

<footer>Herramienta de diagnostico · Solo lectura · Las claves y contrasenas nunca se muestran completas.</footer>
</div>

<textarea id="reportText" style="position:absolute;left:-9999px"><?= htmlspecialchars(self::fullText()) ?></textarea>
<script>
function copyReport(btn){
  var t=document.getElementById('reportText');
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(t.value).then(function(){btn.textContent='¡Copiado! Pegalo en el chat';});
  }else{t.style.position='static';t.select();document.execCommand('copy');t.style.position='absolute';t.style.left='-9999px';btn.textContent='¡Copiado!';}
}
function downloadReport(){
  var blob=new Blob([document.getElementById('reportText').value],{type:'text/plain'});
  var a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download='diagnostico_'+new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')+'.txt';a.click();
}
var onlyProblems=false;
function toggleOnlyProblems(btn){
  onlyProblems=!onlyProblems;
  document.querySelectorAll('tr[data-status]').forEach(function(r){
    r.style.display=(onlyProblems&&(r.dataset.status==='pass'||r.dataset.status==='info'))?'none':'';
  });
  btn.textContent=onlyProblems?'Mostrar todo':'Mostrar solo problemas';
}
</script>
</body>
</html>
        <?php
    }
}

// ----------------------------------------------------------------------------
// GATE DE ACCESO (clave) — la pagina NO muestra nada sin clave valida
// ----------------------------------------------------------------------------
$isCli = PHP_SAPI === 'cli';

function diag_input($name)
{
    $v = $_GET[$name] ?? $_POST[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

$suppliedKey = diag_input('key') ?: diag_input('token');
if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (preg_match('/^--key=(.+)$/', $arg, $m)) $suppliedKey = trim($m[1]);
    }
}

$sessionOk = false;
if (!$isCli) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (!empty($_SESSION['diag_ok']) && (time() - ($_SESSION['diag_time'] ?? 0)) < DIAG_SESSION_TTL) {
        $sessionOk = true;
    }
}
$keyValid = $suppliedKey !== '' && hash_equals(DIAG_KEY, $suppliedKey);
if ($keyValid && !$isCli) {
    $_SESSION['diag_ok'] = true;
    $_SESSION['diag_time'] = time();
    $sessionOk = true;
}
$authorized = $isCli ? $keyValid : $sessionOk;

if (!$authorized) {
    http_response_code(401);
    if ($isCli) {
        fwrite(STDERR, "Acceso denegado. Uso: php diagnostico.php --plain --key=TU_CLAVE\n");
        exit(1);
    }
    header('Content-Type: text/html; charset=UTF-8');
    $err = $suppliedKey !== '' ? '<p style="color:#dc3545;font-weight:600">Clave incorrecta.</p>' : '';
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnostico - Acceso</title>'
       . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#eef1f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
       . '.box{background:#fff;padding:34px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.12);max-width:420px;width:90%;border-top:5px solid #f39200}'
       . 'h1{color:#133b64;font-size:20px;margin-top:0}input{width:100%;padding:10px;margin:10px 0;border:1px solid #ccc;border-radius:6px}'
       . 'button{width:100%;padding:11px;background:#133b64;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer}small{color:#777}</style></head><body>'
       . '<form method="post" class="box"><h1>Diagnostico - Sistema Regente</h1><p>Herramienta privada. Ingresa la clave de acceso.</p>'
       . $err
       . '<input type="password" name="key" placeholder="Clave de diagnostico" autofocus required autocomplete="off">'
       . '<button type="submit">Entrar</button>'
       . '<p><small>Tambien via URL: <code>/diagnostico.php?key=TU_CLAVE</code><br>Define tu clave propia en <code>app/config/diag_override.php</code> (recomendado, esa carpeta no es publica).</small></p>'
       . '</form></body></html>';
    exit;
}

if (!$isCli) {
    header('Cache-Control: no-store, max-age=0');
}

// ============================================================================
// 1. ENTORNO
// ============================================================================
Diag::section('1. Entorno');

Diag::run('Version de PHP >= 8.0', fn() => version_compare(PHP_VERSION, '8.0.0', '>=')
    ? ['pass', 'PHP ' . PHP_VERSION . ' (' . PHP_OS . ')']
    : ['fail', 'PHP ' . PHP_VERSION . ' es muy antigua', 'Subir a PHP 8.1+ desde el panel del hosting'], 'PHP ' . PHP_VERSION);
Diag::check('Zona horaria', 'info', date_default_timezone_get() . ' — hora del servidor: ' . date('Y-m-d H:i:s'));

foreach ([
    'pdo_mysql'  => 'IMPRESCINDIBLE: sin este modulo la conexion a la base de datos falla con "could not find driver"',
    'mbstring'   => 'NECESARIO: manejo correcto de UTF-8 (nombres, mensajes WhatsApp)',
    'session'    => 'NECESARIO: login y proteccion CSRF',
    'json'       => 'NECESARIO: respuestas AJAX',
    'openssl'    => 'NECESARIO: HTTPS y reCAPTCHA',
    'curl'       => 'Importante cuando integres la API de WhatsApp',
    'fileinfo'   => 'Util para validacion de subidas de archivos',
    'gd'         => 'Opcional: requerido para generar codigos QR',
] as $ext => $why) {
    if (extension_loaded($ext)) {
        Diag::check("Extension: $ext", 'pass', 'Cargada');
    } else {
        $critical = in_array($ext, ['pdo_mysql', 'mbstring', 'session', 'json'], true);
        Diag::check("Extension: $ext", $critical ? 'fail' : 'warn', 'NO esta cargada', $why);
    }
}

$cfgLines = [];
foreach (['display_errors', 'log_errors', 'error_log', 'memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'session.cookie_httponly', 'session.cookie_secure'] as $k) {
    $cfgLines[] = "$k = " . (ini_get($k) !== '' ? ini_get($k) : '(vacio)');
}
Diag::check('Configuracion PHP activa', 'info', implode("\n", $cfgLines));

if (ini_get('display_errors') && !$isCli) {
    Diag::check('display_errors en produccion', 'warn', 'Esta encendido: puede filtrar informacion sensible al publico', 'Ponlo en 0 en produccion; esta herramienta ya captura los errores por su cuenta.');
}

$https = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower($_SERVER['HTTPS']) !== 'off')
      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
Diag::check('Protocolo de la peticion', $https ? 'pass' : 'warn', $https ? 'HTTPS' : 'HTTP sin cifrado', $https ? '' : 'Activa el SSL del dominio; con session.cookie_secure=1 el login fallara por HTTP.');

// ============================================================================
// 2. ESTRUCTURA DE ARCHIVOS Y PERMISOS
// ============================================================================
Diag::section('2. Estructura de archivos');

$root = __DIR__; // public_html

foreach (['app', 'app/config', 'app/models', 'app/controllers', 'app/views', 'app/helpers', 'app/middleware', 'app/views/layouts', 'assets', 'assets/css', 'assets/js'] as $d) {
    Diag::run("Directorio $d/", fn() => is_dir("$root/$d") ? 'pass' : ['fail', "No existe: $root/$d", 'Restaurar el directorio desde el repositorio git']);
}

foreach (['logs', 'uploads'] as $d) {
    Diag::run("Directorio $d/ (escritura)", function () use ($root, $d) {
        $path = "$root/$d";
        if (!is_dir($path)) {
            if (@mkdir($path, 0755, true)) {
                return ['warn', "No existia; se creo ahora mismo ($path)", 'Verifica que el owner permita escribir a PHP de forma permanente'];
            }
            return ['fail', "No existe y no se pudo crear: $path", 'Crear manualmente con chmod 755 y dueño = usuario de PHP'];
        }
        return is_writable($path) ? ['pass', "$path existe y es escribible"]
            : ['fail', "$path no tiene permisos de escritura", 'chmod 755 / chown al usuario de PHP (www-data, nobody, etc.)'];
    });
}

$criticalFiles = [
    'index.php', 'Router.php', '.htaccess',
    'app/App.php', 'app/config/autoload.php', 'app/config/database.php',
    'app/config/routes.php', 'app/config/security.php', 'app/config/recaptcha.php',
    'app/models/Model.php', 'app/models/User.php', 'app/models/Student.php',
    'app/models/Course.php', 'app/models/Event.php', 'app/models/Message.php',
    'app/models/Role.php', 'app/models/Permission.php', 'app/models/Management.php',
    'app/controllers/Controller.php', 'app/controllers/AuthController.php', 'app/controllers/DashboardController.php',
    'app/helpers/auth.php', 'app/helpers/permissions.php', 'app/helpers/validation.php', 'app/helpers/sanitize.php', 'app/helpers/recaptcha.php',
    'app/middleware/AuthMiddleware.php', 'app/middleware/CsrfMiddleware.php', 'app/middleware/PermissionMiddleware.php',
    'app/middleware/RateLimitMiddleware.php', 'app/middleware/SecurityHeadersMiddleware.php',
    'app/views/layouts/app.php', 'app/views/layouts/sidebar.php', 'app/views/layouts/header.php', 'app/views/layouts/footer.php',
    'app/views/dashboard/index.php', 'app/views/auth/login.php',
    'app/views/errors/404.php', 'app/views/errors/500.php', 'app/views/errors/403.php',
    'assets/css/app.css', 'assets/js/app.js',
];
foreach ($criticalFiles as $f) {
    $path = "$root/$f";
    if (!file_exists($path)) {
        Diag::check("Archivo $f", 'fail', 'NO EXISTE', 'Restaurarlo desde el repositorio git.');
        continue;
    }
    if (!is_readable($path)) {
        Diag::check("Archivo $f", 'fail', 'Existe pero NO es legible', 'Revisar permisos (chmod 644).');
        continue;
    }
    Diag::check("Archivo $f", 'pass', 'Existe y es legible (' . number_format(filesize($path)) . ' bytes)');
}

// Chequeo de sintaxis PHP de todos los archivos del proyecto (php -l si exec esta disponible)
$syntaxTool = function_exists('exec');
Diag::check('Chequeo de sintaxis PHP', 'info', $syntaxTool
    ? 'Usando "php -l" sobre cada archivo .php del proyecto'
    : 'La funcion exec() esta deshabilitada: se omite el chequeo "php -l" (el resto del diagnostico cubre los fallos principales)');
if ($syntaxTool) {
    $bad = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/app", FilesystemIterator::SKIP_DOTS));
    $files = array_merge(iterator_to_array($rii), [new SplFileInfo("$root/index.php"), new SplFileInfo("$root/Router.php")]);
    foreach ($files as $fi) {
        if ($fi->isFile() && $fi->getExtension() === 'php') {
            $out = []; $rc = 0;
            @exec('php -l ' . escapeshellarg($fi->getPathname()) . ' 2>&1', $out, $rc);
            if ($rc !== 0) $bad[] = str_replace("$root/", '', $fi->getPathname()) . ': ' . trim(implode(' ', $out));
        }
    }
    Diag::run('Sintaxis de todos los .php', fn() => $bad
        ? ['fail', implode("\n", $bad), 'Corregir los errores de sintaxis listados; esos archivos dan 500 al cargarse']
        : ['pass', 'Todos los archivos PHP tienen sintaxis valida']);
}

// ============================================================================
// 3. AUTOLOADER Y CLASES
// ============================================================================
Diag::section('3. Autoloader y clases');

Diag::run('Carga de app/config/autoload.php', function () use ($root) {
    if (!is_file("$root/app/config/autoload.php")) return ['fail', 'autoload.php no existe'];
    require_once "$root/app/config/autoload.php";
    return defined('APP_ROOT')
        ? ['pass', 'Autoloader cargado. APP_ROOT = ' . APP_ROOT]
        : ['fail', 'Autoloader cargado pero APP_ROOT no quedó definido', 'Revisar el define(APP_ROOT...) en autoload.php'];
});

Diag::run('APP_ROOT apunta a public_html', function () use ($root) {
    if (!defined('APP_ROOT')) return ['fail', 'APP_ROOT no definido'];
    return realpath(APP_ROOT) === realpath($root)
        ? ['pass', APP_ROOT]
        : ['fail', 'APP_ROOT=' . APP_ROOT . ' | esperado=' . $root, 'Inconsistencia de rutas: causara errores 500 al cargar clases/vistas'];
});

// La clase App NO se autoloadear: index.php la hace require manualmente.
// El diagnostico debe replicar ese require para probarla en igualdad de condiciones.
if (is_file("$root/app/App.php")) require_once "$root/app/App.php";

foreach ([
    'Database', 'Router', 'App',
    'App\Models\Model', 'App\Models\User', 'App\Models\Student', 'App\Models\Course',
    'App\Models\Event', 'App\Models\Message', 'App\Models\Role', 'App\Models\Permission', 'App\Models\Management',
    'App\Controllers\Controller', 'App\Controllers\AuthController', 'App\Controllers\DashboardController',
    'App\Middleware\AuthMiddleware', 'App\Middleware\CsrfMiddleware', 'App\Middleware\PermissionMiddleware',
    'App\Middleware\RateLimitMiddleware', 'App\Middleware\SecurityHeadersMiddleware',
] as $cls) {
    Diag::run("Clase $cls", fn() => class_exists($cls)
        ? ['pass', 'Disponible via autoloader']
        : ['fail', 'NO se puede cargar', 'El autoloader no encontro el archivo, o cambio el namespace/nombre de clase']);
}

// ============================================================================
// 4. BASE DE DATOS
// ============================================================================
Diag::section('4. Base de datos');

$db = null;
Diag::run('Conexion PDO', function () use (&$db) {
    $db = Database::getConnection();
    return $db instanceof PDO
        ? ['pass', 'Conectado a "' . (defined('DB_NAME') ? DB_NAME : '?') . '" como ' . (defined('DB_USER') ? DB_USER : '?') . ' (host: ' . (defined('DB_HOST') ? DB_HOST : '?') . ')']
        : ['fail', 'Database::getConnection() no devolvio un objeto PDO valido'];
}, 'Conectado');

if ($db instanceof PDO) {
    Diag::check('Version de MySQL/MariaDB', 'info', trim($db->query('SELECT VERSION()')->fetchColumn()));

    // --- Esquema esperado segun los modelos de la aplicacion ---
    $expectedTables = [
        'usuarios'           => 'login del sistema (App\Models\User)',
        'roles'              => 'roles de usuarios (App\Models\Role)',
        'permisos'           => 'permisos granulares (App\Models\Permission)',
        'role_permissions'   => 'pivot rol<->permiso (App\Models\Role)',
        'gestion'            => 'anios escolares (App\Models\Management)',
        'cursos'             => 'cursos (App\Models\Course)',
        'estudiantes'        => 'estudiantes (App\Models\Student)',
        'eventos'            => 'atrasos/faltas/licencias (App\Models\Event)',
        'mensajes_enviados'  => 'historial de WhatsApp (App\Models\Message)',
    ];

    $existing = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $existingLower = array_map('strtolower', $existing);
    Diag::check('Tablas encontradas', count($existing) > 0 ? 'info' : 'warn', count($existing) . " tablas en la BD:\n" . implode(', ', $existing));

    foreach ($expectedTables as $t => $desc) {
        if (in_array(strtolower($t), $existingLower, true)) {
            Diag::check("Tabla '$t'", 'pass', "Existe — necesaria para: $desc");
        } else {
            Diag::check("Tabla '$t'", 'fail', "NO EXISTE — necesaria para: $desc", 'Los modelos que la consultan daran error SQL. Crearla o importar el SQL de estructura.');
        }
    }

    // Columnas criticas esperadas por las consultas existentes en los modelos
    $expectedColumns = [
        'usuarios'          => ['id', 'nombre', 'apellido', 'usuario', 'contrasena', 'rol', 'role_id', 'estado'],
        'estudiantes'       => ['id', 'nombre_completo', 'codigo_bnb', 'id_curso', 'id_gestion'],
        'eventos'           => ['id', 'id_estudiante', 'tipo_evento', 'fecha_evento'],
        'mensajes_enviados' => ['id', 'id_estudiante', 'destinatario', 'fecha_envio'],
        'gestion'           => ['id', 'estado'],
        'permisos'          => ['id', 'name'],
        'role_permissions'  => ['role_id', 'permission_id'],
    ];
    foreach ($expectedColumns as $table => $cols) {
        if (!in_array(strtolower($table), $existingLower, true)) continue;
        Diag::run("Columnas de '$table'", function () use ($db, $table, $cols) {
            $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $real = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $faltan = array_values(array_diff($cols, $real));
            if (!$faltan) return ['pass', count($real) . ' columnas; presentes todas las esperadas (' . implode(', ', $cols) . ')'];
            return ['fail', 'FALTAN: ' . implode(', ', $faltan) . "\nReales: " . implode(', ', $real),
                    'Las consultas del modelo usan esas columnas: habra errores SQL en vivo. Alinear el esquema o ajustar el modelo.'];
        });
    }

    // Conteo de registros (indica si la BD esta sembrada o vacia)
    foreach (array_keys($expectedTables) as $t) {
        if (!in_array(strtolower($t), $existingLower, true)) continue;
        Diag::run("Registros en '$t'", function () use ($db, $t) {
            $n = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            if ($n === 0 && $t === 'usuarios') {
                return ['warn', '0 usuarios', 'App.php creara un admin inicial (admin/admin123) al primer request normal. CAMBIA esa clave de inmediato.'];
            }
            return [$n > 0 ? 'pass' : 'info', number_format($n) . ' fila(s)'];
        });
    }

    Diag::run('Codificacion de la base', function () use ($db) {
        $row = $db->query('SELECT DEFAULT_CHARACTER_SET_NAME cs, DEFAULT_COLLATION_NAME coll FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()')->fetch();
        $ok = stripos($row['cs'] ?? '', 'utf8mb4') !== false;
        return $ok
            ? ['pass', $row['cs'] . ' / ' . $row['coll']]
            : ['warn', ($row['cs'] ?? '?') . ' / ' . ($row['coll'] ?? '?'), 'Recomendado utf8mb4 / utf8mb4_unicode_ci (emojis de WhatsApp, ñ, acentos)'];
    });

    Diag::run('Prueba UTF-8 end-to-end', function () use ($db) {
        $txt = 'Prueba ñ, á, é, í, ó, ú, ü, ¿? ¡Hola! ✅ 📚';
        $res = $db->query('SELECT ' . $db->quote($txt) . ' AS t')->fetch();
        return ($res['t'] ?? '') === $txt
            ? ['pass', 'Ida y vuelta correcta con acentos y emojis']
            : ['fail', 'El texto volvio distinto: problema de charset en la conexion', 'Verificar DB_CHARSET=utf8mb4 en app/config/database.php'];
    });
} else {
    Diag::check('Resto de pruebas de BD', 'fail', 'Omitidas: sin conexion, todo el sistema estara caido.', 'Corregir credenciales en app/config/database.php, verificar que MySQL este activo y que el usuario tenga privilegios sobre la BD.');
}

// ============================================================================
// 5. RUTAS Y CONTROLADORES
// ============================================================================
Diag::section('5. Rutas del sistema');

Diag::run('Router cargado', fn() => class_exists('Router') ? 'pass' : ['fail', 'Clase Router no disponible']);

Diag::run('Rutas de routes.php -> controladores/metodos', function () use ($root) {
    $src = file_get_contents("$root/app/config/routes.php");
    preg_match_all('/Router::(get|post|put|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)@([^\'"]+)[\'"]/i', $src, $m, PREG_SET_ORDER);
    if (!$m) return ['fail', 'No se detecto ninguna ruta en routes.php'];
    $bad = [];
    foreach ($m as [$full, $verb, $path, $ctrl, $method]) {
        if (!class_exists($ctrl)) { $bad[] = "$verb $path -> controlador INEXISTENTE: $ctrl"; continue; }
        if (!method_exists($ctrl, $method)) $bad[] = "$verb $path -> metodo INEXISTENTE: $ctrl::$method()";
    }
    if ($bad) return ['fail', count($bad) . " rutas rotas:\n" . implode("\n", $bad), 'Esas URLs daran 500/404 hasta crear los controladores o quitar las rutas.'];
    return ['pass', count($m) . ' rutas definidas; todos los controladores y metodos referenciados existen'];
});

foreach (['/', '/login', '/logout', '/dashboard'] as $p) {
    Diag::run("Ruta activa GET $p", function () use ($p) {
        $rp = new ReflectionProperty('Router', 'routes');
        $rp->setAccessible(true);
        foreach ($rp->getValue(null) as $r) {
            if ($r['path'] === $p && $r['method'] === 'GET') {
                return ['pass', 'handler: ' . (is_string($r['handler']) ? $r['handler'] : 'closure')];
            }
        }
        return ['fail', 'No registrada en el Router', 'Esa URL dara 404. Revisar routes.php / autoload.php'];
    });
}

Diag::run('Middlewares referenciados en rutas', function () use ($root) {
    preg_match_all('/App\\\\Middleware\\\\\w+/', file_get_contents("$root/app/config/routes.php"), $mm);
    $list = array_values(array_unique($mm[0]));
    $missing = array_filter($list, fn($c) => !class_exists($c));
    return $missing
        ? ['fail', 'Inexistentes: ' . implode(', ', $missing), 'Las rutas que los usan daran error al ejecutarlos']
        : ['pass', implode(', ', array_map(fn($c) => substr($c, strrpos($c, '\\') + 1), $list)) . ' — todos disponibles'];
});

// ============================================================================
// 6. HELPERS Y FUNCIONES GLOBALES
// ============================================================================
Diag::section('6. Helpers de la aplicacion');

// Funciones globales REALES que usan vistas/controladores hoy (definidas por helpers propios)
foreach ([
    'can', 'cannot', 'isAdmin', 'isRegente', 'isProfesor', 'hasRole',
    'event_tag_class', 'event_label',
    'isAuthenticated', 'user', 'userId', 'userRole', 'userRoleId', 'login', 'logout',
] as $fn) {
    Diag::run("Funcion global $fn()", function () use ($fn, $root) {
        if (!function_exists($fn)) {
            return ['fail', "NO existe", 'Las vistas/controladores que la llamen crantaran con "undefined function" (error 500). Definirla en app/helpers/'];
        }
        $rf = (new ReflectionFunction($fn))->getFileName();
        return ['pass', 'Definida en ' . str_replace("$root/", '', $rf)];
    });
}

// CSFR: verificar que exista alguna funcion/helper de token CSRF
Diag::run('Soporte CSRF', function () {
    $candidates = ['csrf_field', 'csrf_token', 'getCsrfToken', 'generateCsrfToken', 'csrf_input'];
    $found = array_values(array_filter($candidates, 'function_exists'));
    if ($found) return ['pass', 'Detectado: ' . implode(', ', $found)];
    if (class_exists('App\Middleware\CsrfMiddleware')) {
        $methods = array_map(fn($m) => $m->name, (new ReflectionClass('App\Middleware\CsrfMiddleware'))->getMethods(ReflectionMethod::IS_PUBLIC));
        return ['info', 'Sin funciones globales; CsrfMiddleware expone: ' . implode(', ', $methods)];
    }
    return ['warn', 'No se detecto helper CSRF global', 'Verifica como generan el token las vistas de formularios.'];
});

Diag::run('can() ejecuta sin errores', function () {
    if (!function_exists('can')) return ['fail', 'can() no definida'];
    $r = can('view_events');
    return ['info', 'can("view_events") devolvio: ' . var_export($r, true) . ' (sesion de diagnostico, sin usuario logueado: lo esperado es false)'];
});

// ============================================================================
// 7. SEGURIDAD
// ============================================================================
Diag::section('7. Seguridad');

Diag::run('config/security.php', function () use ($root) {
    if (!is_file("$root/app/config/security.php")) return ['fail', 'no existe'];
    require_once "$root/app/config/security.php";
    $names = ['SESSION_NAME', 'CSRF_TOKEN_NAME', 'RATE_LIMIT_LOGIN_ATTEMPTS', 'PASSWORD_HASH_ALGO'];
    $parts = [];
    $undef = [];
    foreach ($names as $n) {
        if (defined($n)) $parts[] = "$n=" . constant($n);
        else $undef[] = $n;
    }
    return $undef
        ? ['warn', 'No definidos: ' . implode(', ', $undef), 'Algunos middlewares podrian usar valores por defecto']
        : ['pass', implode(' | ', $parts)];
});

Diag::run('config/recaptcha.php', function () use ($root) {
    if (!is_file("$root/app/config/recaptcha.php")) return ['fail', 'no existe'];
    require_once "$root/app/config/recaptcha.php";
    $consts = ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'];
    $miss = array_filter($consts, fn($c) => !defined($c) || trim((string)constant($c)) === '' || stripos((string)constant($c), 'xxxx') !== false || stripos((string)constant($c), 'tu_') !== false);
    return $miss
        ? ['warn', 'Sin configurar: ' . implode(', ', $miss), 'El login con reCAPTCHA puede fallar hasta colocar claves reales de Google']
        : ['pass', 'Claves definidas (valores ocultos)'];
});

Diag::check('Sesion', 'info', 'estado: ' . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVA (usada por el propio diagnostico)' : 'INACTIVA')
    . ' | save_handler: ' . ini_get('session.save_handler')
    . ' | cookie_httponly: ' . (ini_get('session.cookie_httponly') ? '1' : '0')
    . ' | cookie_secure: ' . (ini_get('session.cookie_secure') ? '1' : '0'));

if (!$https && ini_get('session.cookie_secure')) {
    Diag::check('cookie_secure=1 accediendo por HTTP', 'fail', 'Las cookies de sesion NO se enviaran: el login parecera fallar siempre (te expulsa a /login)', 'Activar SSL del dominio o desactivar session.cookie_secure mientras tanto.');
}

// Advertencia de seguridad: PASSWORD_HASH_ALGO = plain significa contrasenas sin hashear en la BD
if (defined('PASSWORD_HASH_ALGO') && strtolower((string)PASSWORD_HASH_ALGO) === 'plain') {
    Diag::check('Algoritmo de contrasenas "plain"', 'warn',
        'security.php define PASSWORD_HASH_ALGO=plain: las contrasenas se guardan SIN cifrar en la base de datos',
        'Riesgo alto. Migrar a password_hash()/bcrypt y rehashear las contrasenas existentes.');
}

Diag::run('Hashing de contrasenas', fn() => password_hash('prueba', PASSWORD_DEFAULT) !== false
    ? ['pass', 'password_hash() funcional (algo: ' . PASSWORD_DEFAULT . ')']
    : ['fail', 'password_hash() fallo']);

Diag::run('.htaccess', function () use ($root) {
    $src = is_file("$root/.htaccess") ? file_get_contents("$root/.htaccess") : '';
    if (!$src) return ['fail', 'No existe .htaccess', 'El enrutamiento y la proteccion de app/ dependen de el'];
    $issues = [];
    if (stripos($src, 'RewriteRule') === false) $issues[] = 'sin regla de reescritura hacia index.php';
    if (stripos($src, 'DirectoryMatch') === false && stripos($src, 'RewriteRule .*app') === false) $issues[] = 'app/ y logs/ no parecen bloqueados explicitamente';
    return $issues ? ['warn', implode('; ', $issues)] : ['pass', 'rewrite + bloqueo de directorios sensibles presente'];
});

// Prueba real: intentar descargar un archivo privado via HTTP (solo si hay host)
if (!$isCli && !empty($_SERVER['HTTP_HOST'])) {
    Diag::run('Bloqueo web de app/config/database.php', function () {
        $url = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/app/config/database.php';
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        if ($body !== false && strpos($body, 'DB_PASS') !== false) {
            return ['fail', '¡EXPUESTO! Se puede descargar database.php por HTTP', 'URGENTE: rotar la clave de BD y arreglar .htaccess ahora mismo.'];
        }
        return ['pass', "Acceso denegado por el servidor (HTTP $code)"];
    });
}

// ============================================================================
// 8. LOGS
// ============================================================================
Diag::section('8. Logs');

$logDir = "$root/logs";
Diag::check('Ruta de log', 'info', 'error_log() apuntaria a: ' . (ini_get('error_log') ?: $logDir . '/error.log'));

Diag::run('Escritura de log de prueba', function () use ($logDir) {
    if (!is_dir($logDir) && !@mkdir($logDir, 0755, true)) return ['fail', "No se puede crear $logDir"];
    $f = "$logDir/error.log";
    $ok = @file_put_contents($f, '[' . date('Y-m-d H:i:s') . "] DIAGNOSTICO: prueba de escritura OK\n", FILE_APPEND) !== false;
    return $ok ? ['pass', "Se escribio correctamente en $f"] : ['fail', "No se puede escribir en $f", 'Permisos del directorio logs/'];
});

$foundLogs = glob("$logDir/*.log") ?: [];
if ($foundLogs) {
    foreach ($foundLogs as $f) {
        $size = filesize($f);
        $lines = array_slice(array_values(array_filter(explode("\n", (string)file_get_contents($f)))), -10);
        $errLines = array_values(array_filter($lines, fn($l) => (bool)preg_match('/(Fatal|Uncaught|Parse error|Warning|Notice|Deprecated|error)/i', $l)));
        Diag::check('Contenido de ' . basename($f), $errLines ? 'warn' : 'info',
            'Tamano: ' . number_format($size) . " bytes. Ultimas lineas relevantes:\n" . implode("\n", $errLines ?: $lines),
            $size > 2 * 1024 * 1024 ? 'Log mayor a 2 MB: considera rotarlo/truncarlo.' : ($errLines ? 'Hay errores recientes registrados: revisalos.' : ''));
    }
} else {
    Diag::check('Archivos de log', 'info', 'Aun no hay archivos *.log en logs/ (normal si todavia no ocurrio nada que registrar)');
}

// ============================================================================
// 9. VISTAS PRINCIPALES (smoke test estatico)
// ============================================================================
Diag::section('9. Vistas (revision estatica)');

// Funciones globales propias del framework + builtins de PHP que son validas en vistas
$ownGlobals = [];
foreach ((new ReflectionExtension('standard'))->getFunctions() as $rfn) $ownGlobals[strtolower($rfn->getName())] = true;
foreach (['pdo','mbstring','json','session','date','openssl','curl','gd','fileinfo'] as $extName) {
    if (extension_loaded($extName)) {
        foreach ((new ReflectionExtension($extName))->getFunctions() as $rfn) $ownGlobals[strtolower($rfn->getName())] = true;
    }
}
$language = ['if','foreach','for','while','switch','function','isset','empty','unset','echo','print','list','array','extract','compact','require','require_once','include','include_once','match','use','catch','new','return','and','or','xor','fn','endif','endforeach','endwhile','endswitch','exit','die','try','finally','else','elseif','instanceof','class','interface','trait','enum','static','public','private','protected','const','namespace','global','yield','clone','throw'];
foreach ($language as $w) $ownGlobals[$w] = true;

foreach (['app/views/auth/login.php', 'app/views/dashboard/index.php', 'app/views/layouts/app.php', 'app/views/layouts/sidebar.php', 'app/views/layouts/header.php', 'app/views/layouts/footer.php'] as $v) {
    Diag::run("Vista $v", function () use ($root, $v, $ownGlobals) {
        $file = "$root/$v";
        if (!is_file($file)) return ['fail', 'no existe', 'La ruta o el controller que la renderiza fallara'];
        // Analizar SOLO los bloques PHP, ignorando HTML/JS/CSS embebido
        $src = file_get_contents($file);
        $phpCode = '';
        foreach (token_get_all($src) as $tok) {
            if (is_array($tok) && in_array($tok[0], [T_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $phpCode .= $tok[1] . ' ';
            } elseif (is_array($tok) && $tok[0] === T_INLINE_HTML) {
                continue; // HTML/JS puro: ignorar
            }
        }
        preg_match_all('/(?<![a-zA-Z0-9_$\\\\])([a-z_][a-z0-9_]*)\s*\(/i', $phpCode, $mm);
        $missing = [];
        foreach (array_unique($mm[1]) as $fnName) {
            $low = strtolower($fnName);
            if (isset($ownGlobals[$low])) continue;
            if (!function_exists($fnName)) $missing[] = $fnName . '()';
        }
        return $missing
            ? ['warn', 'Llama funciones PHP que NO existen (causa tipica de 500): ' . implode(', ', $missing), 'Definirlas en app/helpers/ o corregir el nombre en la vista.']
            : ['pass', 'Archivo presente; todas las llamadas a funciones globales estan definidas'];
    });
}

// ============================================================================
// CIERRE
// ============================================================================
if ($GLOBALS['diag_fatal'] ?? null) {
    Diag::check('Error fatal durante el diagnostico', 'fail', $GLOBALS['diag_fatal']['message'] . ' en ' . basename($GLOBALS['diag_fatal']['file']) . ':' . $GLOBALS['diag_fatal']['line']);
}

$c = Diag::counts();
Diag::check('RESULTADO FINAL', $c['fail'] ? 'fail' : ($c['warn'] ? 'warn' : 'pass'),
    $c['fail'] . ' errores, ' . $c['warn'] . ' advertencias, ' . $c['pass'] . ' correctos, ' . $c['info'] . ' informativos');

if ($isCli) {
    echo Diag::fullText() . "\n";
    exit($c['fail'] ? 2 : 0);
}

Diag::renderHtml();
