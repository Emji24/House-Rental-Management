<?php
function handleHouses($method, $id) {
    global $conn;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single house
                $query = $conn->query("SELECT h.*, c.name as category_name FROM houses h 
                                       LEFT JOIN categories c ON c.id = h.category_id 
                                       WHERE h.id = $id");
                if ($query->num_rows > 0) {
                    sendResponse($query->fetch_assoc());
                } else {
                    sendResponse(['error' => 'House not found'], 404);
                }
            } else {
                // Get all houses
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = ($page - 1) * $limit;
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                
                $where = "";
                if ($search) {
                    $where = "WHERE h.house_no LIKE '%$search%' OR h.description LIKE '%$search%'";
                }
                
                $totalQuery = $conn->query("SELECT COUNT(*) as total FROM houses h $where");
                $total = $totalQuery->fetch_assoc()['total'];
                
                $query = $conn->query("SELECT h.*, c.name as category_name FROM houses h 
                                       LEFT JOIN categories c ON c.id = h.category_id 
                                       $where 
                                       ORDER BY h.id DESC 
                                       LIMIT $limit OFFSET $offset");
                
                $houses = [];
                while ($row = $query->fetch_assoc()) {
                    // Check if house is occupied
                    $checkTenant = $conn->query("SELECT id FROM tenants WHERE house_id = {$row['id']} AND status = 1");
                    $row['is_occupied'] = $checkTenant->num_rows > 0;
                    $houses[] = $row;
                }
                
                sendResponse([
                    'data' => $houses,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $house_no = $conn->real_escape_string($data['house_no'] ?? '');
            $description = $conn->real_escape_string($data['description'] ?? '');
            $category_id = (int)($data['category_id'] ?? 0);
            $price = (float)($data['price'] ?? 0);
            
            // Check for duplicate house number
            $check = $conn->query("SELECT id FROM houses WHERE house_no = '$house_no'");
            if ($check->num_rows > 0) {
                sendResponse(['error' => 'House number already exists'], 400);
            }
            
            $conn->query("INSERT INTO houses SET 
                         house_no='$house_no', 
                         description='$description', 
                         category_id='$category_id', 
                         price='$price'");
            
            if ($conn->affected_rows > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'House created successfully',
                    'id' => $conn->insert_id
                ], 201);
            } else {
                sendResponse(['error' => 'Failed to create house'], 500);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                sendResponse(['error' => 'House ID required'], 400);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updateFields = [];
            if (isset($data['house_no'])) $updateFields[] = "house_no='{$conn->real_escape_string($data['house_no'])}'";
            if (isset($data['description'])) $updateFields[] = "description='{$conn->real_escape_string($data['description'])}'";
            if (isset($data['category_id'])) $updateFields[] = "category_id='{$data['category_id']}'";
            if (isset($data['price'])) $updateFields[] = "price='{$data['price']}'";
            
            if (empty($updateFields)) {
                sendResponse(['error' => 'No fields to update'], 400);
            }
            
            $conn->query("UPDATE houses SET " . implode(', ', $updateFields) . " WHERE id = $id");
            
            if ($conn->affected_rows >= 0) {
                sendResponse(['success' => true, 'message' => 'House updated successfully']);
            } else {
                sendResponse(['error' => 'Failed to update house'], 500);
            }
            break;
            
        case 'DELETE':
            if (!$id) {
                sendResponse(['error' => 'House ID required'], 400);
            }
            
            // Check if house has active tenants
            $checkTenant = $conn->query("SELECT id FROM tenants WHERE house_id = $id AND status = 1");
            if ($checkTenant->num_rows > 0) {
                sendResponse(['error' => 'Cannot delete house with active tenants'], 400);
            }
            
            $conn->query("DELETE FROM houses WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'House deleted successfully']);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}