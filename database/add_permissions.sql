-- Add permissions table for admin permissions management
-- This table stores permissions for each admin user

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_key VARCHAR(50) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission keys:
-- 'view_groups' - Guruhlarni ko'rish
-- 'create_groups' - Guruh yaratish
-- 'edit_groups' - Guruh tahrirlash
-- 'delete_groups' - Guruh o'chirish
-- 'view_contacts' - Kontaktlarni ko'rish
-- 'create_contacts' - Kontakt yaratish
-- 'edit_contacts' - Kontakt tahrirlash
-- 'delete_contacts' - Kontakt o'chirish
-- 'send_sms' - SMS yuborish
-- 'view_reports' - Hisobotlarni ko'rish
-- 'view_dashboard' - Dashboard ko'rish

