<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Fallback to direct .env file reading if needed
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

$yesterday = date('Y-m-d', strtotime('-1 day'));

// Update completed bookings for yesterday
$sql = "
    UPDATE bookings 
    SET status = 'completed', 
        updated_at = NOW()
    WHERE status = 'confirmed' 
    AND booking_date = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$yesterday]);
$count = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " - Updated {$count} booking(s) to completed status for date: {$yesterday}\n";