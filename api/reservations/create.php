<?php
/**
 * Create reservation endpoint
 * POST /api/reservations/create.php
 * Content-Type: application/json
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
    
    // Get JSON data from request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Neplatný JSON formát');
    }
    
    // Create reservation
    $result = createReservation($data);
    
    if ($result['ok']) {
        http_response_code(201);
        echo json_encode([
            'ok' => true,
            'message' => 'Rezervace byla úspěšně vytvořena',
            'id' => $result['id']
        ]);
    } else {
        // Collision check - return 422 for business logic errors
        if (strpos($result['error'], 'Kolize') !== false) {
            http_response_code(422);
        } else {
            http_response_code(400);
        }
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}