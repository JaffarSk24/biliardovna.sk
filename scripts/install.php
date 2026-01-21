<?php
/**
 * Installation Script for Biliardovna.sk Booking System
 * Run this once to set up the database
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/vendor/autoload.php';
}

use App\Database\Database;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "========================================\n";
echo "Biliardovna.sk Booking System Installer\n";
echo "========================================\n\n";

try {
    echo "Step 1: Testing database connection...\n";
    $db = Database::getInstance();
    echo "✓ Database connection successful\n\n";
    
    echo "Step 2: Running migrations...\n";
    $migrationResults = Database::migrate();
    foreach ($migrationResults as $result) {
        echo "  - $result\n";
    }
    echo "\n";
    
    echo "Step 3: Seeding initial data...\n";
    $seedResults = Database::seed();
    foreach ($seedResults as $result) {
        echo "  - $result\n";
    }
    echo "\n";
    
    echo "========================================\n";
    echo "✓ Installation completed successfully!\n";
    echo "========================================\n\n";
    echo "Default Admin Credentials:\n";
    echo "Username: admin\n";
    echo "Password: password\n";
    echo "\n⚠️  IMPORTANT: Change the default password immediately!\n\n";
    echo "Access the admin panel at: /admin/login\n";
    
} catch (Exception $e) {
    echo "\n❌ Installation failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
