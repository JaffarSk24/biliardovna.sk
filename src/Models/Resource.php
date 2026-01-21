<?php

namespace App\Models;

class Resource extends Model
{
    protected string $table = 'resources';
    
    public function getByService(int $serviceId): array
    {
        return $this->all(['service_id' => $serviceId, 'is_active' => 1]);
    }
    
    public function getAvailableForSlot(int $serviceId, string $date, string $startTime, string $endTime): array
    {
        $sql = "
            SELECT r.*
            FROM resources r
            WHERE r.service_id = ?
            AND r.is_active = 1
            AND r.id NOT IN (
                SELECT resource_id 
                FROM bookings 
                WHERE booking_date = ?
                AND status IN ('pending', 'confirmed')
                AND NOT (end_time <= ? OR start_time >= ?)
            )
        ";
        
        return $this->query($sql, [
            $serviceId,
            $date,
            $startTime,
            $endTime
        ]);
    }
}