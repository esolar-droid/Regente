<?php
// Database Configuration

define('DB_HOST', 'localhost');
define('DB_NAME', 'colmarista');
define('DB_USER', 'colmarista');
define('DB_PASS', 'mjbchch1789+FR');
define('DB_CHARSET', 'utf8mb4');

// Establish database connection
class Database {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
            }
        }
        return self::$connection;
    }
}

// Test connection (optional)
// $db = Database::getConnection();
