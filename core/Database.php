<?php
namespace Core;

use PDO;
use PDOException;

class Database
{
    public $pdo;

    public function __construct()
    {
        // 1. Load Config
        if (defined('APP_CONFIG')) {
            $config = APP_CONFIG;
        } else {
            // Fallback for standalone usage
            $configPath = __DIR__ . '/../config/config.php';
            if (!file_exists($configPath)) {
                $configPath = __DIR__ . '/../config.php';
            }
            $config = require $configPath;
        }

        // 2. Connect
        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

            // 3. Sync Timezone
            $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
            $offset = $now->format('P'); 
            $this->pdo->exec("SET time_zone = '$offset'");

        } catch (PDOException $e) {
            die("DB Connection Error: " . $e->getMessage());
        }
    }

    /**
     * Factory Method: Returns a fresh QueryBuilder
     * This fixes the state collision bug.
     */
    public function table($tableName)
    {
        return new QueryBuilder($this->pdo, $tableName);
    }

    // Helper for your existing code using 'useTable'
    public function useTable($tableName)
    {
        return $this->table($tableName);
    }

    /**
     * Run Raw SQL
     * Useful for complex reports or maintenance scripts
     */
    public function query($sql, $params = [])
    {
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
}