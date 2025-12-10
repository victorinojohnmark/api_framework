<?php
/**
 * ENTRY POINT
 * Loads config, sets up environment, and dispatches routes.
 */

# 1. DEFINE CONSTANTS
define('ROOT_PATH', __DIR__);

# 2. LOAD CONFIGURATION
$configFile = ROOT_PATH . '/config.php';
if (!file_exists($configFile)) {
    die("Error: config.php not found.");
}
$config = require $configFile;

# Store config in a global constant for easy access across the framework
# (Alternative: You could use a Config class, but this is lighter)
define('APP_CONFIG', $config);

# 3. ENVIRONMENT SETUP
if ($config['env'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    # Production: Hide errors from user, log them instead
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    # ini_set('error_log', ROOT_PATH . '/logs/error.log'); # Optional logging
}

# 4. AUTOLOADER
spl_autoload_register(function ($className) {
    # Convert namespace to full file path
    # App\Controllers\User -> app/Controllers/User.php
    $file = ROOT_PATH . '/' . str_replace('\\', '/', $className) . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        if (APP_CONFIG['env'] === 'development') {
            die("Autoloader Error: Class '$className' not found in '$file'");
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            exit();
        }
    }
});

# 5. INITIALIZE ROUTER
use Core\Router;
$router = new Router();

# 6. LOAD ROUTES
$routesPath = ROOT_PATH . '/routes/api.php';
if (file_exists($routesPath)) {
    # Pass $router to the included file
    require_once $routesPath;
} else {
    die("Error: Routes file not found at $routesPath");
}

# 7. DISPATCH REQUEST
$router->dispatch();