<?php
namespace App\Controllers;

use Core\Controller;
use Core\JWT;
use Core\Http\Cookie;

class AuthController extends Controller
{
    public function login()
    {
        $email = $this->input('email');
        $password = $this->input('password');

        // 1. Verify User credentials
        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        // 2. Generate Token
        // expiration to 24 hours for better UX
        $payload = [
            'sub'  => $user->id,
            'iat'  => time(),
            'exp'  => time() + (60 * 60 * 24) 
        ];

        $token = JWT::encode($payload);

        // 3. Set HttpOnly Cookie (For Web)
        // Mobile apps will ignore this, Web browsers will save it automatically.
        Cookie::set('auth_token', $token, 1440); // 1440 mins = 24 hours

        // 4. Return Token and User Info
        $auth = new \Core\Auth($user->id);
        
        return $this->json([
            'status' => 'success',
            'token' => $token, // Mobile apps grab this manually
            'user' => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'roles' => $auth->roles(),
                'permissions' => $auth->permissions()
            ]
        ]);
    }

    /**
     * Logout method to clear the cookie
     */
    public function logout()
    {
        Cookie::forget('auth_token');
        return $this->json(['message' => 'Logged out successfully']);
    }
}