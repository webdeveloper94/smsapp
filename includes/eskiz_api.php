<?php
require_once __DIR__ . '/../config/config.php';

class EskizAPI {
    private $token = null;
    private $tokenExpiry = null;

    public function __construct() {
        $this->loadToken();
    }

    private function loadToken() {
        $tokenFile = __DIR__ . '/../storage/eskiz_token.txt';
        if (file_exists($tokenFile)) {
            $data = json_decode(file_get_contents($tokenFile), true);
            if ($data && isset($data['token']) && isset($data['expiry'])) {
                if (time() < $data['expiry']) {
                    $this->token = $data['token'];
                    $this->tokenExpiry = $data['expiry'];
                    return;
                }
            }
        }
        $this->authenticate();
    }

    private function authenticate() {
        $url = ESKIZ_API_URL . '/auth/login';
        
        $email = trim(ESKIZ_EMAIL);
        $password = trim(ESKIZ_PASSWORD);

        // Log request details (parolni log qilmaymiz)
        error_log("Eskiz Auth Request - URL: $url, Email: $email");

        // Eskiz API documentatsiyasiga qarab: curl --form 'email="..."' --form 'password="..."'
        // PHP da multipart/form-data uchun CURLFile yoki array ishlatiladi
        // Lekin documentatsiyada email va password oddiy string sifatida yuboriladi
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // multipart/form-data - array yuborilganda CURL avtomatik multipart qiladi
        // Email va password to'g'ridan-to'g'ri string sifatida yuboriladi
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'email' => $email,
            'password' => $password
        ]);
        
        // Content-Type ni o'rnatmaymiz - CURL avtomatik multipart/form-data qo'shadi
        // Boundary ham avtomatik qo'shiladi
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // User-Agent qo'shamiz (ba'zi API lar buni talab qiladi)
        curl_setopt($ch, CURLOPT_USERAGENT, 'SMS App PHP Client');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        // Log for debugging
        error_log("Eskiz Auth API Response - HTTP Code: $httpCode");
        error_log("Eskiz Auth API Response Body: " . $response);
        error_log("CURL Info: " . print_r($curlInfo, true));
        
        if ($curlError) {
            error_log("CURL Error: $curlError");
            $this->token = null;
            return false;
        }

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            // Check different possible response formats
            if (isset($result['data']['token'])) {
                $this->token = $result['data']['token'];
                // Token usually expires in 30 days, but we'll refresh daily
                $this->tokenExpiry = time() + (24 * 60 * 60);
                
                // Save token
                $tokenDir = __DIR__ . '/../storage';
                if (!is_dir($tokenDir)) {
                    mkdir($tokenDir, 0755, true);
                }
                file_put_contents(
                    $tokenDir . '/eskiz_token.txt',
                    json_encode([
                        'token' => $this->token,
                        'expiry' => $this->tokenExpiry
                    ])
                );
                error_log("Token saved successfully");
                return true;
            } 
            // Check if token is directly in response
            elseif (isset($result['token'])) {
                $this->token = $result['token'];
                $this->tokenExpiry = time() + (24 * 60 * 60);
                
                $tokenDir = __DIR__ . '/../storage';
                if (!is_dir($tokenDir)) {
                    mkdir($tokenDir, 0755, true);
                }
                file_put_contents(
                    $tokenDir . '/eskiz_token.txt',
                    json_encode([
                        'token' => $this->token,
                        'expiry' => $this->tokenExpiry
                    ])
                );
                error_log("Token saved successfully (direct format)");
                return true;
            } else {
                error_log("Token not found in response. Full response: " . print_r($result, true));
                error_log("Raw response: " . $response);
            }
        } else {
            error_log("Eskiz API authentication failed. HTTP Code: $httpCode");
            if ($response) {
                error_log("Response: " . $response);
                $result = json_decode($response, true);
                if ($result && isset($result['message'])) {
                    error_log("Error message: " . $result['message']);
                }
            }
        }
        
        $this->token = null;
        return false;
    }

    public function sendSMS($phone, $message, $from = '4546') {
        if (!$this->token) {
            if (!$this->authenticate()) {
                return ['success' => false, 'message' => 'Authentication failed'];
            }
        }

        $url = ESKIZ_API_URL . '/message/sms/send';
        
        $data = [
            'mobile_phone' => $phone,
            'message' => $message,
            'from' => $from
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log response for debugging
        error_log("Eskiz SMS API Response - HTTP Code: $httpCode, Response: $response");
        
        if ($curlError) {
            error_log("CURL Error: $curlError");
            return ['success' => false, 'message' => 'CURL Error: ' . $curlError, 'response' => $response];
        }

        // Parse response
        $result = json_decode($response, true);

        // Check for errors first (400, 401, etc.)
        if ($httpCode !== 200) {
            $errorMsg = 'Failed to send SMS. HTTP Code: ' . $httpCode;
            if ($result && isset($result['message'])) {
                $errorMsg = $result['message'];
            } elseif ($result && isset($result['status']) && $result['status'] === 'error') {
                $errorMsg = $result['message'] ?? 'Unknown error';
            }
            return ['success' => false, 'message' => $errorMsg, 'response' => $response, 'http_code' => $httpCode];
        }

        // HTTP 200 - check response format
        if ($httpCode === 200) {
            // Check for error in response (even with HTTP 200)
            if (isset($result['status']) && $result['status'] === 'error') {
                $errorMsg = $result['message'] ?? 'Unknown error';
                return ['success' => false, 'message' => $errorMsg, 'response' => $response];
            }
            
            // Check for success status
            if (isset($result['status']) && $result['status'] === 'success') {
                return ['success' => true, 'message' => 'SMS sent successfully', 'data' => $result];
            }
            
            // Check if message was sent (some APIs return id or message_id on success)
            if (isset($result['id']) && (!isset($result['status']) || $result['status'] !== 'error')) {
                return ['success' => true, 'message' => 'SMS sent successfully', 'data' => $result];
            }
            
            // Check for error messages in response
            if (isset($result['message']) && (stripos($result['message'], 'error') !== false || stripos($result['message'], 'тест') !== false)) {
                return ['success' => false, 'message' => $result['message'], 'response' => $response];
            }
        }

        return ['success' => false, 'message' => 'Failed to send SMS. HTTP Code: ' . $httpCode, 'response' => $response];
    }
}

