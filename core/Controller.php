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

    // -------------------------------------------------------------------------
    // RESPONSE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Send a formatted JSON response and exit
     * @param mixed $data    The data to send
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
     * Send an Error response
     * Unified method to handle simple messages or validation arrays.
     * * @param string $message The error message
     * @param int    $status  HTTP Status Code (default 400)
     * @param array  $errors  Optional array of validation errors or details
     */
    protected function error($message, $status = 400, $errors = [])
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        // If extra error details provided (like validation fields), add them
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    // -------------------------------------------------------------------------
    // INPUT HELPERS
    // -------------------------------------------------------------------------

    /**
     * Get Input Data (Unified)
     * Handles JSON Body, Form Data ($_POST), and Query Params ($_GET)
     */
    protected function input($key = null, $default = null)
    {
        # 1. Read Raw JSON Body
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
     * Get a normalized file or array of files from $_FILES
     */
    public function file($key = null)
    {
        if ($key) {
            if (!isset($_FILES[$key])) {
                return null;
            }
            return $this->normalizeFiles($_FILES[$key]);
        }

        $normalized = [];
        foreach ($_FILES as $k => $v) {
            $normalized[$k] = $this->normalizeFiles($v);
        }
        return $normalized;
    }

    /**
     * Recursive helper to normalize PHP's $_FILES structure
     */
    private function normalizeFiles($file)
    {
        if (is_array($file['name'])) {
            $files = [];
            foreach ($file['name'] as $k => $v) {
                $files[$k] = $this->normalizeFiles([
                    'name'     => $file['name'][$k],
                    'type'     => $file['type'][$k],
                    'tmp_name' => $file['tmp_name'][$k],
                    'error'    => $file['error'][$k],
                    'size'     => $file['size'][$k],
                ]);
            }
            return $files;
        }
        return $file;
    }

    // -------------------------------------------------------------------------
    // AUTH & VALIDATION HELPERS
    // -------------------------------------------------------------------------

    /**
     * Get the authenticated user data
     */
    protected function user()
    {
        if (!isset($_REQUEST['auth_user_id'])) {
            return null;
        }

        return (object) [
            'id'   => $_REQUEST['auth_user_id'],
            'role' => $_REQUEST['auth_user_role'] ?? 'user',
        ];
    }

    /**
     * Get a fresh Validator instance
     */
    public function getValidator()
    {
        return new Validator($this->db);
    }

    /**
     * Helper: Validate and Stop on Error
     * Validates data and automatically sends a 422 JSON response if it fails.
     * * @param array $data  Input data
     * @param array $rules Rule definitions
     * @return array       Returns original data if valid
     */
    public function validate($data, $rules)
    {
        $v = $this->getValidator();

        foreach ($rules as $field => $fieldRules) {
            // Support pipe syntax "required|email"
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            
            // Run the check using your custom Validator class
            $v->check($data[$field] ?? null, $field, $fieldRules);
        }

        // Check if global pass status is false
        if (!$v->passed()) {
            // Stops execution here and returns JSON
            $this->error('Validation failed', 422, $v->errors);
        }

        return $data; 
    }
}