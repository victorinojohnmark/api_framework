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
$router->get('/health', 'SystemController@status');

// POST Request
$router->post('/auth/login', 'AuthController@login');

```

### Dynamic Parameters
You can capture segments of the URL using `{curly_braces}`.
```php
// Capture a single ID
$router->get('/users/{id}', 'UserController@show');

// Capture multiple parameters
// URL Example: /projects/10/tasks/55
$router->get('/projects/{project_id}/tasks/{task_id}', 'TaskController@show');
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

---

## 4. Database & Query Builder

The framework includes a powerful, chainable Query Builder. It returns results as standard PHP Objects (`stdClass`), allowing clean arrow syntax (e.g., `$user->email`).

### Accessing the Database
In any Controller, use `$this->db` to start a query.

### Basic Queries (SELECT)

**Get all rows:**

```php
$users = $this->db->table('users')->get();
```

#### Get a single row:

```php
$user = $this->db->table('users')->where('id', 1)->first();
echo $user->name; // Access as object
```

#### Select specific columns:

```php
// Array syntax
$this->db->table('users')->select(['id', 'email'])->get();

// String syntax (useful for joins)
$this->db->table('users')->select('users.id, projects.name')->get();
```

### Filtering (WHERE)
#### Standard Where:

```php
$this->db->table('products')
    ->where('price', '>', 100)
    ->where('status', 'active') // defaults to '='
    ->get();
```

#### Raw Where String: Useful for hardcoded checks or complex logic.
```php
$this->db->table('users')->where("id > 50 AND role = 'admin'")->get();
```

#### Complex Filtering (whereRaw)
Use this when you need complex SQL logic (like brackets or OR conditions) mixed with variables. 
**Always use `?` for variables.**

```php
$age = 18;
$role = 'admin';

$this->db->table('users')
    ->whereRaw("(age > ? OR role = ?)", [$age, $role])
    ->get();
```

### Joins
#### Support for `join`, `leftJoin`, and `rightJoin`
```php
$data = $this->db->table('projects')
    ->select('projects.title, users.email')
    ->leftJoin('users', 'projects.owner_id = users.id')
    ->get();
```

### Pagination and Sorting
```php
$users = $this->db->table('users')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20) // Skip first 20 records (Page 3)
    ->get();
```

### Write Operations
**Insert:** Returns the last inserted ID.
```php
$newId = $this->db->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

**Update:** Returns `true` on success
```php
$this->db->table('users')
    ->where('id', 5)
    ->update(['status' => 'inactive']);
```

**Delete:**
```php
$this->db->table('users')->where('id', 5)->delete();
```

**Advanced Features**
**Raw SQL Queries:** For complex reports or optimization. Always use ? placeholders for safety.
```php
$sql = "SELECT count(*) as total FROM users WHERE created_at > ?";
$result = $this->db->query($sql, ['2023-01-01']);
```

**Debugging (`getQuery`):** See the generated SQL without running it.
```php
$debug = $this->db->table('users')
    ->where('id', 1)
    ->getQuery();

// Output: ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1]]
```










