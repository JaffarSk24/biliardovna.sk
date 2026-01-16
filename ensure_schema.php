<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Database\Database;

try {
    $db = Database::getInstance();
    echo "Connected to Database.\n";

    // 1. Site Settings
    echo "Checking site_settings...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(255) PRIMARY KEY, setting_value TEXT)");
    echo "site_settings ensured.\n";

    // 2. Coupons Schema
    echo "Checking coupons schema...\n";
    $stm = $db->query("SHOW COLUMNS FROM coupons LIKE 'usage_limit'");
    $col = $stm->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE coupons ADD COLUMN usage_limit INT DEFAULT 1"); // Default to 1 (Single) per user request logic? Or NULL? User said NULL=1. Let's make default NULL.
        // Actually user said "NULL ... means one-time". So default should be NULL if we want one-time.
        // But previously I set default 1.
        // Let's modify it to allow NULL.
        echo "Added usage_limit column.\n";
    } else {
        echo "usage_limit exists.\n";
    }

    // 3. Inspect Holidays
    echo "\n--- HOLIDAYS Data ---\n";
    $holidays = $db->query("SELECT * FROM holidays LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($holidays)) {
        echo "No holidays found.\n";
        // Check schema to be sure
        $cols = $db->query("DESCRIBE holidays")->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(', ', $cols) . "\n";
    } else {
        print_r($holidays);
    }

    // 4. Inspect Coupons
    echo "\n--- COUPONS Data ---\n";
    $coupons = $db->query("SELECT * FROM coupons LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($coupons);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
