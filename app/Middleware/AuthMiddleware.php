<?php
namespace App\Middleware;

use Core\Middleware;
use Core\JWT;
use Core\Http\Cookie;

class AuthMiddleware implements Middleware
{
    public function handle()
    {
        $token = null;
        $headers = getallheaders();

        // 1. Priority: Check Authorization Header (Mobile/Postman)
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        }

        // 2. Fallback: Check Cookie (Web)
        if (!$token) {
            $token = Cookie::get('auth_token');
        }

        // 3. Verify
        if (!$token) {
            $this->unauthorized('Token missing');
        }

        $payload = JWT::decode($token);
        if (!$payload) {
            $this->unauthorized('Invalid or Expired Token');
        }

        $_REQUEST['auth_user_id'] = $payload->sub;
    }

    private function unauthorized($msg)
    {
        header("Content-Type: application/json");
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit();
    }
}