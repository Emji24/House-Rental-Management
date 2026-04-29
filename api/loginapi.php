<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        "success" => false,
        "error" => "Username and password required"
    ]);
    exit();
}

// Query using MD5 hashing (matching your table)
$query = "SELECT id, name, username, type FROM users 
          WHERE username = ? AND password = MD5(?)";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "user" => $user,
        "message" => "Login successful"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Invalid username or password"
    ]);
}

$stmt->close();
$conn->close();
?>