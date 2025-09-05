<?php
// Database configuration for finance system
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
function getFinanceDb() {
    static $pdo = null;
    if ($pdo) return $pdo;
    
    try {
        // Opraven� p�ipojen� k MySQL datab�zi pizza_orders
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        
        // Vytvo�en� tabulky transactions pro finan�n� sledov�n� (MySQL syntaxe)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('income', 'expense') NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT NOT NULL,
                category VARCHAR(255) NOT NULL,
                date DATE NOT NULL,
                user_created VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Add user_created column if it doesn't exist (for existing tables)
        try {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN user_created VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore error
        }
        
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Input sanitization function
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// JSON response helper
function jsonResponse($success, $data = null, $message = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// Validate required fields
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            jsonResponse(false, null, "Field '$field' is required");
        }
    }
}

// Validate transaction type
function validateTransactionType($type) {
    if (!in_array($type, ['income', 'expense'])) {
        jsonResponse(false, null, "Type must be 'income' or 'expense'");
    }
}

// Validate amount
function validateAmount($amount) {
    if (!is_numeric($amount) || $amount <= 0) {
        jsonResponse(false, null, "Amount must be a positive number");
    }
}

// Validate date format
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        jsonResponse(false, null, "Date must be in YYYY-MM-DD format");
    }
}
?>