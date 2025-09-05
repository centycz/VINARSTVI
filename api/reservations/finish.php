<?php
/**
 * Finish reservation endpoint
 * POST /api/reservations/finish.php
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
    
    if ($id <= 0) {
        throw new Exception('Neplatné ID rezervace');
    }
    
    $result = finishReservation($id);
    
    if ($result['ok']) {
        echo json_encode([
            'ok' => true,
            'message' => 'Rezervace byla ukončena'
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