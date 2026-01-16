-- Seed initial settings for Popup
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES 
('popup_active', '0', 'boolean'),
('popup_text_sk', '', 'string'),
('popup_text_en', '', 'string'),
('popup_text_ru', '', 'string'),
('popup_text_uk', '', 'string'),
('popup_text_de', '', 'string');

-- Blocked Slots Table
CREATE TABLE IF NOT EXISTS blocked_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    service_id INT DEFAULT NULL, -- NULL means all services
    resource_id INT DEFAULT NULL, -- NULL means all resources
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
);
