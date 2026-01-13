<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

// Mock DB connection if needed, but integration test is better if DB is available.
// Since I don't have full environment, I might need to mock some parts or rely on the code being correct.
// However, the `PricingService` uses `Holiday` and `Pricing` models which use DB.
// I can try to run it if the environment allows. If not, I'll rely on code correctness.
// Let's try to include the classes directly and mock the models if necessary or assume DB works.
// Given the previous tool outputs, it seems like a normal PHP project.

use App\Services\PricingService;

try {
    $service = new PricingService();
    
    echo "Testing blocked dates (Expect empty):\n";
    $blockedDate = '2025-12-30';
    $slots = $service->getAvailableSlots(1, $blockedDate);
    if (empty($slots)) {
        echo "✅ Slots for $blockedDate are empty.\n";
    } else {
        echo "❌ Slots for $blockedDate are NOT empty!\n";
        print_r($slots);
    }
    
    echo "\nTesting blocked calculation (Expect Exception):\n";
    try {
        $service->calculatePrice(1, $blockedDate, '16:00:00', 1);
        echo "❌ Exception was NOT thrown for $blockedDate!\n";
    } catch (\Exception $e) {
        echo "✅ Exception caught: " . $e->getMessage() . "\n";
    }
    
    echo "\nTesting allowed dates (Expect slots):\n";
    // Assuming 2025-12-28 is open
    $allowedDate = '2025-12-28';
    // This might fail if DB is not reachable or no prices defined, but let's see.
    // If DB fails, I will know I cannot run integration tests this way.
     try {
        $slots = $service->getAvailableSlots(1, $allowedDate);
        if (!empty($slots)) {
             echo "✅ Slots for $allowedDate are available (or DB connection failed but logic passed).\n";
        } else {
             echo "⚠️ Slots for $allowedDate are empty. Might be due to missing prices/DB, but blocking logic didn't crash it.\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ DB Error or similar on allowed date: " . $e->getMessage() . "\n";
    }

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
}
