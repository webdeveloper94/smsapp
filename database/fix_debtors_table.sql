-- Qarzdorlar jadvalini to'g'rilash
-- Agar jadval mavjud bo'lsa, faqat yetishmayotgan ustunlarni qo'shadi

-- Jadval mavjud emas bo'lsa, to'liq yaratadi
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

-- Agar jadval mavjud bo'lsa, yetishmayotgan ustunlarni qo'shadi
-- Eslatma: Agar ustun allaqachon mavjud bo'lsa, xato beradi, lekin bu xavfsiz
-- Avval tekshiring: DESCRIBE debtors;

-- reminder_interval ustunini qo'shish
SET @dbname = DATABASE();
SET @tablename = 'debtors';
SET @columnname = 'reminder_interval';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL COMMENT \'Kun oralig\\\'i (NULL bo\\\'lsa qayta yuborilmaydi)\' AFTER first_send_date')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- last_sent_date ustunini qo'shish
SET @columnname = 'last_sent_date';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATE NULL AFTER reminder_interval')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

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

