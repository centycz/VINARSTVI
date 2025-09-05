<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getDb() {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

function getOrderHistory($params = []) {
    $db = getDb();
    if (is_array($db) && isset($db['status']) && $db['status'] === 'error') {
        return $db;
    }
    $pdo = $db;

    try {
        $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $params['date_to'] ?? date('Y-m-d');
        $tableNumber = $params['table_number'] ?? null;
        $employeeName = $params['employee_name'] ?? null;

        // Základní SQL pro objednávky
        $sql = "
            SELECT DISTINCT
                o.id,
                o.created_at,
                o.customer_name,
                o.employee_name,
                ts.table_number,
                rt.table_code
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            LEFT JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            AND EXISTS (
                SELECT 1 FROM order_items oi 
                WHERE oi.order_id = o.id 
                AND oi.status = 'paid'
            )
        ";
        
        $params_array = [$dateFrom, $dateTo];
        
        // Filtry
        if ($tableNumber) {
            $sql .= " AND ts.table_number = ?";
            $params_array[] = $tableNumber;
        }
        
        if ($employeeName) {
            $sql .= " AND o.employee_name = ?";
            $params_array[] = $employeeName;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_array);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pro každou objednávku naèteme položky
        foreach ($orders as &$order) {
            $itemsSql = "
                SELECT 
                    oi.item_name,
                    oi.quantity,
                    oi.unit_price,
                    oi.note,
                    oi.item_type
                FROM order_items oi
                WHERE oi.order_id = ?
                AND oi.status = 'paid'
                ORDER BY oi.id
            ";
            
            $itemsStmt = $pdo->prepare($itemsSql);
            $itemsStmt->execute([$order['id']]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'success' => true,
            'data' => [
                'orders' => $orders
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Historie API Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getEmployeesList() {
    $db = getDb();
    if (is_array($db) && isset($db['status']) && $db['status'] === 'error') {
        return $db;
    }
    $pdo = $db;

    try {
        $sql = "
            SELECT 
                o.employee_name as name,
                COUNT(DISTINCT o.id) as order_count
            FROM orders o
            WHERE o.employee_name IS NOT NULL 
            AND o.employee_name != ''
            AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY o.employee_name
            ORDER BY order_count DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => ['employees' => $employees]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Hlavní logika
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'order-history':
            $result = getOrderHistory($_GET);
            break;
            
        case 'employees-list':
            $result = getEmployeesList();
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Invalid action'];
    }
    
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?>