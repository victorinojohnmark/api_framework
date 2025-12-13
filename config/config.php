<?php
// config/config.php

return [
    # App Settings
    'app_name' => getenv('APP_NAME') ?: 'Project Name',
    'env'      => getenv('APP_ENV') ?: 'production',
    'base_url' => getenv('BASE_URL') ?: 'http://localhost',
    'port'         => getenv('APP_PORT') ?: '8000',
    'frontend_url' => getenv('FRONTEND_URL') ?: '*',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',

    # Database Settings
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'test',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',

    # JWT Settings
    'jwt_secret' => getenv('JWT_SECRET') ?: 'default-secret',
    
    # Paths
    'root_path' => dirname(__DIR__),
];