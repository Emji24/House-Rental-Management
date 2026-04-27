<?php
function handleCategories($method, $id) {
    global $conn;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $query = $conn->query("SELECT * FROM categories WHERE id = $id");
                if ($query->num_rows > 0) {
                    sendResponse($query->fetch_assoc());
                } else {
                    sendResponse(['error' => 'Category not found'], 404);
                }
            } else {
                $query = $conn->query("SELECT * FROM categories ORDER BY name ASC");
                $categories = [];
                while ($row = $query->fetch_assoc()) {
                    // Count houses in this category
                    $houseCount = $conn->query("SELECT COUNT(*) as count FROM houses WHERE category_id = {$row['id']}")->fetch_assoc();
                    $row['house_count'] = $houseCount['count'];
                    $categories[] = $row;
                }
                sendResponse($categories);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $conn->real_escape_string($data['name'] ?? '');
            
            if (empty($name)) {
                sendResponse(['error' => 'Category name is required'], 400);
            }
            
            $conn->query("INSERT INTO categories SET name='$name'");
            sendResponse([
                'success' => true,
                'message' => 'Category created successfully',
                'id' => $conn->insert_id
            ], 201);
            break;
            
        case 'PUT':
            if (!$id) sendResponse(['error' => 'Category ID required'], 400);
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $conn->real_escape_string($data['name'] ?? '');
            
            $conn->query("UPDATE categories SET name='$name' WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Category updated successfully']);
            break;
            
        case 'DELETE':
            if (!$id) sendResponse(['error' => 'Category ID required'], 400);
            
            // Check if category has houses
            $checkHouses = $conn->query("SELECT id FROM houses WHERE category_id = $id");
            if ($checkHouses->num_rows > 0) {
                sendResponse(['error' => 'Cannot delete category with associated houses'], 400);
            }
            
            $conn->query("DELETE FROM categories WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Category deleted successfully']);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}