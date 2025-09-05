<?php
/**
 * List reservations endpoint
 * GET /api/reservations/list.php?date=YYYY-MM-DD
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
    require_once __DIR__ . '/../../includes/reservations_lib.php';
    
    $filters = [];
    
    // Get date filter from query parameter
    if (isset($_GET['date']) && !empty($_GET['date'])) {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
            throw new Exception('Neplatný formát data');
        }
        $filters['date'] = $_GET['date'];
    }
    
    // Get status filter from query parameter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    
    // Get table_number filter from query parameter  
    if (isset($_GET['table_number']) && !empty($_GET['table_number'])) {
        $filters['table_number'] = (int)$_GET['table_number'];
    }
    
    $reservations = getReservations($filters);
    
    echo json_encode([
        'ok' => true,
        'data' => $reservations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}