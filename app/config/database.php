<?php
/**
 * Database Configuration
 * 
 * Update these values to match your Hostinger database
 */

// Database credentials for regente2.colmarista.com
// Update these to match your actual database
// Current configuration for: rege_colmarista2

define('DB_HOST', 'localhost');
define('DB_NAME', 'rege_colmarista2');
define('DB_USER', 'rege_colmarista2');
define('DB_PASS', 'Marsupiales-2026');
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
