<?php

use Core\Auth;

// Global Singleton to hold the Auth instance
// This ensures we don't re-query the DB if we call auth() multiple times
global $app_auth_instance;
$app_auth_instance = null;

if (!function_exists('auth')) {
    /**
     * Access the Auth System
     * @return Core\Auth|null
     */
    function auth() {
        global $app_auth_instance;

        // If already initialized, return it
        if ($app_auth_instance) {
            return $app_auth_instance;
        }

        // Check if user is logged in (set by Middleware)
        if (isset($_REQUEST['auth_user_id'])) {
            $app_auth_instance = new Auth($_REQUEST['auth_user_id']);
            return $app_auth_instance;
        }

        return null;
    }
}

if (!function_exists('getallheaders')) {
    /**
     * Polyfill for getallheaders() if the server is missing it (e.g. Nginx/FPM)
     * @return array
     */
    function getallheaders() {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            // 1. Handle standard headers (HTTP_...)
            if (substr($name, 0, 5) == 'HTTP_') {
                // HTTP_USER_AGENT -> User-Agent
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
            
            // 2. Handle special cases (Content-Type, Content-Length)
            // These sometimes don't have the HTTP_ prefix in $_SERVER
            elseif ($name == 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            }
            elseif ($name == 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        
        return $headers;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a full URL to a file based on the current request domain.
     * * @param string $path Path to the file (e.g. 'uploads/avatar.jpg')
     * @return string
     */
    function asset($path = '') {
        // 1. Detect Protocol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            ? "https" 
            : "http";

        // 2. Detect Host
        // If accessed via api.myclient.com, this will be "api.myclient.com"
        // If accessed via api.mysoftware.com, this will be "api.mysoftware.com"
        $host = $_SERVER['HTTP_HOST'];

        // 3. Clean Path
        $cleanPath = ltrim($path, '/');

        return "{$protocol}://{$host}/{$cleanPath}";
    }
}