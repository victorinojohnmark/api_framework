<?php

class AdminSeeder
{
    public function run($pdo)
    {
        # Configuration
        $lastName = 'User';
        $firstName = 'Admin';
        $email = 'admin@admin.com';
        $password = 'password';

        // 1. Create 'admin' Role
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute(['admin']);
        $roleId = $stmt->fetchColumn();

        if (!$roleId) {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute(['admin', 'Super Administrator']);
            $roleId = $pdo->lastInsertId();
            echo " [Role Created] ";
        } else {
            echo " [Role Exists] ";
        }

        // 2. Create Admin User
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            $password = password_hash($password, PASSWORD_BCRYPT);
            
            // CHANGED: first_name and last_name
            $sql = "INSERT INTO users (first_name, last_name, email, password, active, created_at) 
                    VALUES (?, ?, ?, ?, 1, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$firstName, $lastName, $email, $password, time()]);
            
            $userId = $pdo->lastInsertId();
            echo " [User Created] ";
        } else {
            echo " [User Exists] ";
        }

        // 3. Assign Role
        $stmt = $pdo->prepare("SELECT * FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$userId, $roleId]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$userId, $roleId]);
            echo " [Role Assigned]";
        }
    }
}