<?php
namespace Core;

class Router
{
    protected $routes = [];

    /**
     * Register a GET route
     * @param string $route  The URL pattern (e.g., /users/{id})
     * @param string $action The Controller action (e.g., UserController@show)
     */
    public function get($route, $action)
    {
        $this->add('GET', $route, $action);
    }

    /**
     * Register a POST route
     */
    public function post($route, $action)
    {
        $this->add('POST', $route, $action);
    }

    /**
     * Register a PUT route (for updates)
     */
    public function put($route, $action)
    {
        $this->add('PUT', $route, $action);
    }

    /**
     * Register a DELETE route
     */
    public function delete($route, $action)
    {
        $this->add('DELETE', $route, $action);
    }

    /**
     * Internal method to store the route with Regex conversion
     */
    private function add($method, $route, $action)
    {
        # Convert parameters like {id} into Regex capture groups
        # {id} becomes ([a-zA-Z0-9-_]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9-_]+)\}/', '([a-zA-Z0-9-_]+)', $route);
        
        # Add start (^) and end ($) anchors to ensure exact match
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'action' => $action
        ];
    }

    /**
     * Dispatch the request to the correct controller
     */
    public function dispatch()
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        # --- SUBDIRECTORY FIX ---
        # This ensures the router ignores the physical folder path
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        
        # If we are in a subdirectory (not root), strip the prefix
        if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }
        
        # Ensure URI always starts with /
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        # ------------------------

        # Loop through registered routes
        foreach ($this->routes as $route) {
            # Check Method matches AND Pattern matches
            if ($route['method'] === $requestMethod && preg_match($route['pattern'], $uri, $matches)) {
                
                # Remove the first item (full string match), keep only the params
                array_shift($matches);

                # Split "Controller@Method"
                $parts = explode('@', $route['action']);
                $controllerName = "App\\Controllers\\" . $parts[0];
                $methodName = $parts[1];

                # Check if Controller class exists
                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    
                    # Check if Method exists
                    if (method_exists($controller, $methodName)) {
                        # Call the method and pass parameters (e.g., $id)
                        call_user_func_array([$controller, $methodName], $matches);
                        return;
                    } else {
                        $this->sendError(500, "Method '$methodName' not found in $controllerName");
                    }
                } else {
                    $this->sendError(500, "Controller class '$controllerName' not found");
                }
            }
        }

        # If no route matched
        $this->sendError(404, "Route not found");
    }

    /**
     * Helper to send JSON error response
     */
    private function sendError($code, $message)
    {
        header("Content-Type: application/json");
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit();
    }
}