-- Oddiy versiya - faqat yetishmayotgan ustunlarni qo'shadi
-- Agar ustun allaqachon mavjud bo'lsa, xato beradi, lekin zararsiz

-- reminder_interval ustunini qo'shish
ALTER TABLE debtors 
ADD COLUMN reminder_interval INT NULL COMMENT 'Kun oralig\'i (NULL bo\'lsa qayta yuborilmaydi)' AFTER first_send_date;

-- last_sent_date ustunini qo'shish  
ALTER TABLE debtors 
ADD COLUMN last_sent_date DATE NULL AFTER reminder_interval;

