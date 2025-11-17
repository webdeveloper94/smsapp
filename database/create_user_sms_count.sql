-- SMS hisoblagich jadvali (har bir admin nechta SMS yuborganini hisoblash uchun)
-- Format: YYYY-MM (masalan: 2024-01)
CREATE TABLE IF NOT EXISTS user_sms_count (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,
    sent_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month (user_id, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

