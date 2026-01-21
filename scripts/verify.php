#!/usr/bin/env php
<?php
/**
 * Verification Script for Biliardovna.sk Booking System
 * Run this to check if everything is properly installed
 */

echo "========================================\n";
echo "Biliardovna.sk System Verification\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

// Check PHP version
$total++;
echo "1. Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    echo "✓ OK (PHP " . PHP_VERSION . ")\n";
    $passed++;
} else {
    echo "✗ FAIL (Need PHP 8.2+, have " . PHP_VERSION . ")\n";
    $errors[] = "PHP version too old";
}

// Check required extensions
$total++;
echo "2. Checking PHP extensions... ";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
$missing = [];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (empty($missing)) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "✗ FAIL (Missing: " . implode(', ', $missing) . ")\n";
    $errors[] = "Missing PHP extensions: " . implode(', ', $missing);
}

// Check .env file
$total++;
echo "3. Checking .env configuration... ";
if (file_exists(__DIR__ . '/.env')) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "⚠ WARNING (Copy .env.example to .env)\n";
    $warnings[] = ".env file not found";
}

// Check vendor directory
$total++;
echo "4. Checking Composer dependencies... ";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "✗ FAIL (Run: composer install)\n";
    $errors[] = "Composer dependencies not installed";
}

// Check directory permissions
$total++;
echo "5. Checking directory permissions... ";
$writable = ['logs', 'storage/cache', 'storage/uploads'];
$notWritable = [];
foreach ($writable as $dir) {
    if (!is_writable(__DIR__ . '/' . $dir)) {
        $notWritable[] = $dir;
    }
}
if (empty($notWritable)) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "✗ FAIL (Not writable: " . implode(', ', $notWritable) . ")\n";
    $errors[] = "Directories not writable: " . implode(', ', $notWritable);
}

// Check .htaccess files
$total++;
echo "6. Checking .htaccess files... ";
if (file_exists(__DIR__ . '/public/.htaccess')) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "⚠ WARNING (.htaccess missing in public/)\n";
    $warnings[] = ".htaccess file missing";
}

// Check database configuration (if .env exists)
if (file_exists(__DIR__ . '/.env')) {
    $total++;
    echo "7. Testing database connection... ";
    
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        
        $config = require __DIR__ . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        
        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options']
        );
        
        // Check if tables exist
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) >= 8) {
            echo "✓ OK (" . count($tables) . " tables found)\n";
            $passed++;
        } else {
            echo "⚠ WARNING (Run install.php to create tables)\n";
            $warnings[] = "Database tables not created";
        }
        
    } catch (Exception $e) {
        echo "✗ FAIL (" . $e->getMessage() . ")\n";
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
}

// Check logo file
$total++;
echo "8. Checking logo file... ";
if (file_exists(__DIR__ . '/assets/images/logo.jpg')) {
    echo "✓ OK\n";
    $passed++;
} else {
    echo "⚠ WARNING (Logo not found)\n";
    $warnings[] = "Logo file missing";
}

// Summary
echo "\n========================================\n";
echo "Verification Summary\n";
echo "========================================\n";
echo "Passed: $passed/$total tests\n";

if (!empty($warnings)) {
    echo "\nWarnings (" . count($warnings) . "):\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". $warning\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors (" . count($errors) . "):\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n❌ System NOT ready for production\n";
    exit(1);
} else if (!empty($warnings)) {
    echo "\n⚠️  System functional but has warnings\n";
    exit(0);
} else {
    echo "\n✅ System ready for production!\n";
    exit(0);
}
