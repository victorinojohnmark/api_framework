<?php
namespace Core;

use PDO;
use PDOException;

class Database
{
    protected $pdo;
    
    // Builder Properties
    protected $table;
    protected $select = '*';
    protected $joins = [];
    protected $where = [];
    protected $params = [];
    protected $orderBy = '';
    protected $limit = '';
    protected $offset = '';

    public function __construct()
    {
        // Handle config location (support both root and /config folder)
        if (defined('APP_CONFIG')) {
            $config = APP_CONFIG;
        } else {
            $configPath = __DIR__ . '/../config/config.php';
            if (!file_exists($configPath)) {
                $configPath = __DIR__ . '/../config.php';
            }
            $config = require $configPath;
        }

        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

            // --- SYNC TIMEZONE ---
            // Get the current offset from PHP (e.g., "+08:00")
            $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
            $offset = $now->format('P'); 

            // Force MySQL to use this offset
            $this->pdo->exec("SET time_zone = '$offset'");
        } catch (PDOException $e) {
            die("DB Connection Error: " . $e->getMessage());
        }
    }

    // --- 1. BASIC METHODS ---

    public function table($tableName)
    {
        $this->table = $tableName;
        $this->reset(); 
        return $this;
    }

    public function select($columns = '*')
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    // --- 2. JOIN METHODS ---

    public function join($table, $condition, $type = 'INNER')
    {
        $this->joins[] = "$type JOIN $table ON $condition";
        return $this;
    }

    public function leftJoin($table, $condition)
    {
        return $this->join($table, $condition, 'LEFT');
    }

    public function rightJoin($table, $condition)
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    // --- 3. WHERE METHODS ---

    public function where($column, $operator = null, $value = null)
    {
        if ($operator === null && $value === null) {
            $this->where[] = $column;
            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereRaw($sql, $params = [])
    {
        $this->where[] = $sql;
        if (!empty($params)) {
            $this->params = array_merge($this->params, $params);
        }
        return $this;
    }

    // --- 4. ORDER & PAGINATION ---

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy = "ORDER BY $column $direction";
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = "LIMIT $limit";
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = "OFFSET $offset";
        return $this;
    }

    // --- 5. EXECUTION (READ) ---

    public function get()
    {
        $stmt = $this->executeSelect();
        return $stmt->fetchAll();
    }

    public function first()
    {
        $this->limit(1);
        $stmt = $this->executeSelect();
        return $stmt->fetch();
    }
    
    public function getQuery()
    {
        return [
            'sql'    => $this->compileSelect(),
            'params' => $this->params
        ];
    }

    // --- 6. EXECUTION (WRITE) ---

    /**
     * Insert Data
     * Returns: Last Insert ID
     */
    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Update Data
     * Returns: Boolean (Success/Fail)
     */
    public function update(array $data)
    {
        $set = [];
        $values = [];

        foreach ($data as $col => $val) {
            $set[] = "$col = ?";
            $values[] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set);
        
        // Add WHERE clause
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
            $values = array_merge($values, $this->params);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete Data
     * Returns: Boolean (Success/Fail)
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->params);
    }

    // --- 7. RAW QUERY & HELPERS ---

    public function query($sql, $params = [])
    {
        $this->reset(); 
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($params);

            if (stripos(trim($sql), 'SELECT') === 0) {
                return $stmt->fetchAll();
            }
            return $success;
        } catch (PDOException $e) {
            if (defined('APP_CONFIG') && APP_CONFIG['env'] === 'development') {
                die("Raw Query Error: " . $e->getMessage());
            }
            return false;
        }
    }

    private function compileSelect()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        $sql .= " {$this->orderBy} {$this->limit} {$this->offset}";

        return trim($sql);
    }

    private function executeSelect()
    {
        $sql = $this->compileSelect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt;
    }

    private function reset()
    {
        $this->select = '*';
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->orderBy = '';
        $this->limit = '';
        $this->offset = '';
    }
}