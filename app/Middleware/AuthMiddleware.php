<?php
namespace App\Middleware;

use Core\Middleware;
use Core\JWT;

class AuthMiddleware implements Middleware
{
    public function handle()
    {
        // 1. Extract Token from Header
        $headers = getallheaders();
        $authHeader = null;

        // Handle case-sensitivity
        if (isset($headers['Authorization'])) $authHeader = $headers['Authorization'];
        elseif (isset($headers['authorization'])) $authHeader = $headers['authorization'];

        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            $this->unauthorized('Token not found');
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        // 2. Validate Token
        $payload = JWT::decode($token);

        if (!$payload) {
            $this->unauthorized('Invalid or Expired Token');
        }

        // 3. Pass User ID to the Application
        // We inject it into $_REQUEST so the Controller can access it via $this->input('auth_user_id')
        // or simple $_REQUEST['auth_user_id']
        $_REQUEST['auth_user_id'] = $payload->sub; // 'sub' is standard for Subject (User ID)
    }

    private function unauthorized($message)
    {
        header("Content-Type: application/json");
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit();
    }
}