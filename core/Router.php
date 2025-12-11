<?php
namespace Core;

class Router
{
    protected $routes = [];
    
    // State for Route Grouping
    protected $groupPrefix = '';
    protected $groupMiddleware = [];

    /**
     * Define a Route Group
     * @param array    $attributes  ['prefix' => '/admin', 'middleware' => ['auth']]
     * @param callable $callback    Function to register routes
     */
    public function group(array $attributes, callable $callback)
    {
        // 1. BACKUP Previous State (To support nested groups)
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // 2. APPLY New Attributes
        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= '/' . trim($attributes['prefix'], '/');
        }
        
        if (isset($attributes['middleware'])) {
            // Ensure middleware is always an array
            $newMiddleware = (array) $attributes['middleware'];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $newMiddleware);
        }

        // 3. EXECUTE Callback (The routes inside the closure are registered now)
        // We pass $this (the router) to the closure
        call_user_func($callback, $this);

        // 4. RESTORE Previous State (Pop stack)
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get($route, $action, $middleware = []) { $this->add('GET', $route, $action, $middleware); }
    public function post($route, $action, $middleware = []) { $this->add('POST', $route, $action, $middleware); }
    public function put($route, $action, $middleware = []) { $this->add('PUT', $route, $action, $middleware); }
    public function delete($route, $action, $middleware = []) { $this->add('DELETE', $route, $action, $middleware); }

    /**
     * Internal method to store the route
     */
    private function add($method, $route, $action, $middleware = [])
    {
        // 1. MERGE Group Prefix
        // If inside a group, prepend the prefix
        $finalRoute = $this->groupPrefix . $route;
        
        // Ensure no double slashes (//) unless it's root
        if ($finalRoute !== '/') {
            $finalRoute = rtrim($finalRoute, '/');
        }

        // 2. MERGE Group Middleware
        // Merge group middleware with route-specific middleware
        $finalMiddleware = array_merge($this->groupMiddleware, (array)$middleware);

        // 3. Regex Conversion
        $pattern = preg_replace('/\{([a-zA-Z0-9-_]+)\}/', '([a-zA-Z0-9-_]+)', $finalRoute);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'action'     => $action,
            'middleware' => $finalMiddleware
        ];
    }

    // ... (Keep your dispatch() and sendError() methods exactly as they were) ...
    public function dispatch()
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }
        if ($uri === '' || $uri[0] !== '/') { $uri = '/' . $uri; }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);

                // Check Middleware
                if (!empty($route['middleware'])) {
                    // Adjust this path if needed
                    $configFile = defined('ROOT_PATH') ? ROOT_PATH . '/config/middleware.php' : __DIR__ . '/../config/middleware.php';
                    
                    if (file_exists($configFile)) {
                        $registry = require $configFile;
                        foreach ($route['middleware'] as $alias) {
                            if (isset($registry[$alias])) {
                                $cls = $registry[$alias];
                                (new $cls)->handle();
                            }
                        }
                    }
                }

                // Dispatch Controller
                $parts = explode('@', $route['action']);
                $controllerName = "App\\Controllers\\" . $parts[0];
                $methodName = $parts[1];

                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    if (method_exists($controller, $methodName)) {
                        call_user_func_array([$controller, $methodName], $matches);
                        return;
                    }
                }
            }
        }
        
        $this->sendError(404, "Route not found");
    }

    private function sendError($code, $message) {
        header("Content-Type: application/json");
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit();
    }
}