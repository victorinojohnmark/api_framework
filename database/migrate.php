<?php
# database/migrate.php

# 1. Setup Paths
define('ROOT_PATH', dirname(__DIR__));

# --- 1.5 ADD MANUAL AUTOLOADER ---
spl_autoload_register(function ($class) {
    if (strpos($class, 'Core\\') === 0) {
        // Convert "Core\Database\Schema" -> "/path/to/project/core/Database/Schema.php"
        $file = ROOT_PATH . '/' . str_replace('\\', '/', lcfirst($class)) . '.php';
        
        // Also check capitalized folder "Core" if "core" fails
        if (!file_exists($file)) {
             $file = ROOT_PATH . '/' . str_replace('\\', '/', $class) . '.php';
        }

        if (file_exists($file)) {
            require_once $file;
        }
    }
});
# -------------------------------------------

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

    // Pass the connection to the Schema class so it's not NULL anymore
    Core\Database\Schema::setConnection($pdo);

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

    # Smart Class Detection
    $content = file_get_contents($file);
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $className = $matches[1];
        
        if (class_exists($className)) {
            $migration = new $className();
            try {
                // Pass PDO if needed, though Schema uses internal Database connection
                $migration->up(); 
                
                # Record completion
                $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                $stmt->execute([$filename]);
                
                echo " DONE.\n";
            } catch (Exception $e) {
                echo " FAILED: " . $e->getMessage() . "\n";
                // Show file path for easier debugging
                echo " File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
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