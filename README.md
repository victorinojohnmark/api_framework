# Simple PHP 7.0 API Framework

A lightweight, self-contained, no-composer MVC framework designed for speed and simplicity. It works out-of-the-box with PHP 7.0+ and supports both local development and legacy server subdirectories.

## 1. Installation & Setup

### 1.1 Directory Structure
Ensure your project looks like this:

```text
/project-root
├── app/
│   ├── Controllers/         # API Controllers (e.g. UserController.php)
│   └── Middleware/          # Middleware Classes (e.g. AuthMiddleware.php)
├── config/
│   ├── config.php           # Main Configuration
│   └── middleware.php       # Middleware Registry
├── core/
│   ├── Database/            # Schema Builder Classes
│   │   ├── Blueprint.php
│   │   └── Schema.php
│   ├── Auth.php             # RBAC Authorization Class
│   ├── Controller.php       # Base Controller
│   ├── Database.php         # Query Builder Class
│   ├── DotEnv.php           # .env File Parser
│   ├── helpers.php          # Global Helper Functions
│   ├── JWT.php              # JWT Token Encoder/Decoder
│   ├── Middleware.php       # Middleware Interface
│   └── Router.php           # Routing Engine
├── database/
│   ├── migrations/          # Migration Files
│   ├── seeds/               # Seeder Files
│   ├── create_migration.php # CLI: Generate Migrations
│   ├── migrate.php          # CLI: Run Migrations
│   ├── rollback.php         # CLI: Rollback Migrations
│   └── seed.php             # CLI: Run Seeders
├── routes/
│   └── api.php              # Route Definitions
├── .env                     # Environment Variables (Do not commit)
├── .env.example             # Example Environment Template
├── index.php                # Entry Point
├── serve.php                # Local Development Server
└── .htaccess                # Apache Config (Optional)
```

### 1.2 Configuration

The application uses a **DotEnv** system. You should never edit `config/config.php` directly for credentials. Instead, use the `.env` file in the project root.

**1.2.1 Setup Environment Variables**
Copy the example file to create your local configuration.
```bash
cp .env.example .env
```
Open `.env` and update your settings:
```ini
APP_NAME="My Project Name"
APP_ENV=development
BASE_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila

# Server & CORS Settings
APP_PORT=8000
FRONTEND_URL=http://localhost:3000

DB_HOST=localhost
DB_NAME=projects
DB_USER=root
DB_PASS="P@ssw0rd#"

JWT_SECRET=Sup3rS3cr3tK3y!
```
**1.2.2 The Config Bridge (`config/config.php`)** This file acts as a bridge to load environment variables. It is located in `config/config.php`.
```php
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
```

### 1.3 Running Locally
You don't need Apache for local work. Use the built-in command line tool:

```bash
php serve.php
```

 Visit `http://localhost:8000/` to test.
 
 ---

## 2. Routing
Define all API endpoints in `routes/api.php`. The framework supports standard HTTP verbs, dynamic parameters, groups, and explicit middleware assignment.

### 2.1 Basic Routes
```php
// routes/api.php

// GET Request
$router->get('/health', 'SystemController@status');

// POST Request
$router->post('/auth/login', 'AuthController@login');
```

### 2.2 Dynamic Parameters
You can capture segments of the URL using `{curly_braces}`. These are passed as arguments to your controller method in the order they appear.

```php
// Capture a single ID -> show($id)
$router->get('/users/{id}', 'UserController@show');

// Capture multiple parameters -> show($project_id, $task_id)
// URL Example: /projects/10/tasks/55
$router->get('/projects/{project_id}/tasks/{task_id}', 'TaskController@show');
```

### 2.3 Middleware on Routes
You can attach middleware to individual routes by passing a third argument. This can be a single string or an array of middleware keys.

```php
// Single Middleware
$router->post('/logout', 'AuthController@logout', 'auth');

// Multiple Middleware
// Order matters: 'auth' runs first, then 'verified_only'
$router->delete('/users/{id}', 'AdminController@delete', ['auth', 'verified_only']);
```

### 2.4 Route Groups
Groups allow you to apply a shared URL prefix and/or middleware to multiple routes at once.

```php
// Group routes under '/admin' with 'auth' middleware
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function($router) {
    
    // Final URL: /admin/dashboard
    // Inherited Middleware: ['auth']
    $router->get('/dashboard', 'AdminController@dashboard');

    // Add EXTRA middleware to a specific route inside the group
    // Final Middleware: ['auth', 'super_admin']
    $router->delete('/logs', 'AdminController@clearLogs', 'super_admin');
    
});
```

### 2.5 Excluding Middleware
If you apply middleware to a group but need to make a specific route public, you can chain `excludeMiddleware`.

```php
$router->group(['prefix' => '/api', 'middleware' => ['auth']], function($router) {

    // Protected Route (Uses 'auth')
    $router->get('/profile', 'UserController@profile');

    // Public Route (Auth middleware removed for this specific route)
    $router->get('/public-info', 'UserController@info')
           ->excludeMiddleware(['auth']); 

});
```

---

## 3. Controllers
Controllers handle the incoming request and return a JSON response. All controllers must extend `Core\Controller`.

#### Location: `app/Controllers/`

### 3.1 Basic Example
```php
<?php
namespace App\Controllers;

use Core\Controller;

class UserController extends Controller
{
    /**
     * GET /users
     * Access the Database directly
     */
    public function index()
    {
        // $this->db is automatically available
        $users = $this->db->table('users')->select('id', 'name', 'email')->get();

        return $this->json($users);
    }

    /**
     * GET /users/{user_id}
     */
    public function show($userId)
    {
        $user = $this->db->table('users')->where('id', $userId)->first();

        if (!$user) {
            return $this->error('User not found', 404);
        }

        return $this->json($user);
    }

    /**
     * POST /users
     */
    public function store()
    {
        $name = $this->input('name');
        $email = $this->input('email');

        if (!$name || !$email) {
            return $this->error('Name and Email are required', 400);
        }

        $this->db->table('users')->insert([
            'name' => $name, 
            'email' => $email
        ]);

        return $this->json(['message' => 'User created'], 201);
    }
}
```

### 3.2 Accessing the Authenticated User
If a route is protected by `AuthMiddleware`, the current user's ID is injected into the request.
```php
public function profile()
{
    // 'auth_user_id' is set by the Middleware
    $currentUserId = $_REQUEST['auth_user_id'] ?? null;

    $user = $this->db->table('users')->where('id', $currentUserId)->first();
    
    return $this->json($user);
}
```

### 3.3 Dynamic URLs & Files
Use the `asset()` helper to generate correct URLs (handles HTTP/HTTPS and Custom Domains automatically).
```php
public function avatar()
{
    $filename = 'avatar-123.jpg';
    
    // Returns: [https://api.yoursite.com/storage/avatar-123.jpg](https://api.yoursite.com/storage/avatar-123.jpg)
    $url = asset('storage/' . $filename); 

    return $this->json(['url' => $url]);
}
```

### 3.4 Helper Methods
The `Core\Controller` provides these built-in methods:

* `$this->db`

  * The fluent Query Builder instance (e.g., `$this->db->table('...')`).

* `$this->json($data, $status = 200)`

  * Sends a standard JSON response and exits.

* $this->error($message, $status = 400)

  * Sends a structured error response `{"status": "error", "message": "..."}`.

* `$this->input($key = null, $default = null)`

  * Retrieves data from JSON body, `$_POST`, or `$_GET`.

### 3.5 File Handling
The controller provides a `file()` method that fixes the confusing PHP `$_FILES` structure. It recursively normalizes deep arrays, making iteration simple.

**.5.1 Single File:**
```php
$avatar = $this->file('avatar');
// Returns: ['name' => 'face.jpg', 'tmp_name' => '...', 'size' => 1024]
```

**3.5.2 Multiple Files (Arrays):**
```php
// HTML: <input type="file" name="photos[]" multiple>
$photos = $this->file('photos');

// Returns:
// [
//    0 => ['name' => '1.jpg', ...],
//    1 => ['name' => '2.jpg', ...]
// ]
```

**3.5.3 Complex / Nested Structures:** The normalizer handles multi-dimensional arrays automatically.

**HTML Input:**
```html
<input type="file" name="user[profile][avatar]">
<input type="file" name="user[docs][id_card]">
```

**Controller Usage:**
```php
public function update()
{
    $files = $this->file('user');

    // Access nested data easily
    $avatar = $files['profile']['avatar'];
    $idCard = $files['docs']['id_card'];

    if ($avatar['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($avatar['tmp_name'], 'storage/' . $avatar['name']);
    }
}
```

---

## 4. Validation
The framework includes a powerful `Core\Validator` class that handles standard rules, database checks (unique/exists), and deeply nested arrays.

### Instantiation Methods
There are two ways to use the validator depending on your needs.

#### Method A: The Controller Helper (Recommended)
Use `$this->validate()`. It automatically halts execution and returns a JSON `422 Unprocessable Entity` response if validation fails.

```php
public function store() 
{
    $input = $this->input();

    // Stops here if failed
    $this->validate($input, [
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|min:8',
        'role_id'  => 'required|exists:roles,id'
    ]);

    // Code here only runs if validation passed
    $this->db->table('users')->insert($input);
}
```

#### Method B: Manual Instantiation (For Complex Logic)
Use new `Core\Validator()` manually. This is useful when you need to validate arrays in a loop or handle errors customly. You do not need to pass the DB connection; it is auto-injected.

```php
use Core\Validator;

public function store()
{
    $validator = new Validator();

    // Check individual fields
    $validator->check($_POST['email'], 'email', ['required']);

    if (!$validator->passed()) {
        return $this->error('Custom error message', 400, $v->errors());
    }
}
```

### Validating Nested Arrays
Since the validator avoids complex "dot notation" magic, you have full control to validate multi-layer arrays using standard loops.

**Scenario:** Validating a list of order items.

```php
public function createOrder()
{
    $input = $this->input();
    $validator = new Validator();

    // 1. Validate Parent
    $validator->check($input['client_id'], 'client_id', ['required', 'exists:clients,id']);
    $validator->check($input['items'], 'items', ['required', 'array']);

    // 2. Iterate Children
    if (!empty($input['items'])) {
        foreach ($input['items'] as $index => $item) {
            
            // Validate Product ID
            $validator->check(
                $item['product_id'] ?? null, 
                "items.$index.product_id", // Error key: items.0.product_id
                ['required', 'exists:products,id']
            );

            // Validate Quantity
            $validator->check(
                $item['quantity'] ?? null, 
                "items.$index.quantity", 
                ['required', 'numeric', 'min:1']
            );
        }
    }

    if (!$validator->passed()) {
        return $this->error('Validation failed', 422, $v->errors());
    }

    // Save to DB...
}
```

### Available Rules

| Category | Rule | Example | Description |
| :--- | :--- | :--- | :--- |
| **Basic** | `required` | `required` | Field must not be empty. |
| | `nullable` | `nullable` | Allows the field to be null/empty; skips subsequent rules if empty. |
| | `bail` | `bail` | Stop validating this field after the first failure. |
| **Type** | `email` | `email` | Must be a valid email address. |
| | `numeric` | `numeric` | Must be a number. |
| | `integer` | `integer` | Must be a whole integer. |
| | `string` | `string` | Must be a string. |
| | `boolean` | `boolean` | Must be true, false, 1, 0, "1", or "0". |
| | `array` | `array` | Must be an array. |
| **Size/Value** | `min` | `min:8` | Minimum string length, numeric value, or array count. |
| | `max` | `max:50` | Maximum string length, numeric value, or array count. |
| | `greater_than` | `greater_than:10` | Value must be strictly greater than X. |
| | `less_than` | `less_than:100` | Value must be strictly less than X. |
| **Database** | `unique` | `unique:users,email` | Fails if value already exists in the table column. |
| | `unique` (Update)| `unique:users,email,5` | Same as unique, but ignores row with ID 5. |
| | `exists` | `exists:roles,id` | Fails if value does NOT exist in the table column. |
| **Comparison** | `same` | `same:password` | Value must match another request field. |
| | `different` | `different:old_pass` | Value must differ from another request field. |
| | `in` | `in:admin,editor` | Must be one of the listed values. |
| | `not_in` | `not_in:banned` | Must not be one of the listed values. |
| **Date/Time** | `date` | `date` or `date:Y-m-d` | Must be a valid date string. |
| | `time` | `time` or `time:H:i` | Must be a valid time string. |
| | `date_time` | `date_time:Y-m-d H:i:s` | Must be a valid date-time matching the format. |
| **Files** | `file` | `file:jpg,png,pdf` | Validate file upload extension. |
| | `mimes` | `mimes:image/jpeg` | Validate file MIME type (more secure). |
| | `file_size` | `file_size:2048` | Max file size in Kilobytes (KB). |
| **Advanced** | `regex` | `regex:/^[A-Z]+$/` | Must match the regular expression pattern. |

---

## 5. Database & Query Builder

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

### Advanced Features
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
---

## 6. Database Migrations

The framework includes a powerful Schema Builder and command-line tools for managing database structure, inspired by Laravel.

### Directory Structure
Migration tools are located in the `database/` folder:
* `database/migrations/` - Stores your migration files.
* `database/create_migration.php` - Generates new migration files.
* `database/migrate.php` - Runs pending migrations (UP).
* `database/rollback.php` - Reverts the last migration (DOWN).

### Creating a Migration
Use the generator script to create a timestamped file.

```bash
php database/create_migration.php CreateUsersTable
```

This generates a file in `database/migrations/` pre-filled with the Schema Builder template and default utility columns (`active`, `timestamps`, `softDelete`).

### Editing the Migration
The framework uses a fluent `Schema` and `Blueprint` system, so you don't have to write raw SQL.

**Creating a Table:**
```php
use Core\Database\Schema;
use Core\Database\Blueprint;

public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id(); // Auto-incrementing Primary Key
        
        $table->string('username', 50);
        $table->string('email', 100)->unique();
        $table->integer('age')->nullable();
        
        # Default Utilities (Included by generator)
        $table->boolean('active')->default(1);
        $table->timestamps(); // created_at, created_by, updated_at, updated_by
        $table->softDelete(); // deleted_at, deleted_by
    });
}

public function down()
{
    Schema::dropIfExists('users');
}
```

**Modifying a Table:**
Use `Schema::table()` to add, modify, or drop columns.

```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        // Add a new column
        $table->string('phone', 20)->nullable();
        
        // Modify an existing column (requires ->change())
        $table->string('username', 100)->change();
        
        // Rename a column (3rd argument REQUIRED for MySQL 5 compatibility)
        // You must re-state the full column definition
        $table->renameColumn('location', 'address', 'VARCHAR(255) DEFAULT NULL');
        
        // Drop a column
        $table->dropColumn('age');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('phone');
        // Revert other changes...
    });
}
```

### Running Migrations
Execute all pending migrations:
```bash
php database/migrate.php
```

### Rolling Back
Undo the last batch of migrations (executes the `down()` method):
```bash
php database/rollback.php
```

---

## 7. Middleware

Middleware allows you to intercept requests before they reach your controller. This is essential for Authentication, Rate Limiting, and Access Control.

### Creating Middleware
Middleware classes must implement the `Core\Middleware` interface and define a `handle()` method.

**Location:** `app/Middleware/`

**Example:** `app/Middleware/AuthMiddleware.php`
```php
<?php
namespace App\Middleware;

use Core\Middleware;
use Core\JWT;

class AuthMiddleware implements Middleware
{
    public function handle()
    {
        // 1. Check for Authorization Header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            $this->unauthorized('Token missing');
        }

        // 2. Decode Token
        $token = substr($authHeader, 7);
        $payload = JWT::decode($token);

        if (!$payload) {
            $this->unauthorized('Invalid Token');
        }

        // 3. Inject User ID into Global Request
        $_REQUEST['auth_user_id'] = $payload->sub;
    }

    private function unauthorized($msg)
    {
        header("Content-Type: application/json");
        http_response_code(401);
        echo json_encode(['error' => $msg]);
        exit(); // Stop execution
    }
}
```

### Registering Middleware
Map your middleware classes to short aliases in the configuration file.
**File:** `config/middleware.php`

```php
return [
    'auth'  => \App\Middleware\AuthMiddleware::class,
    'admin' => \App\Middleware\AdminMiddleware::class,
];
```

### Applying Middleware
**Single Route:** Pass the alias as the third argument.

```php
$router->get('/profile', 'UserController@profile', ['auth']);
```

**Route Groups:** Apply middleware (and URL prefixes) to a block of routes.
```php
$router->group(['prefix' => '/api/v1', 'middleware' => ['auth']], function($router) {
    
    // URL: /api/v1/users (Protected)
    $router->get('/users', 'UserController@index');
    
    // URL: /api/v1/orders (Protected)
    $router->get('/orders', 'OrderController@index');
    
});
```

---

## 8. Authentication (JWT)
The framework includes a native, composer-free JWT Helper.

**Configuration**
Add your secret key to your `.env` file.

```ini
JWT_SECRET=YourSuperSecretKeyHere
```

**issuing Tokens (Login)**
In your `AuthController`, use the `Core\JWT` class to generate a token.
```php
use Core\JWT;

public function login()
{
    // ... validate user credentials ...

    $payload = [
        'sub' => $user->id,      // Subject (User ID)
        'iat' => time(),         // Issued At
        'exp' => time() + 3600   // Expiration (1 hour)
    ];

    $token = JWT::encode($payload);

    return $this->json(['token' => $token]);
}
```

---

## 9. Authorization & RBAC

The framework implements a robust Role-Based Access Control (RBAC) system. It uses specific database tables (`users`, `roles`, `permissions`) to manage access dynamically.

### Step 1: Database Setup
You need to create the `users` table and the RBAC tables.

**1. Generate the Files:**
```bash
php database/create_migration.php CreateUsersTable
php database/create_migration.php CreateRbacTables
```

### Paste Schema:
**File:** `database/migrations/xxxx_CreateUsersTable.php`
```php
<?php

class CreateUsersTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            active TINYINT(1) DEFAULT 1,
            created_by INT NULL,
            created_at INT NULL,
            updated_by INT NULL,
            updated_at INT NULL,
            deleted_by INT NULL,
            deleted_at INT NULL
        )";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS users");
    }
}
```

**File:** `database/migrations/xxxx_CreateRbacTables.php`
```php
<?php

class CreateRbacTables
{
    public function up($pdo)
    {
        # Roles Table (e.g., 'admin', 'editor')
        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        )");

        # Permissions Table (e.g., 'edit-profile', 'delete-users')
        $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        )");

        # User -> Roles (Many-to-Many)
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (user_id, role_id)
        )");

        # Role -> Permissions (Many-to-Many)
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id)
        )");
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS role_permissions");
        $pdo->exec("DROP TABLE IF EXISTS user_roles");
        $pdo->exec("DROP TABLE IF EXISTS permissions");
        $pdo->exec("DROP TABLE IF EXISTS roles");
    }
}

```

### Run Migrations:
```bash
php database/migrate.php
```

#### Step 2: The `auth()` Helper     
Once your database is set up, you can access the current authenticated user's details, roles, and permissions globally using the auth() helper.

**1. Get Current User**
```php
$userId = auth()->id();
$userObj = auth()->user(); // Returns DB row object
```

**2. Check Permissions** Checks if the user has a specific permission (via any of their assigned roles).
```php
if (auth()->can('edit_posts')) {
    // Allow action
}
```

**3. Check Roles**
```php
if (auth()->hasRole('admin')) {
    // Admin only logic
}
```

**4. Get All Roles/Permissions** Useful for sending back to the frontend on login.
```php
$roles = auth()->roles();            // ['admin', 'editor']
$perms = auth()->permissions();      // ['create_post', 'delete_user']
```

#### Step 3: Usage in Controller
```php
public function destroy($id)
{
    // 1. Authentication Check (Middleware handles this, but auth() provides info)
    if (!auth()) {
        return $this->error('Not logged in', 401);
    }

    // 2. Authorization Check
    if (!auth()->can('delete_users')) {
        return $this->error('Permission denied', 403);
    }

    $this->db->table('users')->where('id', $id)->delete();
    return $this->json(['message' => 'Deleted']);
}
```

---

## 10. Database Seeding

Seeders allow you to populate your database with initial data (like Admin accounts or default settings).

### Seeder Engine
The framework includes a seeder runner located at `database/seed.php`.

### Creating a Seeder
Create a class file in `database/seeds/`. The class name must match the filename.

**File:** `database/seeds/AdminSeeder.php`

```php
<?php

class AdminSeeder
{
    /**
     * @param \Core\Database $db
     */
    public function run($db)
    {
        // Configuration
        $firstName = 'Admin';
        $lastName  = 'User';
        $email     = 'admin@admin.com';
        $password  = 'password';

        // ----------------------------------------
        // 1. Create 'admin' Role
        // ----------------------------------------
        $role = $db->table('roles')->where('name', 'admin')->first();

        if (!$role) {
            $roleId = $db->table('roles')->insert([
                'name' => 'admin', 
                'description' => 'Super Administrator'
            ]);
            echo " [Role Created]";
        } else {
            $roleId = $role->id;
            echo " [Role Exists]";
        }

        // ----------------------------------------
        // 2. Create Admin User
        // ----------------------------------------
        $user = $db->table('users')->where('email', $email)->first();

        if (!$user) {
            $userId = $db->table('users')->insert([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => password_hash($password, PASSWORD_BCRYPT),
                'active'     => 1,
                'created_at' => time()
            ]);
            echo " [User Created]";
        } else {
            $userId = $user->id;
            echo " [User Exists]";
        }

        // ----------------------------------------
        // 3. Assign Role to User
        // ----------------------------------------
        $hasRole = $db->table('user_roles')
                      ->where('user_id', $userId)
                      ->where('role_id', $roleId)
                      ->first();
        
        if (!$hasRole) {
            $db->table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
            echo " [Role Assigned]";
        }
    }
}
```

### Running Seeders
Run all seeders in the folder:
```bash
php database/seed.php
```

Run a specific seeder:
```bash
php database/seed.php AdminSeeder
```





