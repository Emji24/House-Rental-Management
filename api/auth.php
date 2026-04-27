<?php
function handleAuth($method, $action) {
    global $conn;
    
    if ($method === 'POST' && $action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'] ?? '';
        $password = md5($data['password'] ?? '');
        
        $query = $conn->query("SELECT * FROM users WHERE username = '$username' AND password = '$password'");
        
        if ($query->num_rows > 0) {
            $user = $query->fetch_assoc();
            $token = generateJWT($user['id'], $user['type']);
            
            sendResponse([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'type' => $user['type']
                ]
            ]);
        } else {
            sendResponse(['error' => 'Invalid credentials'], 401);
        }
    }
    
    if ($method === 'POST' && $action === 'register') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $name = $conn->real_escape_string($data['name'] ?? '');
        $username = $conn->real_escape_string($data['username'] ?? '');
        $password = md5($data['password'] ?? '');
        $type = 3; // Regular user/alumni type
        
        $check = $conn->query("SELECT * FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            sendResponse(['error' => 'Username already exists'], 400);
        }
        
        $conn->query("INSERT INTO users SET name='$name', username='$username', password='$password', type='$type'");
        
        if ($conn->affected_rows > 0) {
            $userId = $conn->insert_id;
            $token = generateJWT($userId, $type);
            
            sendResponse([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'username' => $username,
                    'type' => $type
                ]
            ], 201);
        } else {
            sendResponse(['error' => 'Registration failed'], 500);
        }
    }
    
    sendResponse(['error' => 'Invalid auth endpoint'], 404);
}