-- Qarzdorlar jadvali
CREATE TABLE IF NOT EXISTS debtors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    debt_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    message TEXT NULL,
    first_send_date DATE NOT NULL,
    reminder_interval_days INT NULL COMMENT 'Qayta yuborish oralig\'i (kunlar). NULL bo\'lsa qayta yuborilmaydi',
    last_sent_date DATE NULL COMMENT 'Oxirgi SMS yuborilgan sana',
    next_send_date DATE NULL COMMENT 'Keyingi SMS yuboriladigan sana',
    status ENUM('active', 'paid', 'cancelled') DEFAULT 'active' COMMENT 'active - faol, paid - to\'langan, cancelled - bekor qilingan',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qarzdorlar SMS loglari
CREATE TABLE IF NOT EXISTS debtor_sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debtor_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NULL,
    is_reminder TINYINT(1) DEFAULT 0 COMMENT 'Qayta yuborishmi yoki birinchi yuborishmi',
    FOREIGN KEY (debtor_id) REFERENCES debtors(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for better performance
CREATE INDEX idx_debtor_status ON debtors(status);
CREATE INDEX idx_debtor_next_send_date ON debtors(next_send_date);
CREATE INDEX idx_debtor_first_send_date ON debtors(first_send_date);

