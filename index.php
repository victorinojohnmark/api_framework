<?php
/**
 * ENTRY POINT
 * Loads environment variables, configuration, routes, and dispatches requests.
 */

define('ROOT_PATH', __DIR__);

# 1. AUTOLOADER
spl_autoload_register(function ($className) {
    $file = ROOT_PATH . '/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

# 2. LOAD .ENV (STRICT CHECK)
use Core\DotEnv;

$envFile = ROOT_PATH . '/.env';

if (!file_exists($envFile)) {
    # Stop the application immediately if .env is missing
    header("HTTP/1.1 500 Internal Server Error");
    die("Error: config.php not found."); # Stop execution
}

# Load the environment variables
DotEnv::load($envFile);

# 3. LOAD CONFIGURATION
$configFile = ROOT_PATH . '/config/config.php';

if (!file_exists($configFile)) {
    header("HTTP/1.1 500 Internal Server Error");
    die("Error: config/config.php not found.");
}
$config = require $configFile;
define('APP_CONFIG', $config);

# 4. ENVIRONMENT SETUP
if ($config['env'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

# 5. Helper
require_once ROOT_PATH . '/core/helpers.php';

# 6. INITIALIZE ROUTER
use Core\Router;
$router = new Router();

# 7. LOAD ROUTES
$routesPath = ROOT_PATH . '/routes/api.php';
if (file_exists($routesPath)) {
    require_once $routesPath;
} else {
    die("Error: Routes file not found at $routesPath");
}

# 8. DISPATCH REQUEST
$router->dispatch();