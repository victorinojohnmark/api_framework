<?php

class CreateRbacTables
{
    public function up($pdo)
    {
        # Roles Table (e.g., 'admin', 'editor')
        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        )");

        # Permissions Table (e.g., 'edit-profile', 'delete-users')
        $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL
        )");

        # User -> Roles (Many-to-Many)
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (user_id, role_id)
        )");

        # Role -> Permissions (Many-to-Many)
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id)
        )");
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS role_permissions");
        $pdo->exec("DROP TABLE IF EXISTS user_roles");
        $pdo->exec("DROP TABLE IF EXISTS permissions");
        $pdo->exec("DROP TABLE IF EXISTS roles");
    }
}
