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