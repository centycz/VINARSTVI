<?php
// Redirect na správný endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Přesměruj na správný endpoint
$correct_url = 'http://192.168.3.201:5000/print-order';
$input = file_get_contents('php://input');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $correct_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    http_response_code($http_code);
    echo $response;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Print server connection failed']);
}
?>
