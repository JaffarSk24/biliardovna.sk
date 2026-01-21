-- Seed initial data for Biliardovna.sk

-- Insert services
INSERT INTO services (slug, is_active, sort_order) VALUES
('piramida', TRUE, 1),
('pool', TRUE, 2),
('darts', TRUE, 3),
('table-football', TRUE, 4);

-- Insert translations for services (Slovak)
INSERT INTO translations (entity_type, entity_id, language, field, value) VALUES
('service', 1, 'sk', 'name', 'Pyramída'),
('service', 1, 'sk', 'description', 'Ruská pyramída - klasický billiard'),
('service', 2, 'sk', 'name', 'Pool'),
('service', 2, 'sk', 'description', 'Americký pool'),
('service', 3, 'sk', 'name', 'Šípky'),
('service', 3, 'sk', 'description', 'Elektronické šípky'),
('service', 4, 'sk', 'name', 'Stolný futbal'),
('service', 4, 'sk', 'description', 'Stolný futbal - foosball');

-- Insert translations for services (English)
INSERT INTO translations (entity_type, entity_id, language, field, value) VALUES
('service', 1, 'en', 'name', 'Pyramid'),
('service', 1, 'en', 'description', 'Russian pyramid billiards'),
('service', 2, 'en', 'name', 'Pool'),
('service', 2, 'en', 'description', 'American pool'),
('service', 3, 'en', 'name', 'Darts'),
('service', 3, 'en', 'description', 'Electronic darts'),
('service', 4, 'en', 'name', 'Table Football'),
('service', 4, 'en', 'description', 'Table football - foosball');

-- Insert translations for services (Russian)
INSERT INTO translations (entity_type, entity_id, language, field, value) VALUES
('service', 1, 'ru', 'name', 'Пирамида'),
('service', 1, 'ru', 'description', 'Русская пирамида'),
('service', 2, 'ru', 'name', 'Пул'),
('service', 2, 'ru', 'description', 'Американский пул'),
('service', 3, 'ru', 'name', 'Дартс'),
('service', 3, 'ru', 'description', 'Электронный дартс'),
('service', 4, 'ru', 'name', 'Настольный футбол'),
('service', 4, 'ru', 'description', 'Настольный футбол');

-- Insert translations for services (Hungarian)
INSERT INTO translations (entity_type, entity_id, language, field, value) VALUES
('service', 1, 'hu', 'name', 'Piramis'),
('service', 1, 'hu', 'description', 'Orosz piramis biliárd'),
('service', 2, 'hu', 'name', 'Pool'),
('service', 2, 'hu', 'description', 'Amerikai pool'),
('service', 3, 'hu', 'name', 'Darts'),
('service', 3, 'hu', 'description', 'Elektronikus darts'),
('service', 4, 'hu', 'name', 'Asztali foci'),
('service', 4, 'hu', 'description', 'Asztali foci - csocsó');

-- Insert resources (example tables)
INSERT INTO resources (service_id, name, is_active) VALUES
(1, 'Pyramída - Stôl 1', TRUE),
(1, 'Pyramída - Stôl 2', TRUE),
(2, 'Pool - Stôl 1', TRUE),
(2, 'Pool - Stôl 2', TRUE),
(2, 'Pool - Stôl 3', TRUE),
(3, 'Šípky - Zariadenie 1', TRUE),
(3, 'Šípky - Zariadenie 2', TRUE),
(4, 'Stolný futbal - Stôl 1', TRUE);

-- Insert pricing for Pyramída (service_id = 1)
-- Monday (day 1)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 1, '16:00:00', '16:59:59', 20.00, FALSE),
(1, 1, '17:00:00', '17:59:59', 22.40, FALSE),
(1, 1, '18:00:00', '20:59:59', 24.00, FALSE),
(1, 1, '21:00:00', '21:59:59', 22.40, FALSE),
(1, 1, '22:00:00', '23:59:59', 20.00, FALSE);

-- Tuesday (day 2)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 2, '16:00:00', '16:59:59', 22.50, FALSE),
(1, 2, '17:00:00', '17:59:59', 25.20, FALSE),
(1, 2, '18:00:00', '20:59:59', 27.00, FALSE),
(1, 2, '21:00:00', '21:59:59', 25.20, FALSE),
(1, 2, '22:00:00', '23:59:59', 22.50, FALSE);

-- Wednesday (day 3)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 3, '16:00:00', '16:59:59', 25.00, FALSE),
(1, 3, '17:00:00', '17:59:59', 28.00, FALSE),
(1, 3, '18:00:00', '20:59:59', 30.00, FALSE),
(1, 3, '21:00:00', '21:59:59', 28.00, FALSE),
(1, 3, '22:00:00', '23:59:59', 25.00, FALSE);

-- Thursday (day 4)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 4, '16:00:00', '16:59:59', 27.50, FALSE),
(1, 4, '17:00:00', '17:59:59', 30.80, FALSE),
(1, 4, '18:00:00', '20:59:59', 33.00, FALSE),
(1, 4, '21:00:00', '21:59:59', 30.80, FALSE),
(1, 4, '22:00:00', '23:59:59', 27.50, FALSE);

-- Friday (day 5)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 5, '16:00:00', '16:59:59', 30.00, FALSE),
(1, 5, '17:00:00', '17:59:59', 33.60, FALSE),
(1, 5, '18:00:00', '20:59:59', 36.00, FALSE),
(1, 5, '21:00:00', '21:59:59', 33.60, FALSE),
(1, 5, '22:00:00', '23:59:59', 30.00, FALSE);

-- Saturday (day 6)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 6, '16:00:00', '16:59:59', 35.00, FALSE),
(1, 6, '17:00:00', '17:59:59', 39.20, FALSE),
(1, 6, '18:00:00', '20:59:59', 42.00, FALSE),
(1, 6, '21:00:00', '21:59:59', 39.20, FALSE),
(1, 6, '22:00:00', '23:59:59', 35.00, FALSE);

-- Sunday (day 7)
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 7, '16:00:00', '16:59:59', 32.50, FALSE),
(1, 7, '17:00:00', '17:59:59', 36.40, FALSE),
(1, 7, '18:00:00', '20:59:59', 39.00, FALSE),
(1, 7, '21:00:00', '21:59:59', 36.40, FALSE),
(1, 7, '22:00:00', '23:59:59', 32.50, FALSE);

-- Holiday pricing for Pyramída
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(1, 1, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 2, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 3, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 4, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 5, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 6, '16:00:00', '23:59:59', 35.00, TRUE),
(1, 7, '16:00:00', '23:59:59', 35.00, TRUE);

-- Insert pricing for Pool (service_id = 2) - Similar structure with Pool prices
-- Monday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 1, '16:00:00', '16:59:59', 22.40, FALSE),
(2, 1, '17:00:00', '17:59:59', 24.80, FALSE),
(2, 1, '18:00:00', '20:59:59', 26.40, FALSE),
(2, 1, '21:00:00', '21:59:59', 24.80, FALSE),
(2, 1, '22:00:00', '23:59:59', 22.40, FALSE);

-- Tuesday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 2, '16:00:00', '16:59:59', 25.20, FALSE),
(2, 2, '17:00:00', '17:59:59', 27.90, FALSE),
(2, 2, '18:00:00', '20:59:59', 29.70, FALSE),
(2, 2, '21:00:00', '21:59:59', 27.90, FALSE),
(2, 2, '22:00:00', '23:59:59', 25.20, FALSE);

-- Wednesday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 3, '16:00:00', '16:59:59', 28.00, FALSE),
(2, 3, '17:00:00', '17:59:59', 31.00, FALSE),
(2, 3, '18:00:00', '20:59:59', 33.00, FALSE),
(2, 3, '21:00:00', '21:59:59', 31.00, FALSE),
(2, 3, '22:00:00', '23:59:59', 28.00, FALSE);

-- Thursday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 4, '16:00:00', '16:59:59', 30.80, FALSE),
(2, 4, '17:00:00', '17:59:59', 34.10, FALSE),
(2, 4, '18:00:00', '20:59:59', 36.30, FALSE),
(2, 4, '21:00:00', '21:59:59', 34.10, FALSE),
(2, 4, '22:00:00', '23:59:59', 30.80, FALSE);

-- Friday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 5, '16:00:00', '16:59:59', 33.60, FALSE),
(2, 5, '17:00:00', '17:59:59', 37.20, FALSE),
(2, 5, '18:00:00', '20:59:59', 39.60, FALSE),
(2, 5, '21:00:00', '21:59:59', 37.20, FALSE),
(2, 5, '22:00:00', '23:59:59', 33.60, FALSE);

-- Saturday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 6, '16:00:00', '16:59:59', 39.20, FALSE),
(2, 6, '17:00:00', '17:59:59', 43.40, FALSE),
(2, 6, '18:00:00', '20:59:59', 46.20, FALSE),
(2, 6, '21:00:00', '21:59:59', 43.40, FALSE),
(2, 6, '22:00:00', '23:59:59', 39.20, FALSE);

-- Sunday
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 7, '16:00:00', '16:59:59', 36.40, FALSE),
(2, 7, '17:00:00', '17:59:59', 40.30, FALSE),
(2, 7, '18:00:00', '20:59:59', 42.90, FALSE),
(2, 7, '21:00:00', '21:59:59', 40.30, FALSE),
(2, 7, '22:00:00', '23:59:59', 36.40, FALSE);

-- Holiday pricing for Pool
INSERT INTO pricing (service_id, day_of_week, start_time, end_time, price_per_hour, is_holiday_pricing) VALUES
(2, 1, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 2, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 3, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 4, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 5, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 6, '16:00:00', '23:59:59', 39.20, TRUE),
(2, 7, '16:00:00', '23:59:59', 39.20, TRUE);

-- Insert some common holidays for Slovakia
INSERT INTO holidays (holiday_date, name) VALUES
('2025-01-01', 'Nový rok'),
('2025-01-06', 'Zjavenie Pána'),
('2025-04-18', 'Veľký piatok'),
('2025-04-21', 'Veľkonočný pondelok'),
('2025-05-01', 'Sviatok práce'),
('2025-05-08', 'Deň víťazstva nad fašizmom'),
('2025-07-05', 'Sviatok sv. Cyrila a Metoda'),
('2025-08-29', 'Výročie SNP'),
('2025-09-01', 'Deň Ústavy SR'),
('2025-09-15', 'Sedembolestná Panna Mária'),
('2025-11-01', 'Sviatok všetkých svätých'),
('2025-11-17', 'Deň boja za slobodu a demokraciu'),
('2025-12-24', 'Štedrý deň'),
('2025-12-25', 'Prvý sviatok vianočný'),
('2025-12-26', 'Druhý sviatok vianočný');

-- Insert default admin user (username: admin, password: password - CHANGE THIS!)
INSERT INTO admin_users (username, password_hash, email, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@biliardovna.sk', TRUE);

-- Insert some default settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_maintenance', 'false', 'boolean'),
('booking_enabled', 'true', 'boolean'),
('min_booking_duration', '60', 'integer'),
('max_booking_duration', '240', 'integer'),
('opening_time', '16:00', 'time'),
('closing_time', '00:00', 'time');
