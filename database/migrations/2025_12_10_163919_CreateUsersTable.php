<?php

class CreateUsersTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            active TINYINT(1) DEFAULT 1,
            created_by INT NULL,
            created_at INT NULL,
            updated_by INT NULL,
            updated_at INT NULL,
            deleted_by INT NULL,
            deleted_at INT NULL
        )";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS users");
    }
}
