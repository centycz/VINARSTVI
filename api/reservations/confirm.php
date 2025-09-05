<?php
require_once __DIR__ . '/../../includes/reservations_lib.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']); exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'Missing id']); exit;
}

try {
    $pdo = getReservationDb();
    $stmt = $pdo->prepare("UPDATE reservations SET status='confirmed' WHERE id=? AND status='pending'");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Nelze potvrdit (není pending)']); exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Rezervace potvrzena']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}