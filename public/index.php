<?php
// Front Controller - Punto de entrada principal
// Versión para Hostinger: app/ está en el mismo nivel que public/

// Definir rutas absolutas
$appRoot = realpath(__DIR__ . '/..');
define('APP_ROOT', $appRoot);
define('PUBLIC_ROOT', __DIR__);

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar rutas
if (!is_dir(APP_ROOT . '/app')) {
    die("Error: Carpeta app/ no encontrada en: " . htmlspecialchars(APP_ROOT . '/app'));
}

// Cargar el autoloader
require_once APP_ROOT . '/app/config/autoload.php';

// Verificar que la clase App exista y cargarla manualmente si es necesario
$appFile = APP_ROOT . '/app/App.php';
if (!file_exists($appFile)) {
    die("Error: App.php no encontrado en: " . htmlspecialchars($appFile));
}

// Cargar la clase App manualmente (por si el autoloader no la encuentra)
require_once $appFile;

// Verificar que la clase exista
if (!class_exists('App')) {
    die("Error: La clase App no se encontró después de cargar: " . htmlspecialchars($appFile));
}

// Iniciar la aplicación
try {
    $app = new App();
    $app->run();
} catch (Exception $e) {
    die("Error al ejecutar la aplicación: " . htmlspecialchars($e->getMessage()));
}
?>