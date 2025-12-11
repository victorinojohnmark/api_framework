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
    protected $joins = [];   // New: Store join clauses
    protected $where = [];
    protected $params = [];
    protected $orderBy = '';
    protected $limit = '';
    protected $offset = '';  // New: Store offset

    public function __construct()
    {
        $config = defined('APP_CONFIG') ? APP_CONFIG : require __DIR__ . '/../config/config.php';

        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
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

    /**
     * Select Support
     * Supports array: select(['id', 'name'])
     * Supports string: select('users.id, users.first_name')
     */
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

    /**
     * Supports:
     * 1. where('age', '>', 18)
     * 2. where('status', 'active')  -> defaults to '='
     * 3. where('id = 1')            -> Raw string support
     */
    public function where($column, $operator = null, $value = null)
    {
        // Case A: Raw Where -> where("users.id = 1")
        if ($operator === null && $value === null) {
            $this->where[] = $column;
            return $this;
        }

        // Case B: Short Syntax -> where('id', 5)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        // Case C: Standard -> where('age', '>', 18)
        $this->where[] = "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    /**
     * Safe Raw WHERE clause with bindings
     * Usage: whereRaw("age > ? OR role = ?", [18, 'admin'])
     */
    public function whereRaw($sql, $params = [])
    {
        $this->where[] = $sql;
        
        // Merge new params with existing ones safely
        if (!empty($params)) {
            $this->params = array_merge($this->params, $params);
        }
        
        return $this;
    }

    // --- 4. ORDER & PAGINATION (Feature 3) ---

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

    // --- 5. EXECUTION & DEBUGGING ---

    /**
     * getQuery()
     * Returns the compiled SQL and params without executing it.
     */
    public function getQuery()
    {
        return [
            'sql'    => $this->compileSelect(),
            'params' => $this->params
        ];
    }

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

    // --- HELPER METHODS ---

    /**
     * Compiles the SELECT string
     */
    private function compileSelect()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        // Add Joins
        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }

        // Add Wheres
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        // Add Order, Limit, Offset
        // Note: Space is important before appends
        $sql .= " {$this->orderBy} {$this->limit} {$this->offset}";

        return trim($sql); // Clean up extra spaces
    }

    /**
     * Execute a Raw SQL Query
     * Supports placeholders for safety: query("SELECT * FROM users WHERE id = ?", [1])
     * * @param string $sql
     * @param array  $params
     * @return array|bool Returns array of objects for SELECT, true/false for INSERT/UPDATE
     */
    public function query($sql, $params = [])
    {
        $this->reset(); // Clean up any previous builder state to avoid conflicts

        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($params);

            // If the query starts with SELECT, return the results
            if (stripos(trim($sql), 'SELECT') === 0) {
                return $stmt->fetchAll();
            }

            // For INSERT, UPDATE, DELETE, return boolean success
            return $success;
        } catch (PDOException $e) {
            // In development, you might want to see the SQL error
            if (defined('APP_CONFIG') && APP_CONFIG['env'] === 'development') {
                die("Raw Query Error: " . $e->getMessage());
            }
            return false;
        }
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