<?php
namespace App\Controllers;

use Core\Controller;

class UserController extends Controller
{
    public function index()
    {
        $data = [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];

        return $this->json($data);
    }

    public function show($id)
    {
        // Mock data logic
        if ($id == 999) {
            return $this->error('User not found', 404);
        }

        return $this->json([
            'id' => $id,
            'name' => 'User ' . $id
        ]);
    }

    public function profile()
    {
        $userId = $this->user()->id;
        $role = $this->user()->role;

        $data = $this->db->table('users')->where('id', $userId)->first();

        return $this->json($data);
    }

    public function register()
    {
        // Get Input
        $firstName = $this->input('first_name');
        $lastName  = $this->input('last_name');
        $email     = $this->input('email');
        $password  = $this->input('password');

        // Validate
        if (!$firstName || !$lastName || !$email || !$password) {
            return $this->error("First Name, Last Name, Email, and Password are required.", 400);
        }

        if (strlen($password) < 6) {
            return $this->error("Password must be at least 6 characters.", 400);
        }

        // Check unique email
        $existing = $this->db->table('users')->where('email', $email)->first();
        if ($existing) {
            return $this->error("Email already exists.", 409);
        }

        // Create User
        $newUserId = $this->db->table('users')->insert([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'active'     => 1,
            'created_at' => time()
        ]);

        return $this->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'user_id' => $newUserId
        ], 201);
    }

    public function update($id)
    {
        // 1. Check Permission
        if (!auth()->can('edit-users')) {
            return $this->error('You do not have permission to edit users.', 403);
        }

        // 2. Check Role
        if (auth()->hasRole('super-admin')) {
            // Do special admin logic...
        }

        // 3. Get Role List
        $myRoles = auth()->roles(); // Returns ['admin', 'manager']

        return $this->json(['status' => 'updated']);
    }
}