<?php
/**
 * SMS Yuborish Cron Job
 * Bu fayl har kuni yoki har soatda ishga tushirilishi kerak
 * 
 * Windows Task Scheduler yoki Linux Cron orqali ishga tushirish:
 * Windows: schtasks /create /tn "SendSMS" /tr "php C:\xampp\htdocs\smsapp\cron\send_sms.php" /sc daily /st 09:00
 * Linux: 0 9 * * * /usr/bin/php /path/to/smsapp/cron/send_sms.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eskiz_api.php';

$db = Database::getInstance();
$eskiz = new EskizAPI();

// Get contacts that need to be sent today
$today = date('Y-m-d');

// Get contacts with their own send_date
$contacts = $db->query(
    "SELECT c.*, g.created_by 
     FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     WHERE c.status = 'pending' 
     AND c.send_date = ?",
    [$today]
)->fetchAll();

// Get contacts with group default date (where contact doesn't have own date)
$groupDefaultContacts = $db->query(
    "SELECT c.*, g.created_by, gs.default_send_date, gs.default_message
     FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     JOIN group_settings gs ON gs.group_id = c.group_id
     WHERE c.status = 'pending' 
     AND (c.send_date IS NULL OR c.send_date = '')
     AND gs.default_send_date = ?",
    [$today]
)->fetchAll();

// Merge and remove duplicates
$allContacts = [];
$contactIds = [];

foreach ($contacts as $contact) {
    $allContacts[] = $contact;
    $contactIds[] = $contact['id'];
}

foreach ($groupDefaultContacts as $contact) {
    if (!in_array($contact['id'], $contactIds)) {
        $allContacts[] = $contact;
    }
}

$sentCount = 0;
$failedCount = 0;

foreach ($allContacts as $contact) {
    try {
        // Check SMS limit for admin (skip for super_admin)
        $user = $db->query("SELECT role, sms_limit FROM users WHERE id = ?", [$contact['created_by']])->fetch();
        if ($user && $user['role'] === 'admin' && $user['sms_limit'] !== null && $user['sms_limit'] != -1) {
            $currentMonth = date('Y-m');
            $smsCount = $db->query(
                "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND `year_month` = ?",
                [$contact['created_by'], $currentMonth]
            )->fetch();
            
            $sentCount = $smsCount ? (int)$smsCount['sent_count'] : 0;
            if ($sentCount >= $user['sms_limit']) {
                // Skip - limit reached
                error_log("SMS limit reached for user {$contact['created_by']}. Limit: {$user['sms_limit']}, Sent: $sentCount");
                $failedCount++;
                continue;
            }
        }
        
        // Get message
        $message = $contact['message'];
        if (empty($message) && isset($contact['default_message'])) {
            $message = $contact['default_message'];
        }
        
        if (empty($message)) {
            // Skip if no message
            $db->query(
                "UPDATE contacts SET status = 'failed' WHERE id = ?",
                [$contact['id']]
            );
            $db->query(
                "INSERT INTO sms_logs (contact_id, phone, message, status, error_message) VALUES (?, ?, ?, 'failed', ?)",
                [$contact['id'], $contact['phone'], $message ?: '', 'Xabar matni mavjud emas']
            );
            $failedCount++;
            continue;
        }

        // Send SMS
        $result = $eskiz->sendSMS($contact['phone'], $message);
        
        if ($result['success']) {
            // Update contact status
            $db->query(
                "UPDATE contacts SET status = 'sent', sent_at = NOW() WHERE id = ?",
                [$contact['id']]
            );
            
            // Increment SMS count for admin (using Auth class)
            $auth = new Auth();
            $auth->incrementSMSCount($contact['created_by']);
            
            // Log success
            $db->query(
                "INSERT INTO sms_logs (contact_id, phone, message, status, sent_by) VALUES (?, ?, ?, 'success', ?)",
                [$contact['id'], $contact['phone'], $message, $contact['created_by']]
            );
            
            $sentCount++;
        } else {
            // Update contact status
            $db->query(
                "UPDATE contacts SET status = 'failed' WHERE id = ?",
                [$contact['id']]
            );
            
            // Log failure
            $errorMsg = $result['message'] ?? 'Noma\'lum xatolik';
            $db->query(
                "INSERT INTO sms_logs (contact_id, phone, message, status, error_message, sent_by) VALUES (?, ?, ?, 'failed', ?, ?)",
                [$contact['id'], $contact['phone'], $message, $errorMsg, $contact['created_by']]
            );
            
            $failedCount++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
        
    } catch (Exception $e) {
        error_log("SMS sending error for contact {$contact['id']}: " . $e->getMessage());
        $failedCount++;
    }
}

// Log summary
error_log("SMS sending completed. Sent: $sentCount, Failed: $failedCount");

echo "SMS yuborish yakunlandi. Yuborilgan: $sentCount, Xatolik: $failedCount\n";

