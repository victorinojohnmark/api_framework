<?php
// database/seed.php

// 1. Setup Paths
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/core/DotEnv.php';
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) Core\DotEnv::load($envFile);

$config = require ROOT_PATH . '/config/config.php';

// 2. Connect to DB
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage() . "\n");
}

echo "--------------------------------\n";
echo "  Database Seeder\n";
echo "--------------------------------\n";

// 3. Determine which files to run
// Usage: php database/seed.php (Runs all)
// Usage: php database/seed.php AdminSeeder (Runs specific)
$target = isset($argv[1]) ? $argv[1] : null;

$files = glob(__DIR__ . '/seeds/*.php');

foreach ($files as $file) {
    $filename = basename($file, '.php');

    // If a specific target was requested, skip others
    if ($target && $filename !== $target) {
        continue;
    }

    echo "Seeding: $filename...";
    require_once $file;

    if (class_exists($filename)) {
        $seeder = new $filename();
        if (method_exists($seeder, 'run')) {
            try {
                // Pass PDO to the run method
                $seeder->run($pdo);
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