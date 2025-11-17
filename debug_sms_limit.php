<?php
/**
 * SMS Limit Debug Script
 * Bu fayl SMS limit muammosini tekshirish uchun
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();

echo "<h2>SMS Limit Debug Ma'lumotlari</h2>";

// 1. Check if table exists
echo "<h3>1. Jadval mavjudligini tekshirish:</h3>";
try {
    $result = $db->query("SELECT 1 FROM user_sms_count LIMIT 1");
    echo "✓ user_sms_count jadvali mavjud<br>";
} catch (PDOException $e) {
    echo "✗ user_sms_count jadvali mavjud emas: " . $e->getMessage() . "<br>";
    echo "<strong>Yechim:</strong> database/create_user_sms_count.sql faylini bajarish kerak<br>";
}

// 2. Check user info
echo "<h3>2. Foydalanuvchi ma'lumotlari:</h3>";
$user = $db->query("SELECT id, role, sms_limit, first_name, last_name FROM users WHERE id = ?", [$userId])->fetch();
if ($user) {
    echo "User ID: " . $user['id'] . "<br>";
    echo "Ism: " . $user['first_name'] . " " . $user['last_name'] . "<br>";
    echo "Rol: " . $user['role'] . "<br>";
    echo "SMS Limit: " . ($user['sms_limit'] === null ? "NULL (cheksiz)" : $user['sms_limit']) . "<br>";
} else {
    echo "✗ Foydalanuvchi topilmadi<br>";
}

// 3. Check SMS count
echo "<h3>3. SMS hisoblagichi:</h3>";
$currentMonth = date('Y-m');
try {
    $smsCount = $db->query(
        "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND year_month = ?",
        [$userId, $currentMonth]
    )->fetch();
    
    if ($smsCount) {
        echo "Oy: " . $currentMonth . "<br>";
        echo "Yuborilgan SMS: " . $smsCount['sent_count'] . "<br>";
        
        if ($user['sms_limit'] !== null && $user['sms_limit'] != -1) {
            $remaining = max(0, $user['sms_limit'] - $smsCount['sent_count']);
            echo "Qolgan SMS: " . $remaining . " / " . $user['sms_limit'] . "<br>";
        }
    } else {
        echo "Bu oy uchun SMS yuborilmagan (0)<br>";
    }
} catch (PDOException $e) {
    echo "✗ Xatolik: " . $e->getMessage() . "<br>";
}

// 4. Check all SMS counts for this user
echo "<h3>4. Barcha oylar uchun SMS hisoblagichi:</h3>";
try {
    $allCounts = $db->query(
        "SELECT `year_month`, sent_count FROM user_sms_count WHERE user_id = ? ORDER BY `year_month` DESC",
        [$userId]
    )->fetchAll();
    
    if (empty($allCounts)) {
        echo "Hech qanday SMS yuborilmagan<br>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Oy</th><th>Yuborilgan SMS</th></tr>";
        foreach ($allCounts as $count) {
            echo "<tr><td>" . $count['year_month'] . "</td><td>" . $count['sent_count'] . "</td></tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "✗ Xatolik: " . $e->getMessage() . "<br>";
}

// 5. Test increment
echo "<h3>5. Test: SMS count ni oshirish:</h3>";
try {
    $auth->incrementSMSCount($userId);
    echo "✓ incrementSMSCount() funksiyasi chaqirildi<br>";
    
    // Check again
    $smsCount = $db->query(
        "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND `year_month` = ?",
        [$userId, $currentMonth]
    )->fetch();
    
    if ($smsCount) {
        echo "Yangi yuborilgan SMS: " . $smsCount['sent_count'] . "<br>";
    } else {
        echo "⚠ Jadvalga yozilmadi (jadval mavjud emas yoki xatolik)<br>";
    }
} catch (Exception $e) {
    echo "✗ Xatolik: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>Eslatma:</strong> Agar jadval mavjud bo'lmasa, quyidagi SQL kodini bajarish kerak:</p>";
echo "<pre>";
readfile(__DIR__ . '/database/create_user_sms_count.sql');
echo "</pre>";

