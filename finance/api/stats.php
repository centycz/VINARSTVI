<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    jsonResponse(false, null, 'User not authenticated');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    jsonResponse(false, null, 'Method not allowed');
}

$db = getFinanceDb();

try {
    // Calculate total balance
    $stmt = $db->prepare("
        SELECT 
            type,
            SUM(amount) as total
        FROM transactions 
        GROUP BY type
    ");
    $stmt->execute();
    $totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $totalIncome = isset($totals['income']) ? $totals['income'] : 0;
    $totalExpenses = isset($totals['expense']) ? $totals['expense'] : 0;
    $balance = $totalIncome - $totalExpenses;
    
    // Monthly statistics for current year - OPRAVENO pro MySQL
    $currentYear = date('Y');
    $stmt = $db->prepare("
        SELECT 
            MONTH(date) as month,
            type,
            SUM(amount) as total
        FROM transactions 
        WHERE YEAR(date) = ?
        GROUP BY MONTH(date), type
        ORDER BY month
    ");
    $stmt->execute([$currentYear]);
    $monthlyData = $stmt->fetchAll();
    
    // Process monthly data
    $monthlyStats = [];
    for ($i = 1; $i <= 12; $i++) {
        $monthlyStats[$i] = [
            'month' => sprintf('%02d', $i),
            'monthName' => date('F', mktime(0, 0, 0, $i, 1)),
            'income' => 0,
            'expenses' => 0,
            'balance' => 0
        ];
    }
    
    foreach ($monthlyData as $row) {
        $month = (int)$row['month'];
        if ($row['type'] === 'income') {
            $monthlyStats[$month]['income'] = $row['total'];
        } else {
            $monthlyStats[$month]['expenses'] = $row['total'];
        }
        $monthlyStats[$month]['balance'] = $monthlyStats[$month]['income'] - $monthlyStats[$month]['expenses'];
    }
    
    // Category statistics
    $stmt = $db->prepare("
        SELECT 
            category,
            type,
            SUM(amount) as total,
            COUNT(*) as count
        FROM transactions 
        GROUP BY category, type
        ORDER BY total DESC
    ");
    $stmt->execute();
    $categoryData = $stmt->fetchAll();
    
    // Process category data
    $categoryStats = [
        'income' => [],
        'expense' => []
    ];
    
    foreach ($categoryData as $row) {
        $categoryStats[$row['type']][] = [
            'category' => $row['category'],
            'total' => $row['total'],
            'count' => $row['count']
        ];
    }
    
    // Recent transactions summary - OPRAVENO pro MySQL
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM transactions 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $recentTransactions = $stmt->fetchColumn();
    
    // Transaction count by type
    $stmt = $db->prepare("
        SELECT 
            type,
            COUNT(*) as count
        FROM transactions 
        GROUP BY type
    ");
    $stmt->execute();
    $transactionCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $response = [
        'overview' => [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'balance' => $balance,
            'incomeTransactions' => isset($transactionCounts['income']) ? $transactionCounts['income'] : 0,
            'expenseTransactions' => isset($transactionCounts['expense']) ? $transactionCounts['expense'] : 0,
            'recentTransactions' => $recentTransactions
        ],
        'monthly' => array_values($monthlyStats),
        'categories' => $categoryStats,
        'currentYear' => $currentYear
    ];
    
    jsonResponse(true, $response);
} catch (Exception $e) {
    jsonResponse(false, null, 'Error fetching statistics: ' . $e->getMessage());
}
?>