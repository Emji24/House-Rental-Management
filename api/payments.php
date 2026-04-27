<?php
function handlePayments($method, $id, $subResource) {
    global $conn;
    
    switch ($method) {
        case 'GET':
            if ($subResource === 'tenant' && $id) {
                // Get payments for specific tenant
                $tenantId = $id;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = ($page - 1) * $limit;
                
                $query = $conn->query("SELECT p.*, t.firstname, t.lastname, h.house_no 
                                       FROM payments p 
                                       INNER JOIN tenants t ON t.id = p.tenant_id 
                                       INNER JOIN houses h ON h.id = t.house_id 
                                       WHERE p.tenant_id = $tenantId 
                                       ORDER BY p.date_created DESC 
                                       LIMIT $limit OFFSET $offset");
                
                $totalQuery = $conn->query("SELECT COUNT(*) as total FROM payments WHERE tenant_id = $tenantId");
                $total = $totalQuery->fetch_assoc()['total'];
                
                $payments = [];
                while ($row = $query->fetch_assoc()) {
                    $payments[] = $row;
                }
                
                sendResponse([
                    'data' => $payments,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            } elseif ($id) {
                // Get single payment
                $query = $conn->query("SELECT p.*, t.firstname, t.lastname, t.email, h.house_no 
                                       FROM payments p 
                                       INNER JOIN tenants t ON t.id = p.tenant_id 
                                       INNER JOIN houses h ON h.id = t.house_id 
                                       WHERE p.id = $id");
                if ($query->num_rows > 0) {
                    sendResponse($query->fetch_assoc());
                } else {
                    sendResponse(['error' => 'Payment not found'], 404);
                }
            } else {
                // Get all payments with filters
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = ($page - 1) * $limit;
                $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
                $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
                $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
                
                $where = "1=1";
                if ($startDate) $where .= " AND DATE(p.date_created) >= '$startDate'";
                if ($endDate) $where .= " AND DATE(p.date_created) <= '$endDate'";
                if ($tenantId) $where .= " AND p.tenant_id = $tenantId";
                
                $query = $conn->query("SELECT p.*, t.firstname, t.lastname, t.email, h.house_no 
                                       FROM payments p 
                                       INNER JOIN tenants t ON t.id = p.tenant_id 
                                       INNER JOIN houses h ON h.id = t.house_id 
                                       WHERE $where 
                                       ORDER BY p.date_created DESC 
                                       LIMIT $limit OFFSET $offset");
                
                $totalQuery = $conn->query("SELECT COUNT(*) as total FROM payments p WHERE $where");
                $total = $totalQuery->fetch_assoc()['total'];
                
                $payments = [];
                while ($row = $query->fetch_assoc()) {
                    $payments[] = $row;
                }
                
                sendResponse([
                    'data' => $payments,
                    'total_amount' => array_sum(array_column($payments, 'amount')),
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
            
            $tenant_id = (int)($data['tenant_id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $invoice = $conn->real_escape_string($data['invoice'] ?? 'INV-' . date('Ymd') . '-' . rand(1000, 9999));
            
            // Verify tenant exists
            $checkTenant = $conn->query("SELECT id FROM tenants WHERE id = $tenant_id AND status = 1");
            if ($checkTenant->num_rows == 0) {
                sendResponse(['error' => 'Invalid tenant'], 400);
            }
            
            $conn->query("INSERT INTO payments SET 
                         tenant_id='$tenant_id', 
                         amount='$amount', 
                         invoice='$invoice', 
                         date_created=NOW()");
            
            if ($conn->affected_rows > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Payment recorded successfully',
                    'id' => $conn->insert_id
                ], 201);
            } else {
                sendResponse(['error' => 'Failed to record payment'], 500);
            }
            break;
            
        case 'PUT':
            if (!$id) sendResponse(['error' => 'Payment ID required'], 400);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $updateFields = [];
            
            if (isset($data['amount'])) $updateFields[] = "amount='{$data['amount']}'";
            if (isset($data['invoice'])) $updateFields[] = "invoice='{$conn->real_escape_string($data['invoice'])}'";
            
            if (empty($updateFields)) {
                sendResponse(['error' => 'No fields to update'], 400);
            }
            
            $conn->query("UPDATE payments SET " . implode(', ', $updateFields) . " WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Payment updated successfully']);
            break;
            
        case 'DELETE':
            if (!$id) sendResponse(['error' => 'Payment ID required'], 400);
            
            $conn->query("DELETE FROM payments WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Payment deleted successfully']);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}