<?php

namespace App\Services;

use App\Database\Database;

class TranslationService
{
    private $db;
    private array $translations = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get translation for an entity
     */
    public function getTranslation(string $entityType, int $entityId, string $field, string $language): ?string
    {
        $cacheKey = "{$entityType}:{$entityId}:{$field}:{$language}";
        
        if (isset($this->translations[$cacheKey])) {
            return $this->translations[$cacheKey];
        }
        
        $stmt = $this->db->prepare("
            SELECT value 
            FROM translations 
            WHERE entity_type = ? 
            AND entity_id = ? 
            AND field = ? 
            AND language = ?
        ");
        
        $stmt->execute([$entityType, $entityId, $field, $language]);
        $result = $stmt->fetch();
        
        $value = $result['value'] ?? null;
        $this->translations[$cacheKey] = $value;
        
        return $value;
    }
    
    /**
     * Set translation for an entity
     */
    public function setTranslation(string $entityType, int $entityId, string $field, string $language, string $value): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO translations (entity_type, entity_id, field, language, value)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        
        return $stmt->execute([$entityType, $entityId, $field, $language, $value]);
    }
    
    /**
     * Get all translations for an entity
     */
    public function getAllTranslations(string $entityType, int $entityId, string $language): array
    {
        $stmt = $this->db->prepare("
            SELECT field, value 
            FROM translations 
            WHERE entity_type = ? 
            AND entity_id = ? 
            AND language = ?
        ");
        
        $stmt->execute([$entityType, $entityId, $language]);
        $results = $stmt->fetchAll();
        
        $translations = [];
        foreach ($results as $row) {
            $translations[$row['field']] = $row['value'];
        }
        
        return $translations;
    }
    
    /**
     * Get UI translations (for static text)
     */
    public function getUITranslations(string $language): array
    {
        $filePath = __DIR__ . '/../../translations/' . $language . '.php';
        
        if (file_exists($filePath)) {
            return require $filePath;
        }
        
        // Fallback to Slovak
        return require __DIR__ . '/../../translations/sk.php';
    }
}
