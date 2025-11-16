<?php
/**
 * Eskiz API Authentication Test
 * Bu fayl faqat autentifikatsiyani test qilish uchun
 */

require_once __DIR__ . '/config/config.php';

echo "Eskiz API Authentication Test\n";
echo "=============================\n\n";

echo "Email: " . ESKIZ_EMAIL . "\n";
echo "API URL: " . ESKIZ_API_URL . "\n\n";

$url = ESKIZ_API_URL . '/auth/login';

// Test 1: multipart/form-data
echo "Test 1: multipart/form-data (array)\n";
$data1 = [
    'email' => trim(ESKIZ_EMAIL),
    'password' => trim(ESKIZ_PASSWORD)
];

$ch1 = curl_init($url);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, $data1);
curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);

$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "HTTP Code: $httpCode1\n";
echo "Response: $response1\n";
$result1 = json_decode($response1, true);
if ($result1 && isset($result1['data']['token'])) {
    echo "✓ SUCCESS! Token: " . substr($result1['data']['token'], 0, 20) . "...\n";
} else {
    echo "✗ FAILED\n";
    if ($result1 && isset($result1['message'])) {
        echo "Error: " . $result1['message'] . "\n";
    }
}
echo "\n";

// Test 2: application/x-www-form-urlencoded
echo "Test 2: application/x-www-form-urlencoded\n";
$data2 = http_build_query([
    'email' => trim(ESKIZ_EMAIL),
    'password' => trim(ESKIZ_PASSWORD)
]);

$ch2 = curl_init($url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $data2);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
echo "Response: $response2\n";
$result2 = json_decode($response2, true);
if ($result2 && isset($result2['data']['token'])) {
    echo "✓ SUCCESS! Token: " . substr($result2['data']['token'], 0, 20) . "...\n";
} else {
    echo "✗ FAILED\n";
    if ($result2 && isset($result2['message'])) {
        echo "Error: " . $result2['message'] . "\n";
    }
}
echo "\n";

// Test 3: JSON (eski versiya)
echo "Test 3: application/json (for comparison)\n";
$data3 = json_encode([
    'email' => trim(ESKIZ_EMAIL),
    'password' => trim(ESKIZ_PASSWORD)
]);

$ch3 = curl_init($url);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, $data3);
curl_setopt($ch3, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, false);

$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

echo "HTTP Code: $httpCode3\n";
echo "Response: $response3\n";
$result3 = json_decode($response3, true);
if ($result3 && isset($result3['data']['token'])) {
    echo "✓ SUCCESS! Token: " . substr($result3['data']['token'], 0, 20) . "...\n";
} else {
    echo "✗ FAILED\n";
    if ($result3 && isset($result3['message'])) {
        echo "Error: " . $result3['message'] . "\n";
    }
}

echo "\n";
echo "==========================================\n";
echo "DIQQAT: Agar barcha testlar FAILED bo'lsa, email yoki parol noto'g'ri!\n";
echo "Eskiz.uz saytiga kiring va email/parolni tekshiring.\n";

