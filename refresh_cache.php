<?php
require __DIR__ . '/vendor/autoload.php';

// Simulate simple bootstrap if needed, or just use class directly if autoloader works
// Assuming composer autoloader is enough for App namespace

use App\Services\BlockingService;

try {
    $service = new BlockingService();
    $service->updateCache();
    echo "Cache updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
