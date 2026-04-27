<?php
function handleDashboard() {
    global $conn;
    
    // Total houses
    $totalHouses = $conn->query("SELECT COUNT(*) as count FROM houses")->fetch_assoc()['count'];
    
    // Occupied houses
    $occupiedHouses = $conn->query("SELECT COUNT(*) as count FROM tenants WHERE status = 1")->fetch_assoc()['count'];
    
    // Available houses
    $availableHouses = $totalHouses - $occupiedHouses;
    
    // Total tenants
    $totalTenants = $occupiedHouses;
    
    // Monthly payments (current month)
    $currentMonth = date('Y-m');
    $monthlyPayments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE DATE_FORMAT(date_created, '%Y-%m') = '$currentMonth'")->fetch_assoc();
    
    // Total payments all time
    $totalPayments = $conn->query("SELECT SUM(amount) as total FROM payments")->fetch_assoc();
    
    // Recent payments (last 5)
    $recentPayments = $conn->query("SELECT p.*, CONCAT(t.lastname, ', ', t.firstname) as tenant_name, h.house_no 
                                    FROM payments p 
                                    INNER JOIN tenants t ON t.id = p.tenant_id 
                                    INNER JOIN houses h ON h.id = t.house_id 
                                    ORDER BY p.date_created DESC 
                                    LIMIT 5");
    
    $recent = [];
    while ($row = $recentPayments->fetch_assoc()) {
        $recent[] = $row;
    }
    
    // Monthly payment trend (last 6 months)
    $trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        $amount = $conn->query("SELECT SUM(amount) as total FROM payments WHERE DATE_FORMAT(date_created, '%Y-%m') = '$month'")->fetch_assoc();
        $trend[] = [
            'month' => $monthName,
            'amount' => (float)($amount['total'] ?? 0)
        ];
    }
    
    // Category distribution
    $categoryDist = $conn->query("SELECT c.name, COUNT(h.id) as house_count 
                                  FROM categories c 
                                  LEFT JOIN houses h ON h.category_id = c.id 
                                  GROUP BY c.id");
    $categories = [];
    while ($row = $categoryDist->fetch_assoc()) {
        $categories[] = $row;
    }
    
    sendResponse([
        'summary' => [
            'total_houses' => (int)$totalHouses,
            'occupied_houses' => (int)$occupiedHouses,
            'available_houses' => (int)$availableHouses,
            'occupancy_rate' => $totalHouses > 0 ? round(($occupiedHouses / $totalHouses) * 100, 2) : 0,
            'total_tenants' => (int)$totalTenants,
            'monthly_payments' => (float)($monthlyPayments['total'] ?? 0),
            'total_payments_all_time' => (float)($totalPayments['total'] ?? 0)
        ],
        'recent_payments' => $recent,
        'monthly_trend' => $trend,
        'category_distribution' => $categories,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}