# Simple PHP 7.0 API Framework

A lightweight, self-contained, no-composer MVC framework designed for speed and simplicity. It works out-of-the-box with PHP 7.0+ and supports both local development and legacy server subdirectories.

## 1. Installation & Setup

### 1.1 Directory Structure
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

### 1.2 Configuration (`config.php`)
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

### 1.3 Running Locally
You don't need Apache for local work. Use the built-in command line tool:

```bash
php -S localhost:8000 serve.php
```

 Visit `http://localhost:8000/` to test.
 
 ---

## 2. Routing
Define all API endpoints in `routes/api.php`. The framework supports standard HTTP verbs and dynamic parameters.

### 2.1 Basic Routes
```php
// routes/api.php

// GET Request
$router->get('/health', 'SystemController@status');

// POST Request
$router->post('/auth/login', 'AuthController@login');

```

### 2.2 Dynamic Parameters
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

### 3.1 Example Controller

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
     * GET /users/{user_id}
     * The $useId argument matches the route parameter {user_id}
     */
    public function show($userId)
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

### 3.2 Helper Methods
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

### 4.1 Accessing the Database
In any Controller, use `$this->db` to start a query.

### 4.2 Basic Queries (SELECT)

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

### 4.3 Filtering (WHERE)
#### 4.3.1 Standard Where:

```php
$this->db->table('products')
    ->where('price', '>', 100)
    ->where('status', 'active') // defaults to '='
    ->get();
```

#### 4.3.2 Raw Where String: Useful for hardcoded checks or complex logic.
```php
$this->db->table('users')->where("id > 50 AND role = 'admin'")->get();
```

#### 4.3.3 Complex Filtering (whereRaw)
Use this when you need complex SQL logic (like brackets or OR conditions) mixed with variables. 
**Always use `?` for variables.**

```php
$age = 18;
$role = 'admin';

$this->db->table('users')
    ->whereRaw("(age > ? OR role = ?)", [$age, $role])
    ->get();
```

### 3. Joins
#### 3.1 Support for `join`, `leftJoin`, and `rightJoin`
```php
$data = $this->db->table('projects')
    ->select('projects.title, users.email')
    ->leftJoin('users', 'projects.owner_id = users.id')
    ->get();
```

### 4. Pagination and Sorting
```php
$users = $this->db->table('users')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20) // Skip first 20 records (Page 3)
    ->get();
```

### 5. Write Operations
**5.1 Insert:** Returns the last inserted ID.
```php
$newId = $this->db->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

**5.2 Update:** Returns `true` on success
```php
$this->db->table('users')
    ->where('id', 5)
    ->update(['status' => 'inactive']);
```

**5.3 Delete:**
```php
$this->db->table('users')->where('id', 5)->delete();
```

### 6. Advanced Features
**6.1 Raw SQL Queries:** For complex reports or optimization. Always use ? placeholders for safety.
```php
$sql = "SELECT count(*) as total FROM users WHERE created_at > ?";
$result = $this->db->query($sql, ['2023-01-01']);
```

**6.2 Debugging (`getQuery`):** See the generated SQL without running it.
```php
$debug = $this->db->table('users')
    ->where('id', 1)
    ->getQuery();

// Output: ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1]]
```
---

## 5. Database Migrations

The framework includes a custom command-line tool for managing database schemas, similar to Laravel's artisan.

### Directory Structure
Migration tools are located in the `database/` folder:
* `database/migrations/` - Stores your migration files.
* `database/create_migration.php` - Generates new migration files.
* `database/migrate.php` - Runs pending migrations (UP).
* `database/rollback.php` - Reverts the last migration (DOWN).
`
### 5.1 Creating a Migration
Use the generator script to create a timestamped file.

```bash
php database/create_migration.php CreateUsersTable
```
This generates a file in `database/migrations/` with default utility columns (created_at, updated_at, active, etc.).

### 5.2 Editing the Migration
Open the generated file. You will see two methods: `up()` and `down()`.

**Creating a Table:**
```php
public function up($pdo)
{
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        
        # Default Utilities (Included by generator)
        active TINYINT(1) DEFAULT 1,
        created_at INT NULL,
        updated_at INT NULL
    )";
    $pdo->exec($sql);
}

public function down($pdo)
{
    $pdo->exec("DROP TABLE IF EXISTS users");
}
```

**Modifying a Table (Adding Columns):** Change the SQL to use `ALTER TABLE`.
```php
public function up($pdo)
{
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
}

public function down($pdo)
{
    $pdo->exec("ALTER TABLE users DROP COLUMN phone");
}
```

### 5.3 Running Migrations
Execute all pending migrations:
```bash
php database/migrate.php
```

### 5.4 Rolling Back
Undo the **last** batch of migrations (executes the `down()` method):
```bash
php database/rollback.php
```








