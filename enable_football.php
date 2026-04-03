<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = App\Database\Database::getInstance();

try {
    $db->query("UPDATE services SET is_active = 1 WHERE id = 4");
    $db->query("UPDATE resources SET is_active = 1 WHERE service_id = 4");
    
    // Copy prices from service 3 (darts) to service 4
    $db->query("DELETE FROM pricing WHERE service_id = 4");
    $db->query("INSERT INTO pricing (service_id, day_of_week, start_time, price_per_hour, is_holiday_pricing, created_at, updated_at)
        SELECT 4, day_of_week, start_time, price_per_hour, is_holiday_pricing, NOW(), NOW()
        FROM pricing
        WHERE service_id = 3");
    
    echo "Table Football successfully enabled and priced on DB.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
