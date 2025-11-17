-- Qarzlar uchun umumiy sozlamalar jadvali
CREATE TABLE IF NOT EXISTS debt_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sms_message_template TEXT NOT NULL COMMENT 'SMS matn shabloni. {name} va {amount} o\'rniga qarzdor ismi va summa qo\'yiladi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dastlabki sozlama qo'shish
INSERT INTO debt_settings (sms_message_template) 
VALUES ('sizning megamarket do\'konidan qarzingiz bor iltimos qarzingizni to\'lang')
ON DUPLICATE KEY UPDATE sms_message_template = VALUES(sms_message_template);

