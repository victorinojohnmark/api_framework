<?php

class CreateUserTable
{
    /**
     * Run the migrations.
     * @param PDO $pdo
     */
    public function up($pdo)
    {
        $sql = "CREATE TABLE table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            
            # ADD YOUR COLUMNS HERE
            
            # Default Utilities
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

    /**
     * Reverse the migrations.
     * @param PDO $pdo
     */
    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS table_name");
    }
}
