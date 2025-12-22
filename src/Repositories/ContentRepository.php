<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;
use App\Database\Database;

class ContentRepository
{
    public static function slugExists(PDO $pdo, string $slug, ?int $excludeId = null): bool 
    {
        $sql = "SELECT 1 FROM content_items WHERE slug = :slug" . ($excludeId ? " AND id <> :id" : "");
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        if ($excludeId) $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public static function getPromoForEdit(PDO $pdo, ?int $id): array 
    {
        if (!$id) {
            return [
                'item' => [
                    'id' => null,
                    'type' => 'promo',
                    'slug' => '',
                    'title' => '',
                    'subtitle' => '',
                    'excerpt' => '',
                    'hero_image' => '',
                    'language' => 'sk',
                    'body_html' => '',
                    'status' => 'draft',
                    'featured' => 0,
                    'publish_at' => null,
                ],
                'promo' => [
                    'coupon_code' => '',
                    'service_id' => null,
                    'min_slots' => 1,
                    'allowed_weekdays' => '1,2,3,4,5',
                    'time_start' => null,
                    'time_end' => null,
                    'date_start' => null,
                    'date_end' => null,
                    'auto_apply' => 1,
                ],
            ];
        }

        $sqlItem = "SELECT * FROM content_items WHERE id = :id AND type = 'promo' LIMIT 1";
        $stmt = $pdo->prepare($sqlItem);
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException('Promo not found');
        }

        $sqlPromo = "SELECT * FROM content_promo_meta WHERE content_id = :id LIMIT 1";
        $stmt = $pdo->prepare($sqlPromo);
        $stmt->execute([':id' => $id]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'coupon_code' => '',
            'service_id' => null,
            'min_slots' => 1,
            'allowed_weekdays' => '1,2,3,4,5',
            'time_start' => null,
            'time_end' => null,
            'date_start' => null,
            'date_end' => null,
            'auto_apply' => 1,
        ];

        return ['item' => $item, 'promo' => $promo];
    }

    public static function savePromo(PDO $pdo, array $item, array $promo): int 
    {
        $pdo->beginTransaction();
        try {
            $id = $item['id'] ? (int)$item['id'] : null;

            if (self::slugExists($pdo, $item['slug'], $id)) {
                throw new RuntimeException('Slug already exists');
            }

            if ($id) {
                $sql = "UPDATE content_items
                        SET slug=:slug, title=:title, subtitle=:subtitle, excerpt=:excerpt,
                            hero_image=:hero_image, language=:language, body_html=:body_html,
                            status=:status, featured=:featured, publish_at=:publish_at
                        WHERE id=:id AND type='promo'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':slug' => $item['slug'],
                    ':title' => $item['title'],
                    ':subtitle' => $item['subtitle'],
                    ':excerpt' => $item['excerpt'],
                    ':hero_image' => $item['hero_image'],
                    ':language' => $item['language'],
                    ':body_html' => $item['body_html'],
                    ':status' => $item['status'],
                    ':featured' => (int)$item['featured'],
                    ':publish_at' => $item['publish_at'] ?: null,
                    ':id' => $id,
                ]);
            } else {
                $sql = "INSERT INTO content_items
                        (type, slug, title, subtitle, excerpt, hero_image, language, body_html, status, featured, publish_at)
                        VALUES ('promo', :slug, :title, :subtitle, :excerpt, :hero_image, :language, :body_html, :status, :featured, :publish_at)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':slug' => $item['slug'],
                    ':title' => $item['title'],
                    ':subtitle' => $item['subtitle'],
                    ':excerpt' => $item['excerpt'],
                    ':hero_image' => $item['hero_image'],
                    ':language' => $item['language'],
                    ':body_html' => $item['body_html'],
                    ':status' => $item['status'],
                    ':featured' => (int)$item['featured'],
                    ':publish_at' => $item['publish_at'] ?: null,
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            // Normalize weekdays
            $weekdays = array_filter(array_map('intval', explode(',', (string)$promo['allowed_weekdays'])));
            $weekdays = array_values(array_unique(array_values(array_intersect($weekdays, [1,2,3,4,5,6,7]))));
            $allowed_weekdays = implode(',', $weekdays);

            // Upsert promo meta
            $sqlMeta = "INSERT INTO content_promo_meta
                        (content_id, coupon_code, service_id, min_slots, allowed_weekdays,
                         time_start, time_end, date_start, date_end, auto_apply)
                        VALUES
                        (:content_id, :coupon_code, :service_id, :min_slots, :allowed_weekdays,
                         :time_start, :time_end, :date_start, :date_end, :auto_apply)
                        ON DUPLICATE KEY UPDATE
                          coupon_code=VALUES(coupon_code),
                          service_id=VALUES(service_id),
                          min_slots=VALUES(min_slots),
                          allowed_weekdays=VALUES(allowed_weekdays),
                          time_start=VALUES(time_start),
                          time_end=VALUES(time_end),
                          date_start=VALUES(date_start),
                          date_end=VALUES(date_end),
                          auto_apply=VALUES(auto_apply)";
            $stmt = $pdo->prepare($sqlMeta);
            $stmt->execute([
                ':content_id' => $id,
                ':coupon_code' => $promo['coupon_code'],
                ':service_id' => $promo['service_id'] ?: null,
                ':min_slots' => max(1, (int)$promo['min_slots']),
                ':allowed_weekdays' => $allowed_weekdays ?: null,
                ':time_start' => $promo['time_start'] ?: null,
                ':time_end' => $promo['time_end'] ?: null,
                ':date_start' => $promo['date_start'] ?: null,
                ':date_end' => $promo['date_end'] ?: null,
                ':auto_apply' => (int)$promo['auto_apply'],
            ]);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getAllPromos(PDO $pdo): array 
    {
        $stmt = $pdo->query("
            SELECT c.id, c.slug, c.title, c.language, c.status, 
                   p.coupon_code, p.auto_apply
            FROM content c
            JOIN content_promo p ON c.id = p.content_id
            ORDER BY c.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActivePromoForService(?int $serviceId, string $language = 'sk'): ?array 
    {
        $pdo = Database::getInstance();
        $sql = "
            SELECT c.slug, p.coupon_code, p.service_id, p.min_slots, 
                   p.allowed_weekdays, p.time_start, p.time_end, 
                   p.date_start, p.date_end
            FROM content c
            JOIN content_promo p ON c.id = p.content_id
            WHERE c.status = 'published' 
              AND c.language = :lang
              AND p.auto_apply = 1
              AND (p.service_id IS NULL OR p.service_id = :service_id)
              AND (p.date_start IS NULL OR p.date_start <= CURDATE())
              AND (p.date_end IS NULL OR p.date_end >= CURDATE())
            ORDER BY p.service_id DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['lang' => $language, 'service_id' => $serviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}