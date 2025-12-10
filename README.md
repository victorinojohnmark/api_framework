# Simple PHP 7.0 API Framework

A lightweight, self-contained, no-composer MVC framework designed for speed and simplicity. It works out-of-the-box with PHP 7.0+ and supports both local development and legacy server subdirectories.

## 1. Installation & Setup

### Directory Structure
Ensure your project looks like this:

```text
/project-root-folder
├── app/
│   └── Controllers/       # Your Logic
├── core/
│   ├── Controller.php     # Base Controller
│   └── Router.php         # Routing Engine
├── routes/
│   └── api.php            # Route Definitions
├── config.php             # Environment Config
├── index.php              # Entry Point
├── serve.php              # Local Development Server
└── .htaccess              # Apache Config (Optional)
```

### Configuration (`config.php`)
Set your environment variables in the root `config.php` file.

```php
<?php
return [
    # App Settings
    'app_name' => 'Project Name',
    'env'      => 'development', # 'development' or 'production'
    'base_url' => 'http://localhost:8000',

    # Database Settings
    'db_host' => 'localhost',
    'db_name' => 'database_name',
    'db_user' => 'root',
    'db_pass' => '',
    
    # Paths
    'root_path' => __DIR__,
];
```

### Running Locally
You don't need Apache for local work. Use the built-in command line tool:

```bash
php -S localhost:8000 serve.php
```

 Visit `http://localhost:8000/` to test.
 
 ---

## 2. Routing
Define all API endpoints in `routes/api.php`. The framework supports standard HTTP verbs and dynamic parameters.

### Basic Routes
```php
// routes/api.php

// GET Request
$router->get('/health', 'App\Controllers\SystemController@status');

// POST Request
$router->post('/auth/login', 'App\Controllers\AuthController@login');

```

### Dynamic Parameters
You can capture segments of the URL using `{curly_braces}`.
```php
// Capture a single ID
$router->get('/users/{id}', 'App\Controllers\UserController@show');

// Capture multiple parameters
// URL Example: /projects/10/tasks/55
$router->get('/projects/{project_id}/tasks/{task_id}', 'App\Controllers\TaskController@show');
```

---

## 3. Controllers
Controllers handle the incoming request and return a JSON response. All controllers must extend `Core\Controller`.

#### Location: `app/Controllers/`

#### Example Controller

```php
<?php
namespace App\Controllers;

use Core\Controller;

class UserController extends Controller
{
    /**
     * GET /users
     */
    public function index()
    {
        $data = [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];

        return $this->json($data);
    }

    /**
     * GET /users/{id}
     * The $id argument matches the route parameter {id}
     */
    public function show($userId) # {user_id}
    {
        // Mock data logic
        if ($userId == 999) {
            return $this->error('User not found', 404);
        }

        return $this->json([
            'id' => $userId,
            'name' => 'User ' . $userId
        ]);
    }

    /**
     * POST /users
     */
    public function store()
    {
        // helper to get JSON body or POST data
        $name = $this->input('name');
        $email = $this->input('email');

        // Simple validation
        if (!$name || !$email) {
            return $this->error('Name and Email are required', 400);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'User created'
        ], 201);
    }
}
```

### Helper Methods
The `Core\Controller` provides these built-in methods:

* `$this->json($data, $status = 200)`

  * Sends a standard JSON response and terminates the script.

* `$this->error($message, $status = 400)`

  * Sends a structured error response (e.g., {"status": "error", "message": "..."}).

* `$this->input($key = null, $default = null)`

  * Retrieves data from JSON body, `$_POST`, or `$_GET`.

  * Example: `$this->input('email')`.