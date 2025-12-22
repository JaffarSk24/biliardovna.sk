<?php

namespace App\Models;

class Holiday extends Model
{
    protected string $table = 'holidays';
    protected string $primaryKey = 'id';
    
    public function isHoliday(string $date): bool
    {
        $result = $this->findBy('holiday_date', $date);
        return $result !== null;
    }
    
    public function getUpcoming(int $limit = 10): array
    {
        $sql = "
            SELECT *
            FROM holidays
            WHERE holiday_date >= CURDATE()
            ORDER BY holiday_date ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
