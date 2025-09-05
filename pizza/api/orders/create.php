<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neautorizovaný přístup']);
    exit;
}

require_once __DIR__ . '/../../../includes/dough_auto.php';

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    
    if (!is_array($payload)) {
        throw new Exception('Neplatný JSON');
    }
    
    $date = date('Y-m-d');
    $pdo = getPizzaOrdersDb();
    
    // Start transaction only if not already active
    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    // Extract order data
    $tableSessionId = (int)($payload['table_session_id'] ?? 0);
    if ($tableSessionId <= 0) {
        $tableSessionId = time();
    }

    $orderType = 'pizza';
    $employee = $payload['employee_name'] ?? null;
    $customer = $payload['customer_name'] ?? null;
    $tableNumber = (int)($payload['table'] ?? 0);

    // Create order record
    $stmt = $pdo->prepare("
        INSERT INTO orders (table_session_id, status, order_type, created_at, employee_name, customer_name, is_reserved) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tableSessionId, 'pending', $orderType, date('Y-m-d H:i:s'), $employee, $customer, 0]);
    $orderId = $pdo->lastInsertId();

    // Process order items
    $pizzaCount = 0;
    
    // Cache schema check for defensive insert (prevents runtime errors if migration not yet applied)
    static $orderItemsColumns = null;
    if ($orderItemsColumns === null) {
        try {
            $orderItemsColumns = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // If table doesn't exist, use minimal column set and let migration handle full schema
            $orderItemsColumns = ['order_id', 'item_type', 'item_name', 'unit_price', 'quantity', 'note', 'status'];
        }
    }
    
    foreach ($payload['items'] ?? [] as $item) {
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;
        
        $type = $item['type'] ?? 'pizza';
        if ($type === 'pizza') {
            $pizzaCount += $qty;
        }

        // Build defensive INSERT query based on available columns
        $columns = ['order_id', 'item_type', 'item_name', 'unit_price', 'quantity', 'note', 'status'];
        $values = ['?', '?', '?', '?', '?', '?', "'pending'"];
        $params = [
            $orderId,
            $type,
            $item['name'] ?? '',
            $item['price'] ?? $item['unit_price'] ?? 0,
            $qty,
            $item['note'] ?? ''
        ];
        
        // Add timestamp columns only if they exist (defensive against missing migration)
        if (in_array('created_at', $orderItemsColumns)) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }
        if (in_array('updated_at', $orderItemsColumns)) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }
        
        $sql = "INSERT INTO order_items (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // Commit transaction if we started it
    if ($startedTransaction && $pdo->inTransaction()) {
        $pdo->commit();
    }

    // Update daily pizza consumption
    if ($pizzaCount > 0) {
        incrementPizzaUsed($date, $pizzaCount, 'ORDER');
    }

    // Try to print - wrapped to prevent order failure if printer is offline
    $printerUnavailable = false;
    try {
        // Call existing print functionality if it exists
        // printOrder($orderId); // Uncomment if this function exists
    } catch (Throwable $printError) {
        $printerUnavailable = true;
        error_log('[PRINT ERROR] Order ' . $orderId . ': ' . $printError->getMessage());
    }

    echo json_encode([
        'ok' => true,
        'order_id' => $orderId,
        'pizza_count' => $pizzaCount,
        'printer_unavailable' => $printerUnavailable,
        'data' => ['order_id' => $orderId]  // For compatibility with existing frontend
    ]);

} catch (Throwable $e) {
    // Rollback only if we started the transaction and it's still active
    if (isset($pdo) && $pdo instanceof PDO && isset($startedTransaction) && $startedTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}