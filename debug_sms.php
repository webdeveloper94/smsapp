<?php
/**
 * SMS Debug Page
 * Bu sahifa SMS yuborish muammosini tekshirish uchun
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/eskiz_api.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$errors = [];
$info = [];

// Check configuration
$info[] = "Email: " . ESKIZ_EMAIL;
$info[] = "API URL: " . ESKIZ_API_URL;
$info[] = "Password: " . (ESKIZ_PASSWORD ? "***" : "NOT SET");

// Check token file
$tokenFile = __DIR__ . '/storage/eskiz_token.txt';
if (file_exists($tokenFile)) {
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if ($tokenData) {
        $info[] = "Token file exists";
        $info[] = "Token expiry: " . date('Y-m-d H:i:s', $tokenData['expiry'] ?? 0);
        $info[] = "Token valid: " . (time() < ($tokenData['expiry'] ?? 0) ? 'Yes' : 'No');
    } else {
        $errors[] = "Token file exists but is invalid";
    }
} else {
    $info[] = "Token file does not exist (will be created on first auth)";
}

// Test API connection
try {
    // First test authentication separately
    $info[] = "Testing authentication...";
    
    $authUrl = ESKIZ_API_URL . '/auth/login';
    $authData = [
        'email' => ESKIZ_EMAIL,
        'password' => ESKIZ_PASSWORD
    ];
    
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $authData); // multipart/form-data uchun array
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $authResponse = curl_exec($ch);
    $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $authCurlError = curl_error($ch);
    curl_close($ch);
    
    $info[] = "Auth HTTP Code: $authHttpCode";
    if ($authCurlError) {
        $errors[] = "Auth CURL Error: $authCurlError";
    } else {
        $info[] = "Auth Response: " . substr($authResponse, 0, 500);
        $authResult = json_decode($authResponse, true);
        if ($authResult) {
            $info[] = "Auth Response (decoded): " . print_r($authResult, true);
            if (isset($authResult['data']['token'])) {
                $info[] = "✓ Token found in response!";
            } elseif (isset($authResult['token'])) {
                $info[] = "✓ Token found (direct format)!";
            } else {
                $errors[] = "✗ Token not found in response structure";
            }
        }
    }
    
    $eskiz = new EskizAPI();
    $info[] = "EskizAPI object created successfully";
    
    // Check if token was loaded
    $tokenFile = __DIR__ . '/storage/eskiz_token.txt';
    if (file_exists($tokenFile)) {
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        if ($tokenData && isset($tokenData['token'])) {
            $info[] = "✓ Token loaded from file";
        } else {
            $errors[] = "✗ Token file exists but token not found";
        }
    } else {
        $errors[] = "✗ Token file not created (authentication failed)";
    }
    
    // Try to send a test SMS (if phone provided)
    if (isset($_POST['test_phone']) && isset($_POST['test_message'])) {
        $testPhone = $_POST['test_phone'];
        $testMessage = $_POST['test_message'];
        
        $info[] = "Attempting to send test SMS to: $testPhone";
        $result = $eskiz->sendSMS($testPhone, $testMessage);
        
        if ($result['success']) {
            $info[] = "✓ Test SMS sent successfully!";
        } else {
            $errors[] = "✗ Test SMS failed: " . ($result['message'] ?? 'Unknown error');
            if (isset($result['response'])) {
                $errors[] = "API Response: " . (is_string($result['response']) ? substr($result['response'], 0, 500) : json_encode($result['response']));
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "Exception: " . $e->getMessage();
    $errors[] = "Stack trace: " . $e->getTraceAsString();
}

// Check PHP error log
$errorLog = ini_get('error_log');
if ($errorLog) {
    $info[] = "PHP Error Log: $errorLog";
} else {
    $info[] = "PHP Error Log: Default (check Apache/PHP logs)";
}

// Check recent SMS logs
$recentLogs = $db->query(
    "SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Debug - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>SMS Debug</h1>
        
        <div class="card">
            <div class="card-header">Ma'lumotlar</div>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($info as $item): ?>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);"><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="card">
                <div class="card-header" style="color: var(--danger-color);">Xatoliklar</div>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color); color: var(--danger-color);"><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Test SMS Yuborish</div>
            <form method="POST">
                <div class="form-group">
                    <label for="test_phone">Telefon Raqami</label>
                    <input type="text" id="test_phone" name="test_phone" placeholder="998901234567" required>
                </div>
                <div class="form-group">
                    <label for="test_message">Test Xabar</label>
                    <textarea id="test_message" name="test_message" rows="3" required>Test xabar</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Test SMS Yuborish</button>
            </form>
        </div>
        
        <?php if (!empty($recentLogs)): ?>
            <div class="card">
                <div class="card-header">Oxirgi SMS Loglari (10 ta)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Telefon</th>
                            <th>Xabar</th>
                            <th>Holat</th>
                            <th>Xatolik</th>
                            <th>Vaqt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)); ?>...</td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo $log['status'] === 'success' ? 'Muvaffaqiyatli' : 'Xatolik'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['error_message'] ?? '-'); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($log['sent_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

