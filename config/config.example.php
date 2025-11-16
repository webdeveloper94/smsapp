<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smsapp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Eskiz SMS API Configuration
define('ESKIZ_EMAIL', 'your_email@example.com'); // Eskiz email
define('ESKIZ_PASSWORD', 'your_password'); // Eskiz parol
define('ESKIZ_API_URL', 'https://notify.eskiz.uz/api');

// Application Settings
define('APP_NAME', 'SMS App');
define('SESSION_LIFETIME', 3600); // 1 hour

// Timezone
date_default_timezone_set('Asia/Tashkent');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

