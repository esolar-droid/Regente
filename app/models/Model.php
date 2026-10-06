<?php
namespace App\Models;

use PDO;

abstract class Model {
    protected static $table;
    protected static $primaryKey = 'id';
    
    protected $db;
    
    public function __construct() {
        $this->db = \Database::getConnection();
    }
    
    public static function table() {
        return static::$table;
    }
    
    public static function primaryKey() {
        return static::$primaryKey;
    }
    
    // Get all records
    public static function all() {
        $model = new static();
        $stmt = $model->db->query("SELECT * FROM " . static::$table);
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    
    // Find by ID
    public static function find($id) {
        $model = new static();
        $stmt = $model->db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        return $stmt->fetchObject(get_called_class());
    }
    
    // Find by field
    public static function where($field, $value) {
        $model = new static();
        $stmt = $model->db->prepare("SELECT * FROM " . static::$table . " WHERE {$field} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchObject(get_called_class());
    }
    
    // Get all by field
    public static function whereAll($field, $value) {
        $model = new static();
        $stmt = $model->db->prepare("SELECT * FROM " . static::$table . " WHERE {$field} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    
    // Create a new record
    public function save() {
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        
        $fields = [];
        $values = [];
        $params = [];
        
        foreach ($this as $key => $value) {
            if ($key !== 'db' && $key !== $primaryKey) {
                $fields[] = $key;
                $values[] = "?";
                $params[] = $value;
            }
        }
        
        if (!empty($fields)) {
            $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // Update a record
    public function update() {
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        
        $sets = [];
        $params = [];
        
        foreach ($this as $key => $value) {
            if ($key !== 'db' && $key !== $primaryKey) {
                $sets[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        
        $params[] = $this->$primaryKey;
        
        if (!empty($sets)) {
            $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$primaryKey} = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        }
        
        return false;
    }
    
    // Delete a record
    public function delete() {
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        
        $sql = "DELETE FROM {$table} WHERE {$primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->$primaryKey]);
    }
    
    // Execute custom query
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    
    // Get first result of custom query
    public function queryFirst($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchObject(get_called_class());
    }
}
