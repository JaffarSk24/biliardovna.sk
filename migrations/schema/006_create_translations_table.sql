
-- Translations table for multilingual support
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'service, resource, etc.',
    entity_id INT NOT NULL,
    language VARCHAR(2) NOT NULL,
    field VARCHAR(50) NOT NULL COMMENT 'name, description, etc.',
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_translation (entity_type, entity_id, language, field),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
