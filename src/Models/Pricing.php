<?php

namespace App\Models;

class Pricing extends Model
{
    protected string $table = 'pricing';
    
    public function getPriceForSlot(int $serviceId, int $dayOfWeek, string $time, bool $isHoliday = false): ?float
    {
        $sql = "
            SELECT price_per_hour
            FROM pricing
            WHERE service_id = ?
            AND day_of_week = ?
            AND start_time = ?
            AND is_holiday_pricing = ?
            LIMIT 1
        ";
        
        $result = $this->query($sql, [$serviceId, $dayOfWeek, $time, $isHoliday ? 1 : 0]);
        
        return $result[0]['price_per_hour'] ?? null;
    }
    
    public function getByService(int $serviceId): array
    {
        return $this->all(['service_id' => $serviceId]);
    }
}