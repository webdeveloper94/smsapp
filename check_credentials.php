<?php
/**
 * Eskiz Credentials Checker
 * Bu fayl email va parolni to'g'riligini tekshiradi
 */

require_once __DIR__ . '/config/config.php';

echo "Eskiz Credentials Checker\n";
echo "=========================\n\n";

$email = ESKIZ_EMAIL;
$password = ESKIZ_PASSWORD;

echo "Email: $email\n";
echo "Email length: " . strlen($email) . " characters\n";
echo "Email trimmed: '" . trim($email) . "'\n";
echo "Password length: " . strlen($password) . " characters\n";
echo "Password first char: " . (strlen($password) > 0 ? substr($password, 0, 1) : 'EMPTY') . "\n";
echo "Password last char: " . (strlen($password) > 0 ? substr($password, -1) : 'EMPTY') . "\n\n";

// Check for hidden characters
echo "Email bytes: ";
for ($i = 0; $i < strlen($email); $i++) {
    echo ord($email[$i]) . " ";
}
echo "\n";

echo "Password bytes (first 5): ";
for ($i = 0; $i < min(5, strlen($password)); $i++) {
    echo ord($password[$i]) . " ";
}
echo "\n\n";

// Test with exact values (documentatsiya formatida)
echo "Testing with exact credentials (documentatsiya formatida)...\n";
$url = ESKIZ_API_URL . '/auth/login';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'email' => $email,
    'password' => $password
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'SMS App PHP Client');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['data']['token'])) {
        echo "\n✓ SUCCESS! Authentication successful!\n";
        echo "Token: " . substr($result['data']['token'], 0, 30) . "...\n";
    } else {
        echo "\n✗ Token not found in response\n";
        print_r($result);
    }
} else {
    echo "\n✗ Authentication failed\n";
    if ($curlError) {
        echo "CURL Error: $curlError\n";
    }
    $result = json_decode($response, true);
    if ($result && isset($result['message'])) {
        echo "Error message: " . $result['message'] . "\n";
    }
}

echo "\n";
echo "==========================================\n";
echo "Agar hali ham 401 xatosi bo'lsa:\n";
echo "1. Eskiz.uz saytiga kiring va email/parolni tekshiring\n";
echo "2. Parolni tiklang yoki yangilang\n";
echo "3. Hisob faol ekanligini tekshiring\n";
echo "4. config/config.php faylida email va parolni yangilang\n";

