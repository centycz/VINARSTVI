<?php
/**
 * Update reservation status endpoint
 * POST /api/reservations/update_status.php
 * Content-Type: application/x-www-form-urlencoded
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Povolena pouze POST metoda']);
    exit;
}

try {
    require_once __DIR__ . '/../../includes/reservations_lib.php';
    
    // Get form data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if ($id <= 0) {
        throw new Exception('Neplatné ID rezervace');
    }
    
    if (empty($status)) {
        throw new Exception('Status je povinný');
    }
    
    $result = updateStatus($id, $status);
    
    if ($result['ok']) {
        echo json_encode([
            'ok' => true,
            'message' => 'Status rezervace byl aktualizován'
        ]);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}