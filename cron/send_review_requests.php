<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\NotificationService;

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

// Find completed bookings from yesterday that haven't received review request
$sql = "
    SELECT * FROM bookings 
    WHERE status = 'completed' 
    AND DATE(booking_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    AND (review_request_sent IS NULL OR review_request_sent = 0)
    AND customer_email IS NOT NULL
    AND customer_email != ''
";

$stmt = $db->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notificationService = new NotificationService();
$count = 0;

foreach ($bookings as $booking) {
    // Send review request email
    $sent = $notificationService->sendReviewRequestEmail($booking);
    
    if ($sent) {
        // Mark as sent
        $updateStmt = $db->prepare("UPDATE bookings SET review_request_sent = 1 WHERE id = ?");
        $updateStmt->execute([$booking['id']]);
        $count++;
    }
    
    // 10 секунд между отправками
    usleep(10000000);
}

echo date('Y-m-d H:i:s') . " - Sent {$count} review request(s)\n";