<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neautorizovaný přístup']);
    exit;
}

require_once __DIR__ . '/../../../includes/reservations_lib.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!is_array($data)) {
        throw new Exception('Neplatný JSON');
    }
    
    if (empty($data['id'])) {
        throw new Exception('Chybí ID');
    }
    
    $id = (int)$data['id'];
    unset($data['id']);
    
    $result = updateReservation($id, $data);
    
    if (!$result['ok']) {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}