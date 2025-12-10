<?php
// database/rollback.php

// 1. Setup Paths
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/core/DotEnv.php';
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    Core\DotEnv::load($envFile);
}

$config = require ROOT_PATH . '/config.php';

// 2. Connect to DB
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage() . "\n");
}

echo "--------------------------------\n";
echo "  Rollback Runner\n";
echo "--------------------------------\n";

// 3. Get the last migration run
// We strictly take the one with the highest ID
$lastMigration = $pdo->query("SELECT * FROM migrations ORDER BY id DESC LIMIT 1")->fetch();

if (!$lastMigration) {
    echo "Nothing to rollback.\n";
    exit;
}

$filename = $lastMigration->migration;
$filePath = __DIR__ . '/migrations/' . $filename;

if (!file_exists($filePath)) {
    echo "Error: Migration file '$filename' not found. Cannot rollback.\n";
    exit;
}

// 4. Run the Down method
echo "Rolling back: $filename...";

require_once $filePath;

$content = file_get_contents($filePath);
if (preg_match('/class\s+(\w+)/', $content, $matches)) {
    $className = $matches[1];
    
    if (class_exists($className)) {
        $migration = new $className();
        
        try {
            // EXECUTE DOWN()
            $migration->down($pdo);
            
            // Remove from Database
            $stmt = $pdo->prepare("DELETE FROM migrations WHERE id = ?");
            $stmt->execute([$lastMigration->id]);
            
            echo " DONE.\n";
        } catch (Exception $e) {
            echo " FAILED: " . $e->getMessage() . "\n";
        }
    } else {
        echo " Class not found.\n";
    }
} else {
    echo " Class name could not be determined.\n";
}