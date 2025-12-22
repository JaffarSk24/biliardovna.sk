<?php

namespace App\Models;

class Booking extends Model
{
    protected string $table = 'bookings';
    
    public function getUpcoming(int $limit = 50): array
    {
        $sql = "
            SELECT 
                b.*,
                s.slug as service_slug,
                t.value as service_name,
                r.name as resource_name
            FROM bookings b
            JOIN services s ON s.id = b.service_id
            JOIN resources r ON r.id = b.resource_id
            LEFT JOIN translations t ON t.entity_type = 'service' 
                AND t.entity_id = s.id 
                AND t.language = b.language 
                AND t.field = 'name'
            WHERE b.booking_date >= CURDATE()
            ORDER BY b.booking_date ASC, b.start_time ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getPending(): array
    {
        $sql = "
            SELECT 
                b.*,
                s.slug as service_slug,
                t.value as service_name,
                r.name as resource_name
            FROM bookings b
            JOIN services s ON s.id = b.service_id
            JOIN resources r ON r.id = b.resource_id
            LEFT JOIN translations t ON t.entity_type = 'service' 
                AND t.entity_id = s.id 
                AND t.language = b.language 
                AND t.field = 'name'
            WHERE b.status = 'pending'
            ORDER BY b.created_at DESC
        ";
        
        return $this->query($sql);
    }
    
    public function getByDateRange(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                b.*,
                s.slug as service_slug,
                t.value as service_name,
                r.name as resource_name
            FROM bookings b
            JOIN services s ON s.id = b.service_id
            JOIN resources r ON r.id = b.resource_id
            LEFT JOIN translations t ON t.entity_type = 'service' 
                AND t.entity_id = s.id 
                AND t.language = b.language 
                AND t.field = 'name'
            WHERE b.booking_date BETWEEN ? AND ?
            ORDER BY b.booking_date ASC, b.start_time ASC
        ";
        
        return $this->query($sql, [$startDate, $endDate]);
    }
}
