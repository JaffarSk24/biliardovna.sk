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
    public function all(array $conditions = []): array
    {
        $sql = "SELECT * FROM holidays ORDER BY holiday_date ASC";
        return $this->query($sql);
    }

    public function add(string $date, ?string $name = null): void
    {
        $sql = "INSERT IGNORE INTO holidays (holiday_date, name) VALUES (?, ?)";
        $this->db->prepare($sql)->execute([$date, $name]);
    }
}
