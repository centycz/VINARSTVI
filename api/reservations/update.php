<?php
require_once __DIR__ . '/../../includes/reservations_lib.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'Invalid method']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok'=>false,'error'=>'Empty body']); exit;
}
if (empty($input['id'])) {
    echo json_encode(['ok'=>false,'error'=>'Missing id']); exit;
}

try {
    $result = updateReservation($input);
    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}