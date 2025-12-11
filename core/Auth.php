<?php
namespace Core;

class Auth
{
    protected $db;
    protected $userId;
    
    // Cache properties to avoid repeated DB calls
    protected $user = null;
    protected $roles = null;
    protected $permissions = null;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->db = new Database(); // Using existing DB core
    }

    /**
     * Get the User ID
     */
    public function id()
    {
        return $this->userId;
    }

    /**
     * Get the User Object (Lazy Loaded)
     */
    public function user()
    {
        if (!$this->user) {
            $this->user = $this->db->table('users')->where('id', $this->userId)->first();
        }
        return $this->user;
    }

    /**
     * Get User Roles (Array of strings)
     * Returns: ['admin', 'editor']
     */
    public function roles()
    {
        if ($this->roles === null) {
            $sql = "SELECT r.name FROM roles r 
                    JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = ?";
            
            $results = $this->db->query($sql, [$this->userId]);
            
            // Flatten array of objects to array of strings
            $this->roles = array_map(function($row) { 
                return $row->name; 
            }, $results);
        }
        return $this->roles;
    }

    /**
     * Get All Permissions (Array of strings)
     * Merges permissions from ALL roles the user has.
     */
    public function permissions()
    {
        if ($this->permissions === null) {
            $sql = "SELECT DISTINCT p.name FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    JOIN user_roles ur ON rp.role_id = ur.role_id
                    WHERE ur.user_id = ?";
            
            $results = $this->db->query($sql, [$this->userId]);
            
            $this->permissions = array_map(function($row) { 
                return $row->name; 
            }, $results);
        }
        return $this->permissions;
    }

    /**
     * Check if user has a specific permission
     * Usage: auth()->can('edit_posts')
     */
    public function can($permission)
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Check if user has a specific role
     * Usage: auth()->hasRole('admin')
     */
    public function hasRole($role)
    {
        return in_array($role, $this->roles());
    }
}