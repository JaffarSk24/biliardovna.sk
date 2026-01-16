<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Database\Database;

try {
    $db = Database::getInstance();
    echo "Cleaning up duplicate holidays...\n";

    // Delete duplicates, keeping the one with min ID
    $sql = "DELETE t1 FROM holidays t1
            INNER JOIN holidays t2 
            WHERE t1.id > t2.id AND t1.holiday_date = t2.holiday_date";
    $db->exec($sql);
    echo "Duplicates removed.\n";

    // Add Unique Index
    echo "Adding UNIQUE index to holiday_date...\n";
    // Check if index exists first to avoid error? Or just try/catch
    try {
        $db->exec("ALTER TABLE holidays ADD UNIQUE INDEX unique_date (holiday_date)");
        echo "UNIQUE index added.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Index already exists.\n";
        } else {
            throw $e;
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
