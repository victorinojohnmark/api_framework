<?php

# API ROUTES

// Capture a single ID
$router->get('/users/{id}', 'UserController@show');