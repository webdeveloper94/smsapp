# SMS App - Guruhlangan Kontaktlarga SMS Yuborish Tizimi

Bu loyiha guruhlangan kontaktlarga Eskiz SMS API orqali SMS yuborish uchun yaratilgan.

## Texnologiyalar

- PHP (Framework ishlatilmagan)
- MySQL
- HTML, CSS, JavaScript
- Eskiz SMS API

## Xususiyatlar

- **Rollar**: Super Admin va Admin
- **Autentifikatsiya**: Faqat parol orqali kirish
- **Guruhlar**: Kontaktlarni guruhlash
- **SMS Rejalashtirish**: Sana bo'yicha avtomatik SMS yuborish
- **Faoliyat Jurnali**: Kim nima qilganini kuzatish

## O'rnatish

### 1. Ma'lumotlar Bazasini Yaratish

```bash
mysql -u root -p < database/schema.sql
```

Yoki phpMyAdmin orqali `database/schema.sql` faylini import qiling.

### 2. Konfiguratsiya

`config/config.php` faylini ochib, quyidagilarni to'ldiring:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smsapp');
define('DB_USER', 'root');
define('DB_PASS', '');

define('ESKIZ_EMAIL', 'your_email@example.com');
define('ESKIZ_PASSWORD', 'your_password');
```

### 3. Dastlabki Super Admin Yaratish

Brauzerda yoki terminalda `setup.php` faylini ishga tushiring:

```
http://localhost/smsapp/setup.php
```

Yoki terminalda:
```bash
php setup.php
```

Dastlabki super admin paroli: `admin123` (birinchi kirishdan keyin o'zgartiring!)

### 4. SMS Yuborish Cron Job

Windows uchun:
```bash
schtasks /create /tn "SendSMS" /tr "php C:\xampp\htdocs\smsapp\cron\send_sms.php" /sc daily /st 09:00
```

Linux uchun (crontab):
```bash
0 9 * * * /usr/bin/php /path/to/smsapp/cron/send_sms.php
```

Yoki har soatda:
```bash
0 * * * * /usr/bin/php /path/to/smsapp/cron/send_sms.php
```

## Foydalanish

1. `/login.php` sahifasiga kiring
2. Parolni kiriting
3. Super Admin sifatida adminlar yarating
4. Guruhlar yarating
5. Kontaktlar qo'shing
6. SMS matn va sanani belgilang
7. Cron job avtomatik SMS yuboradi

## Struktura

```
smsapp/
├── assets/
│   ├── css/
│   └── js/
├── config/
├── cron/
├── database/
├── groups/
│   └── contacts/
├── admins/
├── includes/
└── storage/
```

## Xavfsizlik

- Parollar bcrypt orqali hash qilinadi
- SQL injectiondan himoya (PDO prepared statements)
- XSSdan himoya (htmlspecialchars)
- Session boshqaruvi
- Faqat parol orqali autentifikatsiya

## Eslatmalar

- Eskiz SMS API token avtomatik yangilanadi
- Token `storage/eskiz_token.txt` faylida saqlanadi
- Faoliyat jurnali `activity_logs` jadvalida
- SMS loglari `sms_logs` jadvalida

