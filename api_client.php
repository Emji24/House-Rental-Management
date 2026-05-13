<?php
// api_client.php
// PHP helper for calling the Node.js House Rental API instead of using direct MySQL queries.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('API_BASE_URL', 'http://localhost:3000/api');

function api_request($method, $endpoint, $data = null, $useToken = true) {
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];

    if ($useToken && !empty($_SESSION['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['api_token'];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'status' => 0,
            'error' => $curlError,
            'data' => null
        ];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        $json = ['raw' => $body];
    }

    $json['status'] = $statusCode;
    return $json;
}

function api_get_data($endpoint, $default = []) {
    $response = api_request('GET', $endpoint);
    if (!empty($response['success']) && isset($response['data'])) {
        return $response['data'];
    }
    return $default;
}

function api_get($endpoint) {
    return api_request('GET', $endpoint, null, false);
}

function api_post($endpoint, $data = [], $useToken = true) {
    return api_request('POST', $endpoint, $data, $useToken);
}

function api_put($endpoint, $data = [], $useToken = true) {
    return api_request('PUT', $endpoint, $data, $useToken);
}

function api_delete($endpoint, $useToken = true) {
    return api_request('DELETE', $endpoint, null, $useToken);
}
?>
