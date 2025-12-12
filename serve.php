<?php
/**
 * Local Development Server Launcher
 * Usage: php serve.php
 */

define('ROOT_PATH', __DIR__);

// 1. Load DotEnv to get the port
require_once ROOT_PATH . '/core/DotEnv.php';
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    Core\DotEnv::load($envFile);
}

// 2. Get Config
$config = require ROOT_PATH . '/config/config.php';
$port = $config['port'];

echo "------------------------------------------------\n";
echo "Starting Development Server on Port $port\n";
echo "Press Ctrl+C to stop.\n";
echo "------------------------------------------------\n";

// 3. Start PHP Built-in Server
// We point the server to the current directory (ROOT_PATH)
// and use index.php as the router/entry point implicitly if no static file found
passthru("php -S localhost:$port -t " . ROOT_PATH . " index.php");