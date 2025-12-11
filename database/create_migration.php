<?php

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

// 2. Template Content (Updated for Schema Builder)
$template = "<?php
use Core\Database\Schema;
use Core\Database\Blueprint;

class {$name}
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('table_name', function (Blueprint \$table) {
            \$table->id();
            
            // \$table->string('name');
            
            # Default Utilities
            \$table->boolean('active')->default(1);
            \$table->timestamps(); // created_at, created_by, updated_at, updated_by
            \$table->softDelete(); // deleted_at, deleted_by
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('table_name');
    }
}
";

// 3. Save File
if (file_put_contents($filepath, $template)) {
    echo "Created Migration: database/migrations/{$filename}\n";
} else {
    echo "Error: Could not create file.\n";
}