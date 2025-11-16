<?php
/**
 * Eskiz API Test Script
 * Bu fayl Eskiz API bilan bog'lanishni test qilish uchun
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/eskiz_api.php';

echo "Eskiz API Test\n";
echo "==============\n\n";

echo "Email: " . ESKIZ_EMAIL . "\n";
echo "API URL: " . ESKIZ_API_URL . "\n\n";

// Test authentication
echo "1. Authentication test...\n";
$eskiz = new EskizAPI();

if ($eskiz) {
    echo "   ✓ EskizAPI object created\n";
    
    // Test SMS sending (use a test phone number)
    echo "\n2. SMS sending test...\n";
    echo "   Enter phone number (or press Enter to skip): ";
    $phone = trim(fgets(STDIN));
    
    if (!empty($phone)) {
        echo "   Enter test message: ";
        $message = trim(fgets(STDIN));
        
        if (!empty($message)) {
            echo "\n   Sending SMS...\n";
            $result = $eskiz->sendSMS($phone, $message);
            
            echo "\n   Result:\n";
            print_r($result);
            
            if ($result['success']) {
                echo "\n   ✓ SMS sent successfully!\n";
            } else {
                echo "\n   ✗ Failed to send SMS\n";
                echo "   Error: " . ($result['message'] ?? 'Unknown error') . "\n";
                if (isset($result['response'])) {
                    echo "   API Response: " . $result['response'] . "\n";
                }
            }
        } else {
            echo "   Skipped (no message)\n";
        }
    } else {
        echo "   Skipped (no phone number)\n";
    }
} else {
    echo "   ✗ Failed to create EskizAPI object\n";
}

echo "\nTest completed.\n";

