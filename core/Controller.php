<?php
namespace Core;

/**
 * Base Controller
 * All controllers will extend this class.
 */
class Controller
{
    /** @var Database */
    protected $db;

    public function __construct()
    {
        # 1. Initialize Database Connection
        if (class_exists('Core\Database')) {
            $this->db = new Database();
        }
    }

    /**
     * Send a formatted JSON response
     * * @param mixed $data    The data to send
     * @param int   $status  HTTP Status Code (200, 201, 400, etc.)
     */
    protected function json($data, $status = 200)
    {
        header("Content-Type: application/json");
        http_response_code($status);
        echo json_encode($data);
        exit(); # Stop execution immediately
    }

    /**
     * Send an Error response (Shortcut)
     */
    protected function error($message, $status = 400, $details = [])
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        $this->json($response, $status);
    }

    /**
     * Get Input Data (Unified)
     * Handles JSON Body, Form Data ($_POST), and Query Params ($_GET)
     * * @param string|null $key      Specific key to retrieve (optional)
     * @param mixed       $default  Default value if key is missing
     * @return mixed
     */
    protected function input($key = null, $default = null)
    {
        # 1. Read Raw JSON Body (for React/Vue/Mobile apps)
        $json = json_decode(file_get_contents('php://input'), true);
        if (!$json) {
            $json = [];
        }

        # 2. Merge all inputs: JSON > POST > GET
        # (JSON overrides POST, POST overrides GET)
        $allData = array_merge($_GET, $_POST, $json);

        # 3. Return specific key or all data
        if ($key) {
            return isset($allData[$key]) ? $allData[$key] : $default;
        }

        return $allData;
    }

    /**
     * Basic Validation Helper
     * Checks if required keys exist in input
     * * @param array $requiredKeys List of keys that must be present
     * @return bool|array Returns true if valid, or array of missing keys
     */
    protected function validate($requiredKeys)
    {
        $input = $this->input();
        $missing = [];

        foreach ($requiredKeys as $key) {
            if (empty($input[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $this->error("Missing required fields: " . implode(', ', $missing), 400);
        }

        return true;
    }

    /**
     * Get the authenticated user data
     * Returns an object: $this->user()->id, $this->user()->role
     * @return object|null
     */
    protected function user()
    {
        // Check if AuthMiddleware has run
        if (!isset($_REQUEST['auth_user_id'])) {
            return null;
        }

        // Return an anonymous object for clean syntax
        return (object) [
            'id'   => $_REQUEST['auth_user_id'],
            'role' => $_REQUEST['auth_user_role'] ?? 'user',
            // You can add more fields here if your Middleware saves them
        ];
    }
}