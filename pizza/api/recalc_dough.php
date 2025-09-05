<?php
/**
 * Manual dough recalculation endpoint
 * POST /pizza/api/recalc_dough.php
 * Requires authentication
 */

session_start();

// Check authentication
if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Neautorizovaný přístup']);
    exit;
}

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/dough_allocation.php';
    
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Neplatný formát data');
    }
    
    $result = recalcDailyDoughAllocation($date, true);
    
    if ($result['ok']) {
        echo json_encode([
            'ok' => true,
            'message' => 'Přepočet dokončen',
            'data' => [
                'date' => $result['date'],
                'pizza_total' => $result['pizza_total'],
                'pizza_reserved' => $result['pizza_reserved'], 
                'pizza_walkin' => $result['pizza_walkin'],
                'reservations_count' => $result['reservations_count'],
                'total_reserved_dough' => $result['total_reserved_dough']
            ]
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}