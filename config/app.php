<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Biliardovna.sk',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Bratislava',

    'languages' => [
        'supported' => explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? 'sk,en,ru,uk,de'),
        'default' => $_ENV['DEFAULT_LANGUAGE'] ?? 'sk',
    ],

    'booking' => [
        'slots_interval' => (int)($_ENV['BOOKING_SLOTS_INTERVAL'] ?? 60),
        'advance_days' => (int)($_ENV['BOOKING_ADVANCE_DAYS'] ?? 30),
        'min_advance_hours' => (int)($_ENV['BOOKING_MIN_ADVANCE_HOURS'] ?? 2),
        'require_approval' => filter_var($_ENV['BOOKING_REQUIRE_APPROVAL'] ?? true, FILTER_VALIDATE_BOOLEAN),
        // Часы работы по дням недели (1 = понедельник), '24:00' = до полуночи.
        // Отсюда строятся слоты бронирования и часы на сайте (src/Services/OpeningHours.php).
        'opening_hours' => [
            1 => ['14:00', '22:00'],
            2 => ['14:00', '22:00'],
            3 => ['14:00', '22:00'],
            4 => ['14:00', '22:00'],
            5 => ['14:00', '24:00'],
            6 => ['14:00', '24:00'],
            7 => ['14:00', '22:00'],
        ],
    ],

    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 2592000), // 30 days
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['SESSION_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ]
];
