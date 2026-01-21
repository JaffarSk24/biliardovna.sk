<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Database\Database;

try {
    $db = Database::getInstance();
    $db->exec("ALTER TABLE coupons CHANGE usage_limit usage_limit INT DEFAULT NULL");
    echo "Coupons table altered.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
