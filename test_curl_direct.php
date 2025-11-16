<?php
/**
 * Direct CURL Test - Documentatsiyadagi curl command'ni PHP da qayta yozish
 */

require_once __DIR__ . '/config/config.php';

echo "Direct CURL Test (Documentatsiya formatida)\n";
echo "===========================================\n\n";

$email = trim(ESKIZ_EMAIL);
$password = trim(ESKIZ_PASSWORD);

echo "Email: $email\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";

$url = ESKIZ_API_URL . '/auth/login';

$ch = curl_init($url);

// --location = CURLOPT_FOLLOWLOCATION
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'email' => $email,
    'password' => $password
]);

// Boshqa sozlamalar
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

// Debug uchun - so'rovni ko'rish
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "\nHTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($curlError) {
    echo "CURL Error: $curlError\n";
}

// Verbose log (faqat muhim qismlar)
if ($verboseLog) {
    echo "\nCURL Verbose (important parts):\n";
    $lines = explode("\n", $verboseLog);
    foreach ($lines as $line) {
        if (stripos($line, 'Content-Type') !== false || 
            stripos($line, 'POST') !== false ||
            stripos($line, 'email') !== false ||
            stripos($line, 'password') !== false) {
            echo $line . "\n";
        }
    }
}

$result = json_decode($response, true);

echo "\n";
if ($httpCode === 200 && isset($result['data']['token'])) {
    echo "✓ SUCCESS! Authentication successful!\n";
    echo "Token: " . substr($result['data']['token'], 0, 30) . "...\n";
    echo "Message: " . ($result['message'] ?? 'N/A') . "\n";
} else {
    echo "✗ FAILED\n";
    if ($result && isset($result['message'])) {
        echo "Error: " . $result['message'] . "\n";
    }
    echo "\n";
    echo "DIQQAT: Agar hali ham 401 xatosi bo'lsa:\n";
    echo "1. Eskiz.uz saytiga kiring: https://notify.eskiz.uz\n";
    echo "2. Email va parol bilan kiring\n";
    echo "3. Agar kira olmasangiz, parolni tiklang\n";
    echo "4. config/config.php faylida yangi parolni kiriting\n";
}

curl_close($ch);

