<?php

namespace App\Models;

use PDO;

class Coupon
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = \App\Database\Database::getInstance();
    }
    
    /**
     * Create new coupon
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO coupons (code, discount_percent, valid_until, booking_id, customer_email) 
                VALUES (:code, :discount_percent, :valid_until, :booking_id, :customer_email)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'code' => $data['code'],
            'discount_percent' => $data['discount_percent'],
            'valid_until' => $data['valid_until'],
            'booking_id' => $data['booking_id'] ?? null,
            'customer_email' => $data['customer_email'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT * FROM coupons WHERE code = :code LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['code' => $code]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findLatestByEmail(string $email): ?array
    {
        $sql = "SELECT * 
                FROM coupons 
                WHERE customer_email IS NOT NULL 
                  AND LOWER(customer_email) = LOWER(:email)
                ORDER BY id DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    /**
     * Validate and use coupon
     */
    public function validateAndUse(string $code): ?array
    {
        $coupon = $this->findByCode($code);
        
        if (!$coupon) {
            return null;
        }
        
        // Check if already used
        if ($coupon['used']) {
            return null;
        }
        
        // Check if expired
        if (strtotime($coupon['valid_until']) < time()) {
            return null;
        }
        
        // Mark as used
        $sql = "UPDATE coupons SET used = 1, used_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $coupon['id']]);
        
        return $coupon;
    }
}