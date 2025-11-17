-- debt_settings jadvaliga user_id ustunini qo'shish
-- Har bir admin uchun alohida SMS matn shabloni

-- user_id ustunini qo'shish
ALTER TABLE debt_settings 
ADD COLUMN user_id INT NULL AFTER id,
ADD INDEX idx_user_id (user_id);

-- Mavjud ma'lumotlarni super_admin ga biriktirish (agar mavjud bo'lsa)
UPDATE debt_settings SET user_id = (SELECT id FROM users WHERE role = 'super_admin' LIMIT 1) WHERE user_id IS NULL;

-- Har bir admin uchun default shablon yaratish
INSERT INTO debt_settings (user_id, sms_message_template)
SELECT id, 'sizning megamarket do\'konidan qarzingiz bor iltimos qarzingizni to\'lang'
FROM users 
WHERE role = 'admin' 
AND id NOT IN (SELECT COALESCE(user_id, 0) FROM debt_settings WHERE user_id IS NOT NULL)
ON DUPLICATE KEY UPDATE sms_message_template = VALUES(sms_message_template);

-- user_id ni NOT NULL qilish (keyinroq)
-- ALTER TABLE debt_settings MODIFY COLUMN user_id INT NOT NULL;

