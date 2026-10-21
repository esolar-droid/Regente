<?php
/**
 * Base Model Class
 * 
 * Provides common database operations for all models
 */

namespace App\Models;

use PDO;
use PDOException;

abstract class Model implements \ArrayAccess {
    protected static $table = '';
    protected static $primaryKey = 'id';
    protected $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
        // v9: PROPAGAR los datos a las propiedades declaradas en los modelos.
        // Student/Course/Management declaran `public $nombre_completo;` etc.;
        // al existir la propiedad publica, PHP NUNCA llama a __get() y devolvia
        // null -> por eso la Ficha (show) y el formulario de Edicion salian en
        // BLANCO. Ahora ambos caminos ($obj->campo y $obj['campo']) leen de la
        // misma fuente ($this->data). Esto se aplica automaticamente a TODOS
        // los modelos futuros (Event, Message, User...): causa raiz global.
        foreach ((array)$data as $k => $v) {
            if ($k !== '' && property_exists($this, $k)) {
                try { $this->$k = $v; } catch (\Throwable $e) { /* solo-lectura */ }
            }
        }
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

    // ---- v9: acceso tipo array ($obj['campo']) sobre los modelos, para que
    // las vistas puedan usar objeto o array indistintamente sin romper nada.
    #[\ReturnTypeWillChange]
    public function offsetExists($k): bool { return isset($this->data[$k]); }
    #[\ReturnTypeWillChange]
    public function offsetGet($k)          { return $this->data[$k] ?? null; }
    #[\ReturnTypeWillChange]
    public function offsetSet($k, $v): void {
        $this->data[$k] = $v;
        if ($k !== '' && property_exists($this, (string)$k)) $this->$k = $v;
    }
    #[\ReturnTypeWillChange]
    public function offsetUnset($k): void  { unset($this->data[$k]); }
    
    public static function getTableName() {
        return static::$table;
    }
    
    public static function getPrimaryKey() {
        return static::$primaryKey;
    }

    /**
     * Filas crudas ordenadas por una columna (para listas desplegables, etc).
     * Devuelve array de arrays asociativos. Lanza PDOException si falla
     * (el diagnostico lo necesita ver para marcar FAIL con la causa real).
     */
    public static function orderBy($column, $dir = 'ASC', $limit = 0) {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $sql = 'SELECT * FROM `' . static::$table . '` ORDER BY `' . $column . '` ' . $dir;
        if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit;
        return self::getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
