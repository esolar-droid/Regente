<?php
/**
 * Base Model Class
 * 
 * Provides common database operations for all models
 */

namespace App\Models;

use PDO;
use PDOException;

abstract class Model {
    protected static $table = '';
    protected static $primaryKey = 'id';
    protected $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    public function __get($name) {
        return $this->data[$name] ?? null;
    }
    
    public function __set($name, $value) {
        $this->data[$name] = $value;
    }
    
    public function __isset($name) {
        return isset($this->data[$name]);
    }
    
    public function toArray() {
        return $this->data;
    }
    
    public static function getTableName() {
        return static::$table;
    }
    
    public static function getPrimaryKey() {
        return static::$primaryKey;
    }
    
    protected static function getDb() {
        return \Database::getConnection();
    }
    
    public static function all() {
        try {
            $db = self::getDb();
            $stmt = $db->query("SELECT * FROM " . static::$table);
            return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
        } catch (PDOException $e) {
            error_log("Model all() error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function find($id) {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? new static($result) : null;
        } catch (PDOException $e) {
            error_log("Model find() error: " . $e->getMessage());
            return null;
        }
    }
    
    public static function where($column, $operator, $value) {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . $column . " " . $operator . " ?");
            $stmt->execute([$value]);
            return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
        } catch (PDOException $e) {
            error_log("Model where() error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function firstWhere($column, $operator, $value) {
        $results = static::where($column, $operator, $value);
        return count($results) > 0 ? $results[0] : null;
    }
    
    public function save() {
        try {
            $db = self::getDb();
            
            if (isset($this->data[static::$primaryKey]) && $this->data[static::$primaryKey] !== null) {
                // Update existing record
                $fields = [];
                $values = [];
                foreach ($this->data as $key => $value) {
                    if ($key !== static::$primaryKey) {
                        $fields[] = "`$key` = ?";
                        $values[] = $value;
                    }
                }
                $values[] = $this->data[static::$primaryKey];
                
                $sql = "UPDATE " . static::$table . " SET " . implode(', ', $fields) . " WHERE " . static::$primaryKey . " = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
                return $this->data[static::$primaryKey];
            } else {
                // Insert new record
                $fields = [];
                $placeholders = [];
                $values = [];
                
                foreach ($this->data as $key => $value) {
                    if ($key !== static::$primaryKey) {
                        $fields[] = "`$key`";
                        $placeholders[] = "?";
                        $values[] = $value;
                    }
                }
                
                $sql = "INSERT INTO " . static::$table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
                
                if ($db->lastInsertId()) {
                    $this->data[static::$primaryKey] = $db->lastInsertId();
                    return $this->data[static::$primaryKey];
                }
                return false;
            }
        } catch (PDOException $e) {
            error_log("Model save() error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete() {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
            return $stmt->execute([$this->data[static::$primaryKey]]);
        } catch (PDOException $e) {
            error_log("Model delete() error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function query($sql, $params = []) {
        try {
            $db = self::getDb();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
        } catch (PDOException $e) {
            error_log("Model query() error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function execute($sql, $params = []) {
        try {
            $db = self::getDb();
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Model execute() error: " . $e->getMessage());
            return false;
        }
    }
}
