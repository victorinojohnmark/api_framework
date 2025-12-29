<?php
namespace Core\Database;

class Blueprint
{
    protected $table;
    protected $columns = [];     // Stores column definitions
    protected $commands = [];    // Stores commands like DROP/RENAME
    protected $isCreate = false; // "Create" mode vs "Alter" mode

    public function __construct($table, $isCreate = false)
    {
        $this->table = $table;
        $this->isCreate = $isCreate;
    }

    // --- 1. Column Definitions ---

    private function addColumn($sql)
    {
        // If we are ALTERING a table, standard columns are "ADD COLUMN ..."
        // If we are CREATING, they are just "..."
        $prefix = $this->isCreate ? "" : "ADD COLUMN ";
        $this->columns[] = $prefix . $sql;
        return $this;
    }

    public function id() { return $this->increments('id'); }

    public function increments($name)
    {
        return $this->addColumn("`$name` INT AUTO_INCREMENT PRIMARY KEY");
    }

    public function integer($name) { return $this->addColumn("`$name` INT"); }
    public function string($name, $len = 255) { return $this->addColumn("`$name` VARCHAR($len)"); }
    public function text($name) { return $this->addColumn("`$name` TEXT"); }
    public function date($name) { return $this->addColumn("`$name` DATE"); }
    public function datetime($name) { return $this->addColumn("`$name` DATETIME"); }
    public function time($name) { return $this->addColumn("`$name` TIME"); }
    public function boolean($name) { return $this->addColumn("`$name` TINYINT(1)"); }
    
    public function timestamps()
    {
        $this->integer('created_at')->default(0);
        $this->integer('created_by')->default(0);
        $this->integer('updated_at')->default(0);
        $this->integer('updated_by')->default(0);
    }

    /**
     * Add Soft Delete columns (deleted_at, deleted_by)
     */
    public function softDelete()
    {
        $this->integer('deleted_at')->default(0);
        $this->integer('deleted_by')->default(0);
    }

    // --- 2. Modifiers (Nullable, Default, Change) ---

    public function nullable()
    {
        $this->appendLast(" NULL");
        return $this;
    }

    public function default($value)
    {
        $val = is_string($value) ? "'$value'" : $value;
        $this->appendLast(" DEFAULT $val");
        return $this;
    }

    public function unique()
    {
        $this->appendLast(" UNIQUE");
        return $this;
    }

    /**
     * Helper to modify the last added column string
     */
    private function appendLast($str)
    {
        $idx = count($this->columns) - 1;
        if ($idx >= 0) {
            $this->columns[$idx] .= $str;
        }
    }

    /**
     * Mark the last column definition as a MODIFY/CHANGE instead of ADD
     */
    public function change()
    {
        $idx = count($this->columns) - 1;
        if ($idx >= 0 && !$this->isCreate) {
            $this->columns[$idx] = str_replace("ADD COLUMN ", "MODIFY COLUMN ", $this->columns[$idx]);
        }
        return $this;
    }

    // --- 3. Table Commands (Drop, Rename) ---

    public function dropColumn($name)
    {
        $this->commands[] = "DROP COLUMN `$name`";
        return $this;
    }

    /**
     * Rename a column
     * WARNING: Requires full column definition for MySQL 5 compatibility.
     * * @param string $from Old Name
     * @param string $to New Name
     * @param string $definition Column Type (e.g., "VARCHAR(255) NOT NULL")
     */
    public function renameColumn($from, $to, $definition)
    {
        // We use "CHANGE COLUMN" because it works on both MySQL 5.7 and 8.0
        // Syntax: ALTER TABLE table_name CHANGE COLUMN old_name new_name varchar(255) not null
        $this->commands[] = "CHANGE COLUMN `$from` `$to` $definition";
        return $this;
    }

    // --- 4. SQL Generators ---

    public function build()
    {
        if ($this->isCreate) {
            return $this->buildCreate();
        }
        return $this->buildAlter();
    }

    protected function buildCreate()
    {
        $cols = implode(', ', $this->columns);
        return "CREATE TABLE IF NOT EXISTS `{$this->table}` ($cols) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    protected function buildAlter()
    {
        $all = array_merge($this->columns, $this->commands);
        
        if (empty($all)) {
            return null;
        }

        $instructions = implode(', ', $all);
        return "ALTER TABLE `{$this->table}` $instructions;";
    }
}