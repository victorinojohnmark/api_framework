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