<?php
/**
 * Qarz Eslamalari SMS Yuborish Cron Job
 * Bu fayl har kuni yoki har soatda ishga tushirilishi kerak
 * 
 * Windows Task Scheduler yoki Linux Cron orqali ishga tushirish:
 * Windows: schtasks /create /tn "SendDebtReminders" /tr "php C:\xampp\htdocs\smsapp\cron\send_debt_reminders.php" /sc daily /st 09:00
 * Linux: 0 9 * * * /usr/bin/php /path/to/smsapp/cron/send_debt_reminders.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eskiz_api.php';

$db = Database::getInstance();
$eskiz = new EskizAPI();

$today = date('Y-m-d');
$sentCount = 0;
$failedCount = 0;

// Get debtors that need SMS today
// 1. First SMS - debtors with first_send_date = today and status = active
//    (last_sent_date should be NULL or before first_send_date)
$firstSMSDebtors = $db->query(
    "SELECT * FROM debtors 
     WHERE status = 'active' 
     AND first_send_date = ? 
     AND (last_sent_date IS NULL OR last_sent_date < first_send_date)",
    [$today]
)->fetchAll();

// 2. Reminder SMS - debtors with reminder_interval and last_sent_date + interval = today
$reminderDebtors = $db->query(
    "SELECT * FROM debtors 
     WHERE status = 'active' 
     AND reminder_interval IS NOT NULL 
     AND reminder_interval > 0
     AND last_sent_date IS NOT NULL
     AND DATE_ADD(last_sent_date, INTERVAL reminder_interval DAY) = ?",
    [$today]
)->fetchAll();

// Merge debtors
$allDebtors = [];
$debtorIds = [];

foreach ($firstSMSDebtors as $debtor) {
    $allDebtors[] = $debtor;
    $debtorIds[] = $debtor['id'];
}

foreach ($reminderDebtors as $debtor) {
    if (!in_array($debtor['id'], $debtorIds)) {
        $allDebtors[] = $debtor;
    }
}

foreach ($allDebtors as $debtor) {
    try {
        // Check SMS limit for admin (skip for super_admin)
        $user = $db->query("SELECT role, sms_limit FROM users WHERE id = ?", [$debtor['created_by']])->fetch();
        if ($user && $user['role'] === 'admin' && $user['sms_limit'] !== null && $user['sms_limit'] != -1) {
            $currentMonth = date('Y-m');
            $smsCount = $db->query(
                "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND `year_month` = ?",
                [$debtor['created_by'], $currentMonth]
            )->fetch();
            
            $sentCount = $smsCount ? (int)$smsCount['sent_count'] : 0;
            if ($sentCount >= $user['sms_limit']) {
                // Skip - limit reached
                error_log("SMS limit reached for user {$debtor['created_by']}. Limit: {$user['sms_limit']}, Sent: $sentCount");
                $failedCount++;
                continue;
            }
        }
        
        // Get SMS template for this debtor's creator
        $debtSettings = $db->query(
            "SELECT sms_message_template FROM debt_settings WHERE user_id = ? LIMIT 1",
            [$debtor['created_by']]
        )->fetch();
        $messageTemplate = $debtSettings ? $debtSettings['sms_message_template'] : 'sizning megamarket do\'konidan qarzingiz bor iltimos qarzingizni to\'lang';
        
        // Generate SMS message using template
        $formattedAmount = number_format($debtor['debt_amount'], 0, ',', ' ');
        $message = "Hurmatli " . $debtor['name'] . "! " . $messageTemplate . " Qarzingiz: " . $formattedAmount . " so'm.";
        
        // Send SMS
        $result = $eskiz->sendSMS($debtor['phone'], $message);
        
        if ($result['success']) {
            // Update last_sent_date
            $db->query(
                "UPDATE debtors SET last_sent_date = ? WHERE id = ?",
                [$today, $debtor['id']]
            );
            
            // Increment SMS count for admin (using Auth class)
            $auth = new Auth();
            $auth->incrementSMSCount($debtor['created_by']);
            
            // Log success
            $db->query(
                "INSERT INTO debt_sms_logs (debtor_id, phone, message, status, sent_by) VALUES (?, ?, ?, 'success', ?)",
                [$debtor['id'], $debtor['phone'], $message, $debtor['created_by']]
            );
            
            $sentCount++;
        } else {
            // Log failure
            $errorMsg = $result['message'] ?? 'Noma\'lum xatolik';
            $db->query(
                "INSERT INTO debt_sms_logs (debtor_id, phone, message, status, error_message, sent_by) VALUES (?, ?, ?, 'failed', ?, ?)",
                [$debtor['id'], $debtor['phone'], $message, $errorMsg, $debtor['created_by']]
            );
            
            $failedCount++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
        
    } catch (Exception $e) {
        error_log("Debt reminder SMS sending error for debtor {$debtor['id']}: " . $e->getMessage());
        $failedCount++;
    }
}

// Log summary
error_log("Debt reminder SMS sending completed. Sent: $sentCount, Failed: $failedCount");

echo "Qarz eslatmalari yuborish yakunlandi. Yuborilgan: $sentCount, Xatolik: $failedCount\n";

