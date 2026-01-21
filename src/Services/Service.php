<?php
namespace App\Services;

use App\Models\Model;

class Service extends Model
{
    protected string $table = 'services';

    public function getWithTranslations(string $language = 'sk'): array
    {
        $sql = "SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC";
        $services = $this->query($sql);
        
        // Загружаем переводы из файла
        $translationsFile = __DIR__ . '/../../translations/' . $language . '.php';
        $translations = file_exists($translationsFile) ? require $translationsFile : [];
        
        // Применяем переводы к каждой услуге
        foreach ($services as &$service) {
            $nameKey = 'service_' . $service['slug'] . '_name';
            $descKey = 'service_' . $service['slug'] . '_description';
            
            $service['name'] = $translations[$nameKey] ?? $service['slug'];
            $service['description'] = $translations[$descKey] ?? '';
        }
        
        return $services;
    }

    public function getBySlug(string $slug, string $language = 'sk'): ?array
    {
        $sql = "
            SELECT 
                s.*,
                t_name.value AS name,
                t_desc.value AS description
            FROM services s
            LEFT JOIN translations t_name 
                ON t_name.entity_type = 'service' 
                AND t_name.entity_id = s.id 
                AND t_name.language = ? 
                AND t_name.field = 'name'
            LEFT JOIN translations t_desc 
                ON t_desc.entity_type = 'service' 
                AND t_desc.entity_id = s.id 
                AND t_desc.language = ? 
                AND t_desc.field = 'description'
            WHERE s.slug = ?
            LIMIT 1
        ";

        $rows = $this->query($sql, [$language, $language, $slug]);
        return $rows[0] ?? null;
    }
}