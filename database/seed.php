<?php

define('ROOT_PATH', dirname(__DIR__));

// 1. Autoloader 
spl_autoload_register(function ($className) {
    $file = ROOT_PATH . '/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 2. Load Env & Config
use Core\DotEnv;
use Core\Database;

$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) DotEnv::load($envFile);

$config = require ROOT_PATH . '/config/config.php';
define('APP_CONFIG', $config); // Database class needs this constant

echo "--------------------------------\n";
echo "  Database Seeder\n";
echo "--------------------------------\n";

// 3. Initialize Database Core
// This handles the connection automatically based on config
$db = new Database(); 

$target = isset($argv[1]) ? $argv[1] : null;
$files = glob(__DIR__ . '/seeds/*.php');

foreach ($files as $file) {
    $filename = basename($file, '.php');

    if ($target && $filename !== $target) continue;

    echo "Seeding: $filename...";
    require_once $file;

    if (class_exists($filename)) {
        $seeder = new $filename();
        if (method_exists($seeder, 'run')) {
            try {
                // Pass the $db object to the seeder
                $seeder->run($db);
                echo " DONE.\n";
            } catch (Exception $e) {
                echo " FAILED: " . $e->getMessage() . "\n";
            }
        } else {
            echo " SKIPPED (No run method).\n";
        }
    } else {
        echo " SKIPPED (Class not found).\n";
    }
}