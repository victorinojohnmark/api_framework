<?php

class AdminSeeder
{
    /**
     * @param \Core\Database $db
     */
    public function run($db)
    {
        // Configuration
        $firstName = 'Admin';
        $lastName  = 'User';
        $email     = 'admin@admin.com';
        $password  = 'password';

        // ----------------------------------------
        // 1. Create 'admin' Role
        // ----------------------------------------
        $role = $db->table('roles')->where('name', 'admin')->first();

        if (!$role) {
            $roleId = $db->table('roles')->insert([
                'name' => 'admin', 
                'description' => 'Super Administrator'
            ]);
            echo " [Role Created]";
        } else {
            $roleId = $role->id;
            echo " [Role Exists]";
        }

        // ----------------------------------------
        // 2. Create Admin User
        // ----------------------------------------
        $user = $db->table('users')->where('email', $email)->first();

        if (!$user) {
            $userId = $db->table('users')->insert([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => password_hash($password, PASSWORD_BCRYPT),
                'active'     => 1,
                'created_at' => time()
            ]);
            echo " [User Created]";
        } else {
            $userId = $user->id;
            echo " [User Exists]";
        }

        // ----------------------------------------
        // 3. Assign Role to User
        // ----------------------------------------
        $hasRole = $db->table('user_roles')
                      ->where('user_id', $userId)
                      ->where('role_id', $roleId)
                      ->first();
        
        if (!$hasRole) {
            $db->table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
            echo " [Role Assigned]";
        }
    }
}