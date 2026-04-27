<?php
function handleUsers($method, $id) {
    global $conn;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $query = $conn->query("SELECT id, name, username, type, alumnus_id FROM users WHERE id = $id");
                if ($query->num_rows > 0) {
                    $user = $query->fetch_assoc();
                    $user['type_label'] = getUserTypeLabel($user['type']);
                    sendResponse($user);
                } else {
                    sendResponse(['error' => 'User not found'], 404);
                }
            } else {
                $query = $conn->query("SELECT id, name, username, type, alumnus_id FROM users ORDER BY name ASC");
                $users = [];
                while ($row = $query->fetch_assoc()) {
                    $row['type_label'] = getUserTypeLabel($row['type']);
                    $users[] = $row;
                }
                sendResponse($users);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $name = $conn->real_escape_string($data['name'] ?? '');
            $username = $conn->real_escape_string($data['username'] ?? '');
            $password = md5($data['password'] ?? '');
            $type = (int)($data['type'] ?? 2);
            
            // Validate
            if (empty($name) || empty($username) || empty($data['password'])) {
                sendResponse(['error' => 'Name, username, and password are required'], 400);
            }
            
            $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
            if ($check->num_rows > 0) {
                sendResponse(['error' => 'Username already exists'], 400);
            }
            
            $conn->query("INSERT INTO users SET name='$name', username='$username', password='$password', type='$type'");
            
            if ($conn->affected_rows > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'User created successfully',
                    'id' => $conn->insert_id
                ], 201);
            } else {
                sendResponse(['error' => 'Failed to create user'], 500);
            }
            break;
            
        case 'PUT':
            if (!$id) sendResponse(['error' => 'User ID required'], 400);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $updateFields = [];
            
            if (isset($data['name'])) $updateFields[] = "name='{$conn->real_escape_string($data['name'])}'";
            if (isset($data['username'])) $updateFields[] = "username='{$conn->real_escape_string($data['username'])}'";
            if (isset($data['type'])) $updateFields[] = "type='{$data['type']}'";
            if (isset($data['password']) && !empty($data['password'])) {
                $updateFields[] = "password='" . md5($data['password']) . "'";
            }
            
            if (empty($updateFields)) {
                sendResponse(['error' => 'No fields to update'], 400);
            }
            
            $conn->query("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'User updated successfully']);
            break;
            
        case 'DELETE':
            if (!$id) sendResponse(['error' => 'User ID required'], 400);
            
            // Don't allow deleting own account
            $headers = getallheaders();
            $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
            // Additional check would be needed for self-deletion prevention
            
            $conn->query("DELETE FROM users WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'User deleted successfully']);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

function getUserTypeLabel($type) {
    $labels = ['', 'Admin', 'Staff', 'Alumnus/Alumna'];
    return $labels[$type] ?? 'Unknown';
}