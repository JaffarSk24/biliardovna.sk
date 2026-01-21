<?php

namespace App\Services;

use App\Database\Database;

class BlockingService
{
    private string $cacheFile;

    public function __construct()
    {
        $this->cacheFile = dirname(dirname(__DIR__)) . '/config/blocked_slots.json';
    }

    /**
     * Check if a slot is blocked by admin rules (from cache)
     */
    public function isSlotBlocked(string $startTime, string $endTime, ?int $serviceId = null, ?int $resourceId = null): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $blocks = json_decode(file_get_contents($this->cacheFile), true);
        if (!is_array($blocks)) {
            return false;
        }

        $slotStartTs = strtotime($startTime);
        $slotEndTs = strtotime($endTime);

        foreach ($blocks as $block) {
            // Check Service ID match (null in block means ALL services)
            if ($block['service_id'] !== null && $serviceId !== null && $block['service_id'] != $serviceId) {
                continue;
            }

            // Check Resource ID match
            // If block is specific to a resource
            if ($block['resource_id'] !== null) {
                // If we are checking general availability (resourceId is null), we ignore specific resource blocks
                // because other resources might be free.
                if ($resourceId === null) {
                    continue;
                }
                // If we are checking specific resource, it must match
                if ($block['resource_id'] != $resourceId) {
                    continue;
                }
            }

            // Check overlap
            $blockStartTs = strtotime($block['start_time']);
            $blockEndTs = strtotime($block['end_time']);

            if ($blockStartTs < $slotEndTs && $blockEndTs > $slotStartTs) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the JSON cache from the database
     * Should be called whenever blocks are changed in Admin
     */
    public function updateCache(): void
    {
        $db = Database::getInstance();

        // Fetch valid blocks (future or current)
        // We can fetch ALL blocks or just active ones. 
        // Let's fetch all relevant ones (end_time >= NOW is optimized, but fetching all is safer for history consistency if needed)
        // Actually, for performance, we only care about FUTURE functionality.
        // But to be safe, let's just dump the table (it won't be huge).

        $sql = "SELECT start_time, end_time, service_id, resource_id FROM blocked_slots ORDER BY start_time";
        $stmt = $db->query($sql);
        $blocks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Ensure config directory exists
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0755, true);
        }

        file_put_contents($this->cacheFile, json_encode($blocks));
    }
}
