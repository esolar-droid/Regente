<?php
/**
 * PAGINA DE DIAGNOSTICO COMPLETO
 * 
 * Prueba: SQL, codificacion, estructura, autoloader, logs, etc.
 * 
 * Acceso: regente.colmarista.com/diagnostico.php
 */

// ============================================================================
// CONFIGURACION INICIAL
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('html_errors', 1);

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// ============================================================================
// FUNCIONES DE PRUEBA
// ============================================================================

class Diagnostico {
    
    private static $results = [];
    private static $errors = [];
    private static $warnings = [];
    
    public static function init() {
        ob_start();
        echo "<html><head><title>Diagnostico - Regente</title>";
        echo "<meta charset='UTF-8'><style>";
        echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f5f5f5; }";
        echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
        echo "h1 { color: #133b64; border-bottom: 3px solid #f39200; padding-bottom: 10px; }";
        echo "h2 { color: #6a8cc0; margin-top: 30px; }";
        echo ".section { background: #fef2e3; padding: 15px; margin: 15px 0; border-left: 4px solid #f39200; border-radius: 4px; }";
        echo ".success { background: #d4edda; border-left-color: #28a745; color: #155724; }";
        echo ".error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }";
        echo ".warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }";
        echo ".info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }";
        echo "pre { background: #2d2d2d; color: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; margin: 10px 0; }";
        echo "table { width: 100%; border-collapse: collapse; margin: 15px 0; }";
        echo "th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }";
        echo "th { background: #133b64; color: white; }";
        echo "tr:nth-child(even) { background: #f2f2f2; }";
        echo ".btn { display: inline-block; padding: 8px 16px; background: #133b64; color: white; text-decoration: none; border-radius: 4px; margin: 5px 0; }";
        echo ".btn:hover { background: #0d2a48; }";
        echo "</style></head><body><div class='container'>";
        echo "<h1>🔍 Diagnostico Completo - Sistema Regente</h1>";
        echo "<p>Generado: " . date('Y-m-d H:i:s') . " | Server: " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . "</p>";
    }
    
    public static function finish() {
        echo "<h2>📊 Resumen</h2>";
        echo "<div style='display: flex; gap: 20px; margin: 20px 0;'>";
        echo "<div style='flex: 1; background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;'>";
        echo "<h3>✅ Exitos: " . count(self::$results) . "</h3>";
        echo "</div>";
        echo "<div style='flex: 1; background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;'>";
        echo "<h3>⚠️ Advertencias: " . count(self::$warnings) . "</h3>";
        echo "</div>";
        echo "<div style='flex: 1; background: #f8d7da; padding: 20px; border-radius: 8px; text-align: center;'>";
        echo "<h3>❌ Errores: " . count(self::$errors) . "</h3>";
        echo "</div>";
        echo "</div>";
        
        echo "</div></body></html>";
        ob_end_flush();
    }
    
    public static function addResult($title, $message, $type = 'success', $data = null) {
        self::${$type . 's'}[] = ['title' => $title, 'message' => $message, 'data' => $data];
        
        $class = $type;
        echo "<div class='section $class'><strong>[$type] $title</strong><br>" . nl2br(htmlspecialchars($message)) . "</div>";
        
        if ($data !== null) {
            echo "<pre>" . print_r($data, true) . "</pre>";
        }
    }
    
    public static function printSection($title) {
        echo "<h2>$title</h2>";
    }
    
    public static function printTable($headers, $rows) {
        echo "<table><thead><tr>";
        foreach ($headers as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr></thead><tbody>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . (is_array($cell) ? implode(', ', $cell) : htmlspecialchars($cell)) . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
}

// Iniciar diagnostico
Diagnostico::init();

// ============================================================================
// 1. PRUEBA DE ENTORNO BASICO
// ============================================================================

Diagnostico::printSection("🌍 1. Entorno Basico");

// PHP Version
Diagnostico::addResult(
    "Versión de PHP",
    "PHP " . PHP_VERSION . " (" . PHP_OS . ")",
    'info'
);

// Server Info
$serverInfo = [
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
];
Diagnostico::addResult("Información del Servidor", "Variables de servidor", 'info', $serverInfo);

// PHP Configuration
$phpConfig = [
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
    'error_reporting' => error_reporting(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
];
Diagnostico::addResult("Configuración PHP", "Valores actuales", 'info', $phpConfig);

// ============================================================================
// 2. PRUEBA DE ESTRUCTURA DE ARCHIVOS
// ============================================================================

Diagnostico::printSection("📁 2. Estructura de Archivos");

// Definir rutas base
$currentDir = __DIR__;
$publicHtmlDir = $currentDir;
$appDir = $currentDir . '/app';

// Verificar directorios principales
$directories = [
    ['public_html/', $publicHtmlDir, true],
    ['public_html/app/', $appDir, true],
    ['public_html/app/config/', $appDir . '/config', true],
    ['public_html/app/models/', $appDir . '/models', true],
    ['public_html/app/controllers/', $appDir . '/controllers', true],
    ['public_html/app/views/', $appDir . '/views', true],
    ['public_html/app/helpers/', $appDir . '/helpers', true],
    ['public_html/app/middleware/', $appDir . '/middleware', true],
    ['public_html/assets/', $publicHtmlDir . '/assets', true],
    ['public_html/logs/', $publicHtmlDir . '/logs', false],
    ['public_html/uploads/', $publicHtmlDir . '/uploads', false],
];

$dirResults = [];
foreach ($directories as $dir) {
    $exists = is_dir($dir[1]);
    $writable = $exists ? is_writable($dir[1]) : false;
    $dirResults[] = [
        $dir[0],
        $exists ? '✅' : '❌',
        $exists ? ($writable ? '✅' : '❌') : 'N/A',
        $exists ? 'Existe' : 'NO EXISTE'
    ];
    
    if (!$exists && $dir[2]) {
        Diagnostico::addResult("Directorio faltante", "El directorio {$dir[0]} no existe", 'error');
    } elseif ($exists && !$writable && $dir[2]) {
        Diagnostico::addResult("Permisos insuficientes", "El directorio {$dir[0]} no es writable", 'warning');
    }
}

Diagnostico::printTable(['Directorio', 'Existe', 'Writable', 'Estado'], $dirResults);

// Verificar archivos criticos
Diagnostico::addResult("Archivos Criticos", "Verificando archivos esenciales...", 'info');

$criticalFiles = [
    'index.php',
    'Router.php',
    'app/App.php',
    'app/config/autoload.php',
    'app/config/database.php',
    'app/config/routes.php',
    'app/config/security.php',
    'app/config/recaptcha.php',
    'app/models/Model.php',
    'app/models/User.php',
    'app/controllers/AuthController.php',
    'app/middleware/AuthMiddleware.php',
];

$fileResults = [];
foreach ($criticalFiles as $file) {
    $fullPath = $publicHtmlDir . '/' . $file;
    $exists = file_exists($fullPath);
    $readable = $exists ? is_readable($fullPath) : false;
    $fileResults[] = [$file, $exists ? '✅' : '❌', $exists ? ($readable ? '✅' : '❌') : 'N/A'];
    
    if (!$exists) {
        Diagnostico::addResult("Archivo faltante", "El archivo $file NO EXISTE", 'error');
    }
}

Diagnostico::printTable(['Archivo', 'Existe', 'Legible'], $fileResults);

// ============================================================================
// 3. PRUEBA DE AUTOLADERS Y CLASSES
// ============================================================================

Diagnostico::printSection("🔄 3. Autoloaders y Clases");

// Probar cargar clases manualmente
Diagnostico::addResult("Carga Manual", "Probando carga de clases esenciales...", 'info');

$testClasses = [
    'Database' => $appDir . '/config/database.php',
    'Router' => $publicHtmlDir . '/Router.php',
    'App\\Models\\Model' => $appDir . '/models/Model.php',
    'App\\Models\\User' => $appDir . '/models/User.php',
    'App\\Controllers\\AuthController' => $appDir . '/controllers/AuthController.php',
    'App\\Middleware\\AuthMiddleware' => $appDir . '/middleware/AuthMiddleware.php',
];

foreach ($testClasses as $class => $expectedPath) {
    try {
        if (file_exists($expectedPath)) {
            require_once $expectedPath;
            if (class_exists($class)) {
                Diagnostico::addResult("Clase $class", "✅ Cargada correctamente desde: $expectedPath", 'success');
            } else {
                Diagnostico::addResult("Clase $class", "❌ Archivo existe pero clase no encontrada: $expectedPath", 'error');
            }
        } else {
            Diagnostico::addResult("Clase $class", "❌ Archivo no existe: $expectedPath", 'error');
        }
    } catch (Throwable $e) {
        Diagnostico::addResult("Clase $class", "❌ Error al cargar: " . $e->getMessage(), 'error');
    }
}

// Probar autoloader completo
Diagnostico::addResult("Autoloader", "Probando autoloader de la aplicacion...", 'info');

try {
    // Cargar autoloader
    if (file_exists($appDir . '/config/autoload.php')) {
        require_once $appDir . '/config/autoload.php';
        Diagnostico::addResult("Autoloader", "✅ Autoloader cargado", 'success');
        
        // Verificar si APP_ROOT esta definido
        if (defined('APP_ROOT')) {
            Diagnostico::addResult("APP_ROOT", "Definido como: " . APP_ROOT, 'success');
        } else {
            Diagnostico::addResult("APP_ROOT", "❌ No esta definido", 'error');
        }
        
        // Probar cargar clases con autoloader
        $testWithAutoloader = ['Database', 'Router'];
        foreach ($testWithAutoloader as $class) {
            if (class_exists($class)) {
                Diagnostico::addResult("Autoloader: $class", "✅ Clase disponible", 'success');
            } else {
                Diagnostico::addResult("Autoloader: $class", "❌ Clase NO disponible", 'error');
            }
        }
    } else {
        Diagnostico::addResult("Autoloader", "❌ Archivo autoload.php no existe", 'error');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("Autoloader", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 4. PRUEBA DE CONEXION A BASE DE DATOS
// ============================================================================

Diagnostico::printSection("🗄️ 4. Conexion a Base de Datos");

Diagnostico::addResult("Configuracion DB", "Verificando configuracion de database.php...", 'info');

try {
    if (file_exists($appDir . '/config/database.php')) {
        require_once $appDir . '/config/database.php';
        
        // Mostrar configuracion
        $dbConfig = [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'N/A',
            'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'N/A',
            'DB_USER' => defined('DB_USER') ? DB_USER : 'N/A',
            'DB_PASS' => defined('DB_PASS') ? '*****' : 'N/A',
            'DB_CHARSET' => defined('DB_CHARSET') ? DB_CHARSET : 'N/A',
        ];
        Diagnostico::addResult("Configuracion", "Parámetros de conexión", 'info', $dbConfig);
        
        // Probar conexion
        Diagnostico::addResult("Conexion DB", "Intentando conectar a la base de datos...", 'info');
        
        $db = Database::getConnection();
        if ($db) {
            Diagnostico::addResult("Conexion DB", "✅ Conexion exitosa a: " . DB_NAME, 'success');
            
            // Probar consulta simple
            try {
                $stmt = $db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($tables)) {
                    Diagnostico::addResult("Tablas", "✅ Se encontraron " . count($tables) . " tablas", 'success');
                    Diagnostico::printTable(['Tablas en ' . DB_NAME], [implode(', ', $tables)]);
                } else {
                    Diagnostico::addResult("Tablas", "⚠️ No se encontraron tablas en la base de datos", 'warning');
                }
            } catch (PDOException $e) {
                Diagnostico::addResult("Consulta DB", "❌ Error al listar tablas: " . $e->getMessage(), 'error');
            }
            
            // Probar tabla usuarios
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM usuarios");
                $result = $stmt->fetch();
                Diagnostico::addResult("Tabla usuarios", "✅ Existen " . $result['count'] . " usuarios", 'success');
            } catch (PDOException $e) {
                Diagnostico::addResult("Tabla usuarios", "❌ Error: " . $e->getMessage(), 'error');
            }
            
            // Probar tabla roles
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM roles");
                $result = $stmt->fetch();
                Diagnostico::addResult("Tabla roles", "✅ Existen " . $result['count'] . " roles", 'success');
            } catch (PDOException $e) {
                Diagnostico::addResult("Tabla roles", "❌ Error: " . $e->getMessage(), 'error');
            }
            
        } else {
            Diagnostico::addResult("Conexion DB", "❌ No se pudo establecer conexion", 'error');
        }
    } else {
        Diagnostico::addResult("Configuracion DB", "❌ Archivo database.php no existe", 'error');
    }
} catch (PDOException $e) {
    Diagnostico::addResult("Conexion DB", "❌ Error PDO: " . $e->getMessage(), 'error');
} catch (Throwable $e) {
    Diagnostico::addResult("Conexion DB", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 5. PRUEBA DE LOGGING
// ============================================================================

Diagnostico::printSection("📝 5. Sistema de Logging");

Diagnostico::addResult("Configuracion Logs", "Verificando configuracion de logs...", 'info');

// Probar crear directorio de logs
$logDir = $publicHtmlDir . '/logs';
if (!is_dir($logDir)) {
    if (mkdir($logDir, 0755, true)) {
        Diagnostico::addResult("Directorio logs/", "✅ Creado: $logDir", 'success');
    } else {
        Diagnostico::addResult("Directorio logs/", "❌ No se pudo crear: $logDir", 'error');
    }
} else {
    Diagnostico::addResult("Directorio logs/", "✅ Ya existe: $logDir", 'success');
}

// Probar escribir en log
$logFile = $logDir . '/error.log';
try {
    $testMessage = "[DIAGNOSTICO] Prueba de escritura de log - " . date('Y-m-d H:i:s');
    if (file_put_contents($logFile, $testMessage . PHP_EOL, FILE_APPEND) !== false) {
        Diagnostico::addResult("Escritura Log", "✅ Se pudo escribir en: $logFile", 'success');
    } else {
        Diagnostico::addResult("Escritura Log", "❌ No se pudo escribir en: $logFile", 'error');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("Escritura Log", "❌ Error: " . $e->getMessage(), 'error');
}

// Probar error_log de PHP
try {
    ini_set('error_log', $logFile);
    ini_set('log_errors', 1);
    error_log("[DIAGNOSTICO] Prueba error_log() - " . date('Y-m-d H:i:s'));
    Diagnostico::addResult("error_log()", "✅ Funcion error_log() configurada", 'success');
} catch (Throwable $e) {
    Diagnostico::addResult("error_log()", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 6. PRUEBA DE APLICACION COMPLETA
// ============================================================================

Diagnostico::printSection("🚀 6. Prueba de Aplicacion Completa");

Diagnostico::addResult("Inicializacion App", "Intentando inicializar la aplicacion completa...", 'info');

try {
    // Requerir todos los archivos principales
    require_once $publicHtmlDir . '/index.php';
    Diagnostico::addResult("index.php", "✅ Cargado correctamente", 'success');
} catch (Throwable $e) {
    Diagnostico::addResult("index.php", "❌ Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine(), 'error');
}

try {
    // Probar crear instancia de App
    if (class_exists('App')) {
        $app = new App();
        Diagnostico::addResult("Clase App", "✅ Instancia creada correctamente", 'success');
    } else {
        Diagnostico::addResult("Clase App", "❌ Clase App no disponible", 'error');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("Clase App", "❌ Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine(), 'error');
}

try {
    // Probar Router
    if (class_exists('Router')) {
        Router::get('/test', function() { echo 'OK'; });
        Diagnostico::addResult("Router", "✅ Router funcional", 'success');
    } else {
        Diagnostico::addResult("Router", "❌ Clase Router no disponible", 'error');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("Router", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 7. PRUEBA DE CODIFICACION
// ============================================================================

Diagnostico::printSection("🔤 7. Prueba de Codificacion");

Diagnostico::addResult("Codificacion", "Verificando manejo de caracteres...", 'info');

// Probar UTF-8
$utf8Test = "Hola Mundo - 你好世界 - Привет мир - こんにちは世界";
if (mb_detect_encoding($utf8Test, 'UTF-8', true)) {
    Diagnostico::addResult("UTF-8", "✅ Codificacion UTF-8 funcional", 'success');
} else {
    Diagnostico::addResult("UTF-8", "❌ Problemas con UTF-8", 'error');
}

// Probar conexion con caracteres especiales
try {
    $db = Database::getConnection();
    $testText = "Prueba con ñ, á, é, í, ó, ú, ü";
    $stmt = $db->prepare("SELECT ? as text");
    $stmt->execute([$testText]);
    $result = $stmt->fetch();
    if ($result['text'] === $testText) {
        Diagnostico::addResult("DB + UTF-8", "✅ Base de datos maneja UTF-8 correctamente", 'success');
    } else {
        Diagnostico::addResult("DB + UTF-8", "⚠️ Posibles problemas con caracteres especiales", 'warning');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("DB + UTF-8", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 8. PRUEBA DE SEGURIDAD
// ============================================================================

Diagnostico::printSection("🔒 8. Prueba de Seguridad");

Diagnostico::addResult("Seguridad", "Verificando configuracion de seguridad...", 'info');

// Verificar security.php
if (file_exists($appDir . '/config/security.php')) {
    require_once $appDir . '/config/security.php';
    Diagnostico::addResult("security.php", "✅ Archivo de seguridad cargado", 'success');
    
    $securityConfig = [
        'SESSION_NAME' => defined('SESSION_NAME') ? SESSION_NAME : 'N/A',
        'RATE_LIMIT_LOGIN_ATTEMPTS' => defined('RATE_LIMIT_LOGIN_ATTEMPTS') ? RATE_LIMIT_LOGIN_ATTEMPTS : 'N/A',
        'CSRF_TOKEN_NAME' => defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'N/A',
        'PASSWORD_HASH_ALGO' => defined('PASSWORD_HASH_ALGO') ? PASSWORD_HASH_ALGO : 'N/A',
    ];
    Diagnostico::addResult("Configuracion Seguridad", "Parametros de seguridad", 'info', $securityConfig);
} else {
    Diagnostico::addResult("security.php", "❌ Archivo no existe", 'error');
}

// Verificar sesiones
Diagnostico::addResult("Sesiones", "Estado de sesiones: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVA' : 'INACTIVA'), 'info');

// ============================================================================
// 9. PRUEBA DE EXPORTACIONES (CSV/PDF)
// ============================================================================

Diagnostico::printSection("📊 9. Prueba de Exportaciones");

Diagnostico::addResult("Exportaciones", "Verificando capacidades de exportacion...", 'info');

// Verificar si hay clases de exportacion
$exportFiles = [
    'app/controllers/ReportController.php',
    'app/helpers/ExportHelper.php',
];

foreach ($exportFiles as $file) {
    $fullPath = $publicHtmlDir . '/' . $file;
    if (file_exists($fullPath)) {
        Diagnostico::addResult("Exportacion: $file", "✅ Archivo existe", 'success');
    } else {
        Diagnostico::addResult("Exportacion: $file", "⚠️ Archivo no encontrado", 'warning');
    }
}

// Probar escritura de CSV
$testCsv = $publicHtmlDir . '/uploads/test.csv';
try {
    if (!is_dir($publicHtmlDir . '/uploads')) {
        mkdir($publicHtmlDir . '/uploads', 0755, true);
    }
    $csvContent = "col1,col2,col3\nval1,val2,val3\n";
    if (file_put_contents($testCsv, $csvContent) !== false) {
        Diagnostico::addResult("Export CSV", "✅ Se puede crear archivos CSV", 'success');
        unlink($testCsv); // Limpiar
    } else {
        Diagnostico::addResult("Export CSV", "❌ No se puede crear archivos CSV", 'error');
    }
} catch (Throwable $e) {
    Diagnostico::addResult("Export CSV", "❌ Error: " . $e->getMessage(), 'error');
}

// ============================================================================
// 10. RESUMEN FINAL
// ============================================================================

Diagnostico::printSection("📋 10. Resumen Final");

Diagnostico::addResult(
    "Resumen",
    "Diagnostico completado. Revisa los errores (❌) y advertencias (⚠️) arriba.",
    'info'
);

// Finalizar
Diagnostico::finish();
