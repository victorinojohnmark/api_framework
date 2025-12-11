<?php

return [
    // alias => Class Name
    'auth' => \App\Middleware\AuthMiddleware::class,
    'admin' => \App\Middleware\AdminMiddleware::class,
    
    // Add your custom middleware here
    // 'maintenance' => \App\Middleware\MaintenanceMiddleware::class,
];