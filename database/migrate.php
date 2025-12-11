<?php
# database/migrate.php

# 1. Setup Paths (One level up from database/ folder is root)
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/core/DotEnv.php';
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    Core\DotEnv::load($envFile);
}

$config = require ROOT_PATH . '/config/config.php';

# 2. Connect to DB
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage() . "\n");
}

echo "--------------------------------\n";
echo "  Migration Runner\n";
echo "--------------------------------\n";

# 3. Ensure migrations table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

# 4. Get files
$files = glob(__DIR__ . '/migrations/*.php');
$applied = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

$toRun = [];

foreach ($files as $file) {
    $filename = basename($file);
    if (!in_array($filename, $applied)) {
        $toRun[] = $file;
    }
}

if (empty($toRun)) {
    echo "Nothing to migrate.\n";
    exit;
}

# 5. Run Migrations
foreach ($toRun as $file) {
    $filename = basename($file);
    echo "Migrating: $filename...";

    require_once $file;

    # Smart Class Detection: Read file to find "class X"
    $content = file_get_contents($file);
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $className = $matches[1]; # The actual class name
        
        if (class_exists($className)) {
            $migration = new $className();
            try {
                $migration->up($pdo);
                
                # Record completion
                $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                $stmt->execute([$filename]);
                
                echo " DONE.\n";
            } catch (Exception $e) {
                echo " FAILED: " . $e->getMessage() . "\n";
                exit;
            }
        } else {
            echo " SKIPPED (Class '$className' not loaded).\n";
        }
    } else {
        echo " FAILED (Could not find class name in file).\n";
    }
}

echo "All done.\n";