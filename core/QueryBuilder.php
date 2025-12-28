<?php
namespace Core;

use PDO;

class QueryBuilder
{
    protected $pdo;
    protected $table;
    
    // State Properties
    protected $select = '*';
    protected $joins = [];
    protected $where = [];
    protected $params = [];
    protected $orderBy = '';
    protected $limit = '';
    protected $offset = '';

    public function __construct(PDO $pdo, $tableName)
    {
        $this->pdo = $pdo;
        $this->table = $tableName;
    }

    // --- 1. BASIC METHODS ---

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

    public function exists()
    {
        $this->select('COUNT(*) as count')->limit(1);
        $stmt = $this->executeSelect();
        return $stmt->fetchColumn() > 0;
    }
    
    // --- 6. EXECUTION (WRITE) ---

    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }

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

    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->params);
    }

    // --- INTERNAL HELPERS ---

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
}