<?php
namespace Core;

class Router
{
    protected $routes = [];
    
    // State for Route Grouping
    protected $groupPrefix = '';
    protected $groupMiddleware = [];

    // Track the last added route index to allow modification
    protected $lastRouteIndex = null;

    /**
     * Define a Route Group
     */
    public function group(array $attributes, callable $callback)
    {
        // 1. BACKUP Previous State
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // 2. APPLY New Attributes
        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= '/' . trim($attributes['prefix'], '/');
        }
        
        if (isset($attributes['middleware'])) {
            $newMiddleware = (array) $attributes['middleware'];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $newMiddleware);
        }

        // 3. EXECUTE Callback
        call_user_func($callback, $this);

        // 4. RESTORE Previous State
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    // All methods now return $this to allow chaining
    public function get($route, $action, $middleware = []) { return $this->add('GET', $route, $action, $middleware); }
    public function post($route, $action, $middleware = []) { return $this->add('POST', $route, $action, $middleware); }
    public function put($route, $action, $middleware = []) { return $this->add('PUT', $route, $action, $middleware); }
    public function delete($route, $action, $middleware = []) { return $this->add('DELETE', $route, $action, $middleware); }

    /**
     * Exclude specific middleware from the last added route
     * @param array|string $names Middleware keys to remove
     * @return $this
     */
    public function excludeMiddleware($names)
    {
        // Safety check: ensure a route was actually added before calling this
        if ($this->lastRouteIndex === null || !isset($this->routes[$this->lastRouteIndex])) {
            return $this;
        }

        $names = (array) $names; // Ensure input is an array
        
        // Get current middleware of the last added route
        $current = $this->routes[$this->lastRouteIndex]['middleware'];

        // Remove the specified middleware
        // array_diff returns entries from $current that are NOT in $names
        $filtered = array_diff($current, $names);

        // Re-assign (array_values ensures keys are re-indexed cleanly)
        $this->routes[$this->lastRouteIndex]['middleware'] = array_values($filtered);

        return $this;
    }

    /**
     * Internal method to store the route
     * Returns $this
     */
    private function add($method, $route, $action, $middleware = [])
    {
        // 1. MERGE Group Prefix
        $finalRoute = $this->groupPrefix . $route;
        
        if ($finalRoute !== '/') {
            $finalRoute = rtrim($finalRoute, '/');
        }

        // 2. MERGE Group Middleware
        $finalMiddleware = array_merge($this->groupMiddleware, (array)$middleware);

        // 3. Regex Conversion
        $pattern = preg_replace('/\{([a-zA-Z0-9-_]+)\}/', '([a-zA-Z0-9-_]+)', $finalRoute);
        $pattern = '#^' . $pattern . '$#';

        // 4. Store Route
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'action'     => $action,
            'middleware' => $finalMiddleware
        ];

        // Save the index of this new route so excludeMiddleware can find it
        $this->lastRouteIndex = count($this->routes) - 1;

        return $this;
    }

    // -- dispatch method --
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