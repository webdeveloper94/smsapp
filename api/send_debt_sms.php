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

$debtorId = $_POST['debtor_id'] ?? 0;
$message = trim($_POST['message'] ?? '');

if (empty($debtorId) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Qarzdor ID va SMS matni kiritilishi shart']);
    exit;
}

// Get debtor
$debtor = $db->query(
    "SELECT * FROM debtors WHERE id = ?",
    [$debtorId]
)->fetch();

if (!$debtor) {
    echo json_encode(['success' => false, 'message' => 'Qarzdor topilmadi']);
    exit;
}

// Get SMS template for debtor's creator
$debtSettings = $db->query(
    "SELECT sms_message_template FROM debt_settings WHERE user_id = ? LIMIT 1",
    [$debtor['created_by']]
)->fetch();
$messageTemplate = $debtSettings ? $debtSettings['sms_message_template'] : 'sizning megamarket do\'konidan qarzingiz bor iltimos qarzingizni to\'lang';

// Check permissions
$userRole = $auth->getUserRole();
if (!$auth->hasPermission('send_sms')) {
    echo json_encode(['success' => false, 'message' => 'SMS yuborish huquqi yo\'q']);
    exit;
}

if ($userRole !== 'super_admin' && $debtor['created_by'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Ruxsat yo\'q']);
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
    error_log("Attempting to send SMS to debtor: " . $debtor['phone'] . ", Message length: " . strlen($message));
    
    $result = $eskiz->sendSMS($debtor['phone'], $message);
    
    // Log result
    error_log("Debt SMS Send Result: " . print_r($result, true));
    
    if ($result['success']) {
        // Update last_sent_date
        $db->query(
            "UPDATE debtors SET last_sent_date = CURDATE() WHERE id = ?",
            [$debtorId]
        );
        
        // Increment SMS count (use debtor's creator, not current user if different)
        error_log("About to increment SMS count for debtor creator: " . $debtor['created_by']);
        $auth->incrementSMSCount($debtor['created_by']);
        error_log("SMS count increment called for user: " . $debtor['created_by']);
        
        // Log SMS
        $db->query(
            "INSERT INTO debt_sms_logs (debtor_id, phone, message, status, sent_by) VALUES (?, ?, ?, 'success', ?)",
            [$debtorId, $debtor['phone'], $message, $userId]
        );
        
        // Log activity
        $auth->logActivity($userId, 'send_sms', 'debtors', $debtorId, "SMS sent manually to debtor " . $debtor['name']);
        
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
            "INSERT INTO debt_sms_logs (debtor_id, phone, message, status, error_message, sent_by) VALUES (?, ?, ?, 'failed', ?, ?)",
            [$debtorId, $debtor['phone'], $message, $errorMsg, $userId]
        );
        
        echo json_encode([
            'success' => false, 
            'message' => 'SMS yuborishda xatolik: ' . $errorMsg
        ]);
    }
} catch (Exception $e) {
    error_log("Debt SMS sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
    ]);
}

