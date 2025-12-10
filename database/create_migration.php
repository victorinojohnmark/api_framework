<?php
// database/create_migration.php

if ($argc < 2) {
    echo "Error: Please provide a migration name.\n";
    echo "Usage: php database/create_migration.php CreateUsersTable\n";
    exit(1);
}

$name = $argv[1];

// 1. Generate Filename
$timestamp = date('Y_m_d_His');
$filename = "{$timestamp}_{$name}.php";
$folder = __DIR__ . '/migrations/';

if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$filepath = $folder . $filename;

// 2. Template Content
$template = "<?php

class {$name}
{
    /**
     * Run the migrations.
     * @param PDO \$pdo
     */
    public function up(\$pdo)
    {
        \$sql = \"CREATE TABLE table_name (
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
        )\";

        \$pdo->exec(\$sql);
    }

    /**
     * Reverse the migrations.
     * @param PDO \$pdo
     */
    public function down(\$pdo)
    {
        \$pdo->exec(\"DROP TABLE IF EXISTS table_name\");
    }
}
";

// 3. Save File
if (file_put_contents($filepath, $template)) {
    echo "Created Migration: database/migrations/{$filename}\n";
} else {
    echo "Error: Could not create file.\n";
}