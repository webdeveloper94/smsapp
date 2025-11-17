-- Qarzdorlar jadvali
CREATE TABLE IF NOT EXISTS debtors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    debt_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    first_send_date DATE NOT NULL,
    reminder_interval INT NULL COMMENT 'Kun oralig\'i (NULL bo\'lsa qayta yuborilmaydi)',
    last_sent_date DATE NULL,
    status ENUM('active', 'paid', 'deleted') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qarz SMS loglari jadvali
CREATE TABLE IF NOT EXISTS debt_sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debtor_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NULL,
    FOREIGN KEY (debtor_id) REFERENCES debtors(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

