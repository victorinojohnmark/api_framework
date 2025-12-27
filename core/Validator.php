<?php
namespace Core;

use \PDO;
use \DateTime;
use \Exception;

class Validator
{
    /** @var Database|null */
    protected $db;

    protected static $globalDb;
    
    public $errors = [];
    protected $pass = true;
    protected $customNames = [];
    protected $customMessages = [];
    protected $defaultBail = false;

    /**
     * Set the global database instance for all Validators
     * Call this ONCE in index.php
     */
    public static function boot($db)
    {
        self::$globalDb = $db;
    }

    /**
     * @param Database|null $db
     */
    public function __construct($db = null)
    {
        // 1. Use passed DB if available
        if ($db) {
            $this->db = $db;
        } 
        // 2. Fallback to Global DB
        elseif (self::$globalDb) {
            $this->db = self::$globalDb;
        }
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Run validation on a single field against a set of rules
     * @param mixed  $value The value to check
     * @param string $name  The field name (e.g. 'email' or 'items.0.id')
     * @param array  $rules Array of rules (e.g. ['required', 'email'])
     */
    public function check($value, $name, $rules)
    {
        $hasNullable = in_array('nullable', $rules);
        $hasRequired = in_array('required', $rules);
        $hasBail = in_array('bail', $rules);
        $shouldBail = $hasBail || $this->defaultBail;
    
        // Check if empty (handling null, empty string, or empty array)
        $isEmpty = $value === null || $value === '' || (is_array($value) && count($value) === 0);
    
        // Handle nullable: if empty and nullable, skip remaining rules
        if ($hasNullable && $isEmpty) {
            return; 
        }
    
        foreach ($rules as $rule) {
            // Skip control flags during validation loop
            if (in_array($rule, ['nullable', 'bail'])) continue;
    
            list($ruleName, $params) = $this->parseRule($rule);
    
            // REQUIRED: Always short-circuit if this fails
            if ($ruleName === 'required') {
                $result = $this->validateRequired($value, $params, $name);
                if ($result !== true) {
                    $this->addError($name, $ruleName, $result, $params);
                    if ($shouldBail) return;
                    continue; 
                }
                // If required passes, continue to other rules
                continue;
            }
    
            // STANDARD RULES
            $method = 'validate' . ucfirst($ruleName);
            if (method_exists($this, $method)) {
                $result = $this->$method($value, $params, $name);
                if ($result !== true) {
                    $this->addError($name, $ruleName, $result, $params);
                    if ($shouldBail) return;
                }
            } else {
                // Fallback for unknown rules
                $this->errors[$name][] = "Unknown rule: $ruleName";
                $this->pass = false;
                if ($shouldBail) return;
            }
        }
    }

    /**
     * Check if validation passed
     */
    public function passed()
    {
        return $this->pass;
    }

    /**
     * Get all errors
     */
    public function errors()
    {
        return $this->errors;
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    protected function addError($name, $rule, $defaultMessage, $params)
    {
        $this->errors[$name][] = $this->getCustomMessage($name, $rule, $defaultMessage, $params);
        $this->pass = false;
    }

    protected function parseRule($rule)
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];
        return [$name, $params];
    }

    public function setCustomNames(array $names)
    {
        $this->customNames = $names;
    }

    public function setCustomMessages(array $messages) 
    {
        $this->customMessages = $messages;
    }
    
    public function setDefaultBail($bail) 
    {
        $this->defaultBail = $bail;
    }

    protected function getDisplayName($field)
    {
        // Handle dot notation names for display if needed, or just return custom
        return $this->customNames[$field] ?? $field;
    }

    protected function getCustomMessage($field, $rule, $defaultMessage, $params = []) 
    {
        $key = "$field.$rule";
    
        if (isset($this->customMessages[$key])) {
            $message = $this->customMessages[$key];
            
            // Replace placeholders :0, :1, etc.
            foreach ($params as $index => $param) {
                $message = str_replace(":$index", $param, $message);
            }
    
            // Replace :attribute
            $name = $this->getDisplayName($field);
            $message = str_replace(':attribute', $name, $message);
    
            return $message;
        }
    
        return $defaultMessage;
    }

    protected function hasDeletedAtColumn($table) 
    {
        if (!$this->db) return false;
        try {
            // Using the PDO instance from Core\Database
            $stmt = $this->db->pdo->query("DESCRIBE `$table`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strtolower($row['Field']) === 'deleted_at') {
                    return true;
                }
            }
        } catch (Exception $e) {
            // Table might not exist or other DB error
            return false;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // VALIDATION RULES
    // -------------------------------------------------------------------------

    protected function validateRequired($value, $params, $name)
    {
        $display = $this->getDisplayName($name);

        // Flat-level file input check
        if (is_array($value) && isset($value['error'], $value['tmp_name'])) {
            return $value['error'] !== UPLOAD_ERR_NO_FILE
                ? true
                : "$display is required.";
        }

        // Arrays
        if (is_array($value)) {
            return count($value) > 0
                ? true
                : "$display is required.";
        }

        // Scalars
        return (!is_null($value) && trim((string)$value) !== '')
            ? true
            : "$display is required.";
    }

    protected function validateInteger($value, $params, $name) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false 
            ? true 
            : $this->getDisplayName($name) . " must be an integer.";
    }

    protected function validateNumeric($value, $params, $name) {
        return is_numeric($value) 
            ? true 
            : $this->getDisplayName($name) . " must be numeric.";
    }

    protected function validateString($value, $params, $name) {
        return is_string($value) 
            ? true 
            : $this->getDisplayName($name) . " must be a string.";
    }

    protected function validateBoolean($value, $params, $name) {
        return in_array($value, [true, false, 0, 1, '0', '1'], true) 
            ? true 
            : $this->getDisplayName($name) . " must be true or false.";
    }

    protected function validateEmail($value, $params, $name) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false 
            ? true 
            : $this->getDisplayName($name) . " must be a valid email address.";
    }

    protected function validateIn($value, $params, $name) {
        return in_array($value, $params) 
            ? true 
            : $this->getDisplayName($name) . " must be one of: " . implode(', ', $params);
    }

    protected function validateNot_in($value, $params, $name) {
        return !in_array($value, $params) 
            ? true 
            : $this->getDisplayName($name) . " must not be any of: " . implode(', ', $params);
    }

    protected function validateMin($value, $params, $name) {
        $min = $params[0];
        $display = $this->getDisplayName($name);
        if (is_numeric($value)) return $value >= $min ? true : "$display must be at least $min.";
        if (is_string($value)) return mb_strlen($value) >= $min ? true : "$display must be at least $min characters.";
        if (is_array($value)) return count($value) >= $min ? true : "$display must have at least $min items.";
        return true;
    }

    protected function validateMax($value, $params, $name) {
        $max = $params[0];
        $display = $this->getDisplayName($name);
        if (is_numeric($value)) return $value <= $max ? true : "$display must be at most $max.";
        if (is_string($value)) return mb_strlen($value) <= $max ? true : "$display must be at most $max characters.";
        if (is_array($value)) return count($value) <= $max ? true : "$display must have at most $max items.";
        return true;
    }

    protected function validateGreater_than($value, $params, $name) {
        $compare = isset($params[0]) ? (float)$params[0] : 0;
        if (!is_numeric($value)) return $this->getDisplayName($name) . " must be a number.";
        return $value > $compare 
            ? true 
            : $this->getDisplayName($name) . " must be greater than $compare.";
    }

    protected function validateLess_than($value, $params, $name) {
        $compare = isset($params[0]) ? (float)$params[0] : 0;
        if (!is_numeric($value)) return $this->getDisplayName($name) . " must be a number.";
        return $value < $compare 
            ? true 
            : $this->getDisplayName($name) . " must be less than $compare.";
    }

    protected function validateDate($value, $params, $name) {
        $display = $this->getDisplayName($name);
        if (!is_string($value)) return "$display must be a valid date.";

        // Format provided
        if (!empty($params)) {
            $format = $params[0];
            $dt = DateTime::createFromFormat($format, $value);
            $errors = DateTime::getLastErrors();
            if ($dt && $errors['warning_count'] === 0 && $errors['error_count'] === 0) {
                return true;
            }
            return "$display must be a valid date with format $format.";
        }

        // General parsing
        $timestamp = strtotime($value);
        return $timestamp !== false ? true : "$display must be a valid date.";
    }

    protected function validateTime($value, $params, $name) {
        $display = $this->getDisplayName($name);
        if (!is_string($value)) return "$display must be a valid time.";

        if (!empty($params)) {
            $format = $params[0];
            $dt = DateTime::createFromFormat($format, $value);
            $errors = DateTime::getLastErrors();
            if ($dt && $errors['warning_count'] === 0 && $errors['error_count'] === 0) {
                return true;
            }
            return "$display must be a valid time with format $format.";
        }

        $timestamp = strtotime("1970-01-01 $value");
        return $timestamp !== false ? true : "$display must be a valid time.";
    }

    protected function validateDate_time($value, $params, $name) {
        $format = $params[0] ?? 'Y-m-d H:i:s';
        $dt = DateTime::createFromFormat($format, $value);
        $valid = $dt && $dt->format($format) === $value;
        return $valid ? true : "{$this->getDisplayName($name)} must be a valid date and time in format {$format}.";
    }

    protected function validateRegex($value, $params, $name) {
        $pattern = $params[0];
        // Ensure pattern is valid regex
        return @preg_match($pattern, $value)
            ? true
            : $this->getDisplayName($name) . " has an invalid format.";
    }

    // --- DB Rules ---

    protected function validateExists($value, $params, $name) {
        if (!$this->db) return "Server Error: Database validation not available.";
        if (count($params) < 2) return "Server Error: Missing table/column definition.";
    
        $table = $params[0];
        $column = $params[1];
    
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
        
        // Auto-handle soft deletes
        if ($this->hasDeletedAtColumn($table)) {
            $sql .= " AND (`deleted_at` IS NULL OR `deleted_at` = 0)";
        }
    
        $stmt = $this->db->pdo->prepare($sql);
        $stmt->execute([$value]);
        
        return $stmt->fetchColumn() > 0 
            ? true 
            : "The selected " . $this->getDisplayName($name) . " is invalid.";
    }

    protected function validateUnique($value, $params, $name) {
        if (!$this->db) return "Server Error: Database validation not available.";
        if (count($params) < 2) return "Server Error: Missing table/column definition.";

        $table = $params[0];
        $column = $params[1];
        $exceptId = $params[2] ?? null; // Optional ID to ignore

        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
        $bindings = [$value];

        if ($exceptId) {
            $sql .= " AND `id` != ?";
            $bindings[] = $exceptId;
        }

        // Auto-handle soft deletes
        if ($this->hasDeletedAtColumn($table)) {
            $sql .= " AND (`deleted_at` IS NULL OR `deleted_at` = 0)";
        }

        $stmt = $this->db->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchColumn() == 0 
            ? true 
            : $this->getDisplayName($name) . " has already been taken.";
    }

    // --- Logical Rules ---

    protected function validateSame($value, $params, $name) {
        $otherField = $params[0];
        // Note: This relies on $_REQUEST or needs context injection if you want to support JSON body purely. 
        // For now, falling back to $_REQUEST which catches both $_POST and $_GET.
        $otherValue = $_REQUEST[$otherField] ?? null; 
        
        return $value === $otherValue
            ? true
            : $this->getDisplayName($name) . " must match " . $this->getDisplayName($otherField) . ".";
    }

    protected function validateDifferent($value, $params, $name) {
        $otherField = $params[0];
        $otherValue = $_REQUEST[$otherField] ?? null;
        return $value !== $otherValue
            ? true
            : $this->getDisplayName($name) . " must be different from " . $this->getDisplayName($otherField) . ".";
    }

    // --- File Rules ---

    protected function validateFile($value, $params, $name) {
        // Must be a normalized file array OR raw $_FILES entry
        if (!is_array($value) || !isset($value['name']) || !isset($value['tmp_name'])) {
            return $this->getDisplayName($name) . " must be a valid file.";
        }
        
        // Check if uploaded successfully
        if ($value['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($value['tmp_name'])) {
            return $this->getDisplayName($name) . " upload failed.";
        }

        // Extension check (file:jpg,png)
        if (!empty($params)) {
            $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $params)) {
                return $this->getDisplayName($name) . " type must be: " . implode(', ', $params);
            }
        }
        return true;
    }

    protected function validateMimes($value, $params, $name) {
        if (!isset($value['tmp_name']) || !is_uploaded_file($value['tmp_name'])) {
            return $this->getDisplayName($name) . " must be a valid file.";
        }
        $mime = mime_content_type($value['tmp_name']);
        return in_array($mime, $params) 
            ? true 
            : $this->getDisplayName($name) . " mime type invalid.";
    }

    protected function validateFile_size($value, $params, $name) {
        $maxKb = (int) $params[0];
        if (!isset($value['size'])) return true; // Skip if weird structure
        
        return $value['size'] <= ($maxKb * 1024) 
            ? true 
            : $this->getDisplayName($name) . " must be under $maxKb KB.";
    }
}