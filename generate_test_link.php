<?php

use App\Models\Booking;
use App\Database\Database;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "Generating Test Review Link...\n";

// 1. Create a dummy completed booking
$db = Database::getInstance();
$token = bin2hex(random_bytes(16));

// Insert dummy data
$stmt = $db->prepare("INSERT INTO bookings (booking_date, start_time, end_time, service_id, resource_id, language, customer_name, customer_phone, customer_email, status, review_token, price) VALUES (CURDATE(), '12:00', '13:00', 1, 1, 'sk', 'Manual Tester', '0000000000', 'test@manual.com', 'completed', ?, 15.00)");
$stmt->execute([$token]);
$bookingId = $db->lastInsertId();

// 2. Generate Link
$baseUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
$link = "{$baseUrl}/review?booking={$bookingId}&token={$token}";

echo "\n---------------------------------------------------\n";
echo "✅ Test Booking Created (ID: {$bookingId})\n";
echo "🔗 CLICK THIS LINK TO TEST REDIRECT:\n";
echo $link . "\n";
echo "---------------------------------------------------\n";
echo "(This booking will remain in DB so you can click the link. You can delete it later from Admin Panel.)\n";
