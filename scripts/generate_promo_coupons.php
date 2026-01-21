<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Fallback to direct .env file reading
if (empty($_ENV['DB_PASSWORD'])) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"');
        }
    }
}

// Database connection
$db = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$count = 5000;
$generated = 0;
$expiryDate = date('Y-m-d', strtotime('+1 year'));

echo "Generating {$count} promo coupons...\n";

$stmt = $db->prepare("
    INSERT INTO coupons (code, type, discount_percent, valid_until) 
    VALUES (?, 'promo', 10, ?)
");

while ($generated < $count) {
    $code = generateCouponCode();
    
    // Check if code already exists
    $checkStmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
    $checkStmt->execute([$code]);
    
    if (!$checkStmt->fetch()) {
        $stmt->execute([$code, $expiryDate]);
        $generated++;
        
        if ($generated % 100 == 0) {
            echo "Generated {$generated}/{$count}...\n";
        }
    }
}

echo "Done! Generated {$generated} promo coupons.\n";

function generateCouponCode(): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $code;
}