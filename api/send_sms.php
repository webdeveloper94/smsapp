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

$contactId = $_POST['contact_id'] ?? 0;
$message = trim($_POST['message'] ?? '');

if (empty($contactId) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Kontakt ID va SMS matni kiritilishi shart']);
    exit;
}

// Get contact
$contact = $db->query(
    "SELECT c.*, g.created_by 
     FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     WHERE c.id = ?",
    [$contactId]
)->fetch();

if (!$contact) {
    echo json_encode(['success' => false, 'message' => 'Kontakt topilmadi']);
    exit;
}

// Check permissions
$userRole = $auth->getUserRole();
if ($userRole !== 'super_admin' && $contact['created_by'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Ruxsat yo\'q']);
    exit;
}

try {
    // Send SMS
    $eskiz = new EskizAPI();
    
    // Log before sending
    error_log("Attempting to send SMS to: " . $contact['phone'] . ", Message length: " . strlen($message));
    
    $result = $eskiz->sendSMS($contact['phone'], $message);
    
    // Log result
    error_log("SMS Send Result: " . print_r($result, true));
    
    if ($result['success']) {
        // Update contact status
        $db->query(
            "UPDATE contacts SET status = 'sent', sent_at = NOW() WHERE id = ?",
            [$contactId]
        );
        
        // Log SMS
        $db->query(
            "INSERT INTO sms_logs (contact_id, phone, message, status, sent_by) VALUES (?, ?, ?, 'success', ?)",
            [$contactId, $contact['phone'], $message, $userId]
        );
        
        // Log activity
        $auth->logActivity($userId, 'send_sms', 'contacts', $contactId, "SMS sent manually to " . $contact['phone']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'SMS muvaffaqiyatli yuborildi!'
        ]);
    } else {
        // Update contact status
        $db->query(
            "UPDATE contacts SET status = 'failed' WHERE id = ?",
            [$contactId]
        );
        
        // Log failure
        $errorMsg = $result['message'] ?? 'Noma\'lum xatolik';
        $db->query(
            "INSERT INTO sms_logs (contact_id, phone, message, status, error_message, sent_by) VALUES (?, ?, ?, 'failed', ?, ?)",
            [$contactId, $contact['phone'], $message, $errorMsg, $userId]
        );
        
        echo json_encode([
            'success' => false, 
            'message' => 'SMS yuborishda xatolik: ' . $errorMsg
        ]);
    }
} catch (Exception $e) {
    error_log("SMS sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
    ]);
}

