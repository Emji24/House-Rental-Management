<?php
function handleTenants($method, $id) {
    global $conn;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $query = $conn->query("SELECT t.*, h.house_no, h.price as monthly_rate 
                                       FROM tenants t 
                                       INNER JOIN houses h ON h.id = t.house_id 
                                       WHERE t.id = $id AND t.status = 1");
                if ($query->num_rows > 0) {
                    $tenant = $query->fetch_assoc();
                    
                    // Calculate financial details
                    $months = floor(abs(strtotime(date('Y-m-d')) - strtotime($tenant['date_in'])) / (30*60*60*24));
                    $payable = $tenant['price'] * $months;
                    
                    $paidQuery = $conn->query("SELECT SUM(amount) as paid FROM payments WHERE tenant_id = $id");
                    $paid = $paidQuery->fetch_assoc()['paid'] ?? 0;
                    
                    $tenant['payable_months'] = $months;
                    $tenant['total_payable'] = $payable;
                    $tenant['total_paid'] = $paid;
                    $tenant['outstanding_balance'] = $payable - $paid;
                    
                    sendResponse($tenant);
                } else {
                    sendResponse(['error' => 'Tenant not found'], 404);
                }
            } else {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = ($page - 1) * $limit;
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                $house_id = isset($_GET['house_id']) ? (int)$_GET['house_id'] : null;
                
                $where = "t.status = 1";
                if ($search) {
                    $where .= " AND (t.firstname LIKE '%$search%' OR t.lastname LIKE '%$search%' OR t.email LIKE '%$search%')";
                }
                if ($house_id) {
                    $where .= " AND t.house_id = $house_id";
                }
                
                $totalQuery = $conn->query("SELECT COUNT(*) as total FROM tenants t WHERE $where");
                $total = $totalQuery->fetch_assoc()['total'];
                
                $query = $conn->query("SELECT t.*, h.house_no, h.price as monthly_rate,
                                      CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as full_name
                                      FROM tenants t 
                                      INNER JOIN houses h ON h.id = t.house_id 
                                      WHERE $where 
                                      ORDER BY t.id DESC 
                                      LIMIT $limit OFFSET $offset");
                
                $tenants = [];
                while ($row = $query->fetch_assoc()) {
                    $months = floor(abs(strtotime(date('Y-m-d')) - strtotime($row['date_in'])) / (30*60*60*24));
                    $payable = $row['price'] * $months;
                    $paidQuery = $conn->query("SELECT SUM(amount) as paid FROM payments WHERE tenant_id = {$row['id']}");
                    $paid = $paidQuery->fetch_assoc()['paid'] ?? 0;
                    $row['outstanding_balance'] = $payable - $paid;
                    $tenants[] = $row;
                }
                
                sendResponse([
                    'data' => $tenants,
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
            
            $firstname = $conn->real_escape_string($data['firstname'] ?? '');
            $lastname = $conn->real_escape_string($data['lastname'] ?? '');
            $middlename = $conn->real_escape_string($data['middlename'] ?? '');
            $email = $conn->real_escape_string($data['email'] ?? '');
            $contact = $conn->real_escape_string($data['contact'] ?? '');
            $house_id = (int)($data['house_id'] ?? 0);
            $date_in = $conn->real_escape_string($data['date_in'] ?? date('Y-m-d'));
            
            // Check if house is available
            $checkHouse = $conn->query("SELECT id FROM houses WHERE id = $house_id");
            if ($checkHouse->num_rows == 0) {
                sendResponse(['error' => 'Invalid house'], 400);
            }
            
            $checkOccupied = $conn->query("SELECT id FROM tenants WHERE house_id = $house_id AND status = 1");
            if ($checkOccupied->num_rows > 0) {
                sendResponse(['error' => 'House is already occupied'], 400);
            }
            
            $conn->query("INSERT INTO tenants SET 
                         firstname='$firstname', 
                         lastname='$lastname', 
                         middlename='$middlename', 
                         email='$email', 
                         contact='$contact', 
                         house_id='$house_id', 
                         date_in='$date_in', 
                         status=1");
            
            if ($conn->affected_rows > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Tenant added successfully',
                    'id' => $conn->insert_id
                ], 201);
            } else {
                sendResponse(['error' => 'Failed to add tenant'], 500);
            }
            break;
            
        case 'PUT':
            if (!$id) sendResponse(['error' => 'Tenant ID required'], 400);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $updateFields = [];
            
            if (isset($data['firstname'])) $updateFields[] = "firstname='{$conn->real_escape_string($data['firstname'])}'";
            if (isset($data['lastname'])) $updateFields[] = "lastname='{$conn->real_escape_string($data['lastname'])}'";
            if (isset($data['middlename'])) $updateFields[] = "middlename='{$conn->real_escape_string($data['middlename'])}'";
            if (isset($data['email'])) $updateFields[] = "email='{$conn->real_escape_string($data['email'])}'";
            if (isset($data['contact'])) $updateFields[] = "contact='{$conn->real_escape_string($data['contact'])}'";
            if (isset($data['date_in'])) $updateFields[] = "date_in='{$conn->real_escape_string($data['date_in'])}'";
            
            if (isset($data['house_id'])) {
                $newHouseId = (int)$data['house_id'];
                $checkOccupied = $conn->query("SELECT id FROM tenants WHERE house_id = $newHouseId AND status = 1 AND id != $id");
                if ($checkOccupied->num_rows > 0) {
                    sendResponse(['error' => 'Selected house is already occupied'], 400);
                }
                $updateFields[] = "house_id='$newHouseId'";
            }
            
            if (empty($updateFields)) {
                sendResponse(['error' => 'No fields to update'], 400);
            }
            
            $conn->query("UPDATE tenants SET " . implode(', ', $updateFields) . " WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Tenant updated successfully']);
            break;
            
        case 'DELETE':
            if (!$id) sendResponse(['error' => 'Tenant ID required'], 400);
            
            // Soft delete - just mark as inactive
            $conn->query("UPDATE tenants SET status = 0 WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Tenant deleted successfully']);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}