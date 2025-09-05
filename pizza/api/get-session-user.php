<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in'
    ]);
    exit;
}

// Return user session info
echo json_encode([
    'success' => true,
    'username' => $_SESSION['order_user'] ?? '',
    'full_name' => $_SESSION['order_full_name'] ?? '',
    'user_role' => $_SESSION['user_role'] ?? 'user'
]);
?>