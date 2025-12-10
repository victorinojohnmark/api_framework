<?php
/**
 * Local Development Server Router
 * Usage: php -S localhost:8000 serve.php
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// 1. If the file exists physically (e.g., assets/style.css), serve it directly.
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let PHP serve the file as is
}

// 2. Otherwise, delegate everything to index.php
require_once __DIR__ . '/index.php';