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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check permissions
if (!$auth->hasPermission('send_sms')) {
    echo json_encode(['success' => false, 'message' => 'SMS yuborish huquqi yo\'q']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Telefon raqami va SMS matni kiritilishi shart']);
    exit;
}

// Format phone number (add 998 if not present)
if (!preg_match('/^998/', $phone)) {
    // Remove any non-digit characters first
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Add 998 if it doesn't start with it
    if (!preg_match('/^998/', $phone)) {
        $phone = '998' . $phone;
    }
}

// Validate phone number
if (!preg_match('/^998[0-9]{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Noto\'g\'ri telefon raqami formati']);
    exit;
}

// Check SMS limit
$limitInfo = $auth->checkSMSLimit();
if (!$limitInfo['allowed']) {
    $limitMsg = $limitInfo['limit'] > 0 
        ? "SMS limiti tugagan. Limit: {$limitInfo['limit']}, Yuborilgan: {$limitInfo['sent']}"
        : "SMS limiti belgilanmagan";
    echo json_encode(['success' => false, 'message' => $limitMsg]);
    exit;
}

try {
    // Send SMS
    $eskiz = new EskizAPI();
    
    // Log before sending
    error_log("Attempting to send direct SMS to: " . $phone . ", Message length: " . strlen($message));
    
    $result = $eskiz->sendSMS($phone, $message);
    
    // Log result
    error_log("Direct SMS Send Result: " . print_r($result, true));
    
    if ($result['success']) {
        // Increment SMS count for current user
        $auth->incrementSMSCount($userId);
        
        // Log SMS (without contact_id since it's a direct SMS)
        $db->query(
            "INSERT INTO sms_logs (contact_id, phone, message, status, sent_by) VALUES (NULL, ?, ?, 'success', ?)",
            [$phone, $message, $userId]
        );
        
        // Log activity
        $auth->logActivity($userId, 'send_direct_sms', 'direct', null, "Direct SMS sent to " . $phone);
        
        // Get updated limit info
        $updatedLimit = $auth->checkSMSLimit();
        $limitMsg = '';
        if ($updatedLimit['limit'] > 0) {
            $limitMsg = " Qolgan: {$updatedLimit['remaining']} / {$updatedLimit['limit']}";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'SMS muvaffaqiyatli yuborildi!' . $limitMsg
        ]);
    } else {
        // Log failure
        $errorMsg = $result['message'] ?? 'Noma\'lum xatolik';
        $db->query(
            "INSERT INTO sms_logs (contact_id, phone, message, status, error_message, sent_by) VALUES (NULL, ?, ?, 'failed', ?, ?)",
            [$phone, $message, $errorMsg, $userId]
        );
        
        echo json_encode([
            'success' => false, 
            'message' => 'SMS yuborishda xatolik: ' . $errorMsg
        ]);
    }
} catch (Exception $e) {
    error_log("Direct SMS sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
    ]);
}

