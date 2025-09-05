<?php
/**
 * Opening Hours Management endpoint
 * GET /api/reservations/opening_hours.php?date=YYYY-MM-DD - get opening hours for date
 * POST /api/reservations/opening_hours.php - save opening hours for date
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get opening hours for a specific date
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Neplatný formát data');
        }
        
        $openingHours = getOpeningHours(getReservationDb(), $date);
        
        echo json_encode([
            'ok' => true,
            'open_time' => $openingHours['open_time'],
            'close_time' => $openingHours['close_time']
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save opening hours for a date
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Neplatný JSON formát');
        }
        
        // Validate required fields
        if (empty($data['date']) || empty($data['open_time']) || empty($data['close_time'])) {
            throw new Exception('Chybí povinná pole: date, open_time, close_time');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            throw new Exception('Neplatný formát data');
        }
        
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $data['open_time']) || !preg_match('/^\d{2}:\d{2}$/', $data['close_time'])) {
            throw new Exception('Neplatný formát času (očekává HH:MM)');
        }
        
        // Validate that open_time < close_time
        if ($data['open_time'] >= $data['close_time']) {
            throw new Exception('Čas otevření musí být dříve než čas zavření');
        }
        
        $result = setOpeningHours(getReservationDb(), $data['date'], $data['open_time'], $data['close_time']);
        
        if ($result['ok']) {
            echo json_encode([
                'ok' => true,
                'message' => 'Otevírací doba byla úspěšně uložena'
            ]);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Povoleny jsou pouze GET a POST metody']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}