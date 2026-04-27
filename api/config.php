<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db_connect.php';

// API Key for authentication (store in environment variables in production)
define('API_KEY', 'your-secret-api-key-here-change-this');
define('JWT_SECRET', 'your-jwt-secret-key-change-this');

// Simple API Key validation
function validateApiKey() {
    $headers = getallheaders();
    $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API Key']);
        exit();
    }
    return true;
}

// JWT Token generation
function generateJWT($userId, $userType) {
    $payload = [
        'user_id' => $userId,
        'user_type' => $userType,
        'exp' => time() + 86400 // 24 hours expiration
    ];
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payloadEncoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true);
    $signatureEncoded = base64_encode($signature);
    return "$header.$payloadEncoded.$signatureEncoded";
}

// JWT Token validation
function validateJWT() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit();
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token format']);
        exit();
    }
    
    $signature = base64_encode(hash_hmac('sha256', "$parts[0].$parts[1]", JWT_SECRET, true));
    if ($signature !== $parts[2]) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token signature']);
        exit();
    }
    
    $payload = json_decode(base64_decode($parts[1]), true);
    if ($payload['exp'] < time()) {
        http_response_code(401);
        echo json_encode(['error' => 'Token expired']);
        exit();
    }
    
    return $payload;
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}