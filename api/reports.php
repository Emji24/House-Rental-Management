<?php
function handleReports($method, $type) {
    global $conn;
    
    if ($method !== 'GET') {
        sendResponse(['error' => 'Method not allowed'], 405);
    }
    
    switch ($type) {
        case 'balances':
            $tenants = $conn->query("SELECT t.*, h.house_no, h.price 
                                     FROM tenants t 
                                     INNER JOIN houses h ON h.id = t.house_id 
                                     WHERE t.status = 1 
                                     ORDER BY h.house_no ASC");
            
            $report = [];
            while ($row = $tenants->fetch_assoc()) {
                $months = floor(abs(strtotime(date('Y-m-d')) - strtotime($row['date_in'])) / (30*60*60*24));
                $payable = $row['price'] * $months;
                $paidQuery = $conn->query("SELECT SUM(amount) as paid FROM payments WHERE tenant_id = {$row['id']}");
                $paid = $paidQuery->fetch_assoc()['paid'] ?? 0;
                
                $lastPaymentQuery = $conn->query("SELECT date_created FROM payments WHERE tenant_id = {$row['id']} ORDER BY date_created DESC LIMIT 1");
                $lastPayment = $lastPaymentQuery->num_rows > 0 ? $lastPaymentQuery->fetch_assoc()['date_created'] : null;
                
                $report[] = [
                    'tenant_id' => $row['id'],
                    'tenant_name' => $row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename'],
                    'house_no' => $row['house_no'],
                    'monthly_rate' => (float)$row['price'],
                    'rent_started' => $row['date_in'],
                    'payable_months' => $months,
                    'total_payable' => (float)$payable,
                    'total_paid' => (float)$paid,
                    'outstanding_balance' => (float)($payable - $paid),
                    'last_payment_date' => $lastPayment
                ];
            }
            
            sendResponse([
                'report_type' => 'balance_report',
                'generated_at' => date('Y-m-d H:i:s'),
                'data' => $report,
                'summary' => [
                    'total_tenants' => count($report),
                    'total_outstanding' => array_sum(array_column($report, 'outstanding_balance')),
                    'total_collected' => array_sum(array_column($report, 'total_paid'))
                ]
            ]);
            break;
            
        case 'payments':
            $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
            
            $payments = $conn->query("SELECT p.*, t.firstname, t.lastname, h.house_no 
                                      FROM payments p 
                                      INNER JOIN tenants t ON t.id = p.tenant_id 
                                      INNER JOIN houses h ON h.id = t.house_id 
                                      WHERE DATE_FORMAT(p.date_created, '%Y-%m') = '$month' 
                                      ORDER BY p.date_created ASC");
            
            $report = [];
            $totalAmount = 0;
            $dailyBreakdown = [];
            
            while ($row = $payments->fetch_assoc()) {
                $date = date('Y-m-d', strtotime($row['date_created']));
                if (!isset($dailyBreakdown[$date])) {
                    $dailyBreakdown[$date] = 0;
                }
                $dailyBreakdown[$date] += $row['amount'];
                $totalAmount += $row['amount'];
                
                $report[] = [
                    'payment_id' => $row['id'],
                    'date' => $row['date_created'],
                    'tenant_name' => $row['lastname'] . ', ' . $row['firstname'],
                    'house_no' => $row['house_no'],
                    'invoice' => $row['invoice'],
                    'amount' => (float)$row['amount']
                ];
            }
            
            sendResponse([
                'report_type' => 'payment_report',
                'month' => $month,
                'generated_at' => date('Y-m-d H:i:s'),
                'data' => $report,
                'summary' => [
                    'total_payments' => count($report),
                    'total_amount' => $totalAmount,
                    'daily_breakdown' => $dailyBreakdown
                ]
            ]);
            break;
            
        case 'summary':
            // Dashboard summary
            $totalHouses = $conn->query("SELECT COUNT(*) as count FROM houses")->fetch_assoc()['count'];
            $occupiedHouses = $conn->query("SELECT COUNT(*) as count FROM tenants WHERE status = 1")->fetch_assoc()['count'];
            $availableHouses = $totalHouses - $occupiedHouses;
            
            $currentMonth = date('Y-m');
            $monthlyPayments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE DATE_FORMAT(date_created, '%Y-%m') = '$currentMonth'")->fetch_assoc();
            
            $yearToDate = $conn->query("SELECT SUM(amount) as total FROM payments WHERE YEAR(date_created) = YEAR(NOW())")->fetch_assoc();
            
            $totalCategories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
            
            sendResponse([
                'summary' => [
                    'total_houses' => (int)$totalHouses,
                    'occupied_houses' => (int)$occupiedHouses,
                    'available_houses' => (int)$availableHouses,
                    'occupancy_rate' => $totalHouses > 0 ? round(($occupiedHouses / $totalHouses) * 100, 2) : 0,
                    'total_categories' => (int)$totalCategories,
                    'total_tenants' => (int)$occupiedHouses
                ],
                'financial_summary' => [
                    'current_month_payments' => (float)($monthlyPayments['total'] ?? 0),
                    'year_to_date_payments' => (float)($yearToDate['total'] ?? 0)
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            sendResponse(['error' => 'Invalid report type. Use: balances, payments, or summary'], 400);
    }
}