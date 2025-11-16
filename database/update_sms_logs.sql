-- Update SMS Logs table if needed
-- This file is for reference only, as the table already exists in schema.sql
-- If you need to add any additional columns, use this file

-- Check if sms_logs table exists and has sent_by column
-- The table should already exist from schema.sql with the following structure:
-- 
-- CREATE TABLE IF NOT EXISTS sms_logs (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     contact_id INT NOT NULL,
--     phone VARCHAR(20) NOT NULL,
--     message TEXT NOT NULL,
--     status ENUM('success', 'failed') NOT NULL,
--     error_message TEXT NULL,
--     sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     sent_by INT NULL,
--     FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
--     FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If you need to add sent_by column (in case it doesn't exist):
-- ALTER TABLE sms_logs ADD COLUMN sent_by INT NULL AFTER sent_at;
-- ALTER TABLE sms_logs ADD FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL;

-- Note: The table structure is already correct in schema.sql
-- This file is just for reference in case you need to update existing database

