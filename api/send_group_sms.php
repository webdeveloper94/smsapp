<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/eskiz_api.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$groupId = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

if (empty($groupId)) {
    echo json_encode(['success' => false, 'message' => 'Guruh ID kiritilishi shart']);
    exit;
}

// Check permissions
if (!$auth->hasPermission('send_sms')) {
    echo json_encode(['success' => false, 'message' => 'SMS yuborish huquqi yo\'q']);
    exit;
}

// Get group
$group = $db->query(
    "SELECT g.*, u.first_name, u.last_name 
     FROM groups g 
     LEFT JOIN users u ON g.created_by = u.id 
     WHERE g.id = ?",
    [$groupId]
)->fetch();

if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Guruh topilmadi']);
    exit;
}

// Check if user has permission to send SMS to this group
if ($userRole !== 'super_admin' && $group['created_by'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Bu guruhga SMS yuborish huquqi yo\'q']);
    exit;
}

// Get group settings for default message
$groupSettings = $db->query(
    "SELECT default_message FROM group_settings WHERE group_id = ?",
    [$groupId]
)->fetch();

$defaultMessage = $groupSettings ? ($groupSettings['default_message'] ?? '') : '';

// Get all contacts in this group
$contacts = $db->query(
    "SELECT c.*, g.created_by 
     FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     WHERE c.group_id = ?",
    [$groupId]
)->fetchAll();

if (empty($contacts)) {
    echo json_encode(['success' => false, 'message' => 'Guruhda kontaktlar mavjud emas']);
    exit;
}

// Check SMS limit before sending
$limitInfo = $auth->checkSMSLimit();
if (!$limitInfo['allowed']) {
    $limitMsg = $limitInfo['limit'] > 0 
        ? "SMS limiti tugagan. Limit: {$limitInfo['limit']}, Yuborilgan: {$limitInfo['sent']}"
        : "SMS limiti belgilanmagan";
    echo json_encode(['success' => false, 'message' => $limitMsg]);
    exit;
}

// Check if we have enough limit for all contacts
$contactsCount = count($contacts);
if ($limitInfo['limit'] > 0 && $limitInfo['remaining'] < $contactsCount) {
    echo json_encode([
        'success' => false, 
        'message' => "SMS limiti yetarli emas. Qolgan: {$limitInfo['remaining']}, Kerak: {$contactsCount}"
    ]);
    exit;
}

$eskiz = new EskizAPI();
$sentCount = 0;
$failedCount = 0;
$results = [];

try {
    foreach ($contacts as $contact) {
        // Get message for this contact
        $message = $contact['message'];
        if (empty($message)) {
            $message = $defaultMessage;
        }
        
        if (empty($message)) {
            // Skip if no message
            $results[] = [
                'contact_id' => $contact['id'],
                'phone' => $contact['phone'],
                'success' => false,
                'message' => 'SMS matni mavjud emas'
            ];
            $failedCount++;
            continue;
        }
        
        // Check SMS limit for each contact's creator (before sending)
        $contactCreator = $db->query("SELECT role, sms_limit FROM users WHERE id = ?", [$contact['created_by']])->fetch();
        if ($contactCreator && $contactCreator['role'] === 'admin' && $contactCreator['sms_limit'] !== null && $contactCreator['sms_limit'] != -1) {
            $currentMonth = date('Y-m');
            $smsCountRecord = $db->query(
                "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND `year_month` = ?",
                [$contact['created_by'], $currentMonth]
            )->fetch();
            
            $sentCountForUser = $smsCountRecord ? (int)$smsCountRecord['sent_count'] : 0;
            if ($sentCountForUser >= $contactCreator['sms_limit']) {
                $results[] = [
                    'contact_id' => $contact['id'],
                    'phone' => $contact['phone'],
                    'success' => false,
                    'message' => 'SMS limiti tugagan (admin uchun)'
                ];
                $failedCount++;
                continue;
            }
        }
        
        // Send SMS
        $result = $eskiz->sendSMS($contact['phone'], $message);
        
        if ($result['success']) {
            // Update contact status
            $db->query(
                "UPDATE contacts SET status = 'sent', sent_at = NOW() WHERE id = ?",
                [$contact['id']]
            );
            
            // Increment SMS count for contact's creator
            $auth->incrementSMSCount($contact['created_by']);
            
            // Log SMS
            $db->query(
                "INSERT INTO sms_logs (contact_id, phone, message, status, sent_by) VALUES (?, ?, ?, 'success', ?)",
                [$contact['id'], $contact['phone'], $message, $userId]
            );
            
            $results[] = [
                'contact_id' => $contact['id'],
                'phone' => $contact['phone'],
                'success' => true,
                'message' => 'SMS muvaffaqiyatli yuborildi'
            ];
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
                [$contact['id'], $contact['phone'], $message, $errorMsg, $userId]
            );
            
            $results[] = [
                'contact_id' => $contact['id'],
                'phone' => $contact['phone'],
                'success' => false,
                'message' => 'SMS yuborishda xatolik: ' . $errorMsg
            ];
            $failedCount++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    // Log activity
    $auth->logActivity($userId, 'send_group_sms', 'groups', $groupId, "Guruhdagi barcha kontaktlarga SMS yuborildi. Yuborilgan: $sentCount, Xatolik: $failedCount");
    
    // Get updated limit info
    $updatedLimit = $auth->checkSMSLimit();
    $limitMsg = '';
    if ($updatedLimit['limit'] > 0) {
        $limitMsg = " Qolgan: {$updatedLimit['remaining']} / {$updatedLimit['limit']}";
    }
    
    echo json_encode([
        'success' => true,
        'message' => "SMS yuborish yakunlandi. Yuborilgan: $sentCount, Xatolik: $failedCount" . $limitMsg,
        'sent_count' => $sentCount,
        'failed_count' => $failedCount,
        'total' => count($contacts),
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log("Group SMS sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
    ]);
}

