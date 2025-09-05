<?php
// Nový endpoint pro tisk objednávek
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'send-to-printer') {
    $order_id = $_POST['order_id'] ?? 0;
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Chybí ID objednávky']);
        exit;
    }
    
    // Získání detailů objednávky
    $stmt = $pdo->prepare("
        SELECT o.*, oi.item_name, oi.quantity, oi.unit_price, oi.note 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.id = ? AND oi.item_type IN ('pizza', 'pasta', 'predkrm')
        ORDER BY oi.id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Objednávka neobsahuje kuchyňské položky']);
        exit;
    }
    
    // Příprava dat pro tisk
    $order = $items[0]; // základní info o objednávce
    
    // Sanitize and trim customer_name with optional whitespace collapse
    $customer_name = '';
    if (!empty($order['customer_name'])) {
        $customer_name = preg_replace('/\s+/u', ' ', trim($order['customer_name']));
    }
    
    $print_data = [
        'order_id' => $order['id'],
        'table_code' => $order['table_code'] ?? $order['table_number'],
        'employee_name' => $order['employee_name'],
        'customer_name' => $customer_name,
        'created_at' => $order['created_at'],
        'items' => []
    ];
    
    foreach ($items as $item) {
        $print_data['items'][] = [
            'name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'note' => $item['note'] ?: ''
        ];
    }
    
    // Odeslání na RPi3 tiskárnu
    $rpi_url = 'http://192.168.30.203:5000/print-order'; // IP vašeho RPi3
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rpi_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($print_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        // Označit objednávku jako vytištěnou
        $stmt = $pdo->prepare("UPDATE orders SET print_status = 'printed', printed_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);
        
        echo json_encode(['success' => true, 'message' => 'Objednávka odeslána na tiskárnu']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při komunikaci s tiskárnou']);
    }
}
?>
