-- Обработанные обновления вебхука Telegram: повторно присланное обновление пропускается
CREATE TABLE IF NOT EXISTS telegram_updates (
    update_id BIGINT NOT NULL PRIMARY KEY,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
