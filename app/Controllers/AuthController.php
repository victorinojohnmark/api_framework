<?php
namespace App\Controllers;

use Core\Controller;
use Core\JWT;

class AuthController extends Controller
{
    public function login()
    {
        $email = $this->input('email');
        $password = $this->input('password');

        // 1. Verify User credentials from DB
        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        // 2. Generate Token
        $payload = [
            'sub'  => $user->id,         // Subject (User ID)
            'iat'  => time(),            // Issued at
            'exp'  => time() + (60 * 60) // Expires in 1 hour
        ];

        $token = JWT::encode($payload);

        // 3. Return Token and User Info
        // Initialize Auth manually for this user to fetch roles easily
        $auth = new \Core\Auth($user->id);
        
        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'roles' => $auth->roles(),            // ['admin']
                'permissions' => $auth->permissions() // ['edit-profile', 'delete-user']
            ]
        ]);
    }
}