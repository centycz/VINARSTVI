<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    jsonResponse(false, null, 'User not authenticated');
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getFinanceDb();

switch ($method) {
    case 'GET':
        handleGet($db);
        break;
    case 'POST':
        handlePost($db);
        break;
    case 'PUT':
        handlePut($db);
        break;
    case 'DELETE':
        handleDelete($db);
        break;
    default:
        http_response_code(405);
        jsonResponse(false, null, 'Method not allowed');
}

function handleGet($db) {
    try {
        $sql = "SELECT * FROM transactions ORDER BY date DESC, created_at DESC";
        $params = [];
        
        // Filter by type
        if (isset($_GET['type']) && in_array($_GET['type'], ['income', 'expense'])) {
            $sql = "SELECT * FROM transactions WHERE type = ? ORDER BY date DESC, created_at DESC";
            $params[] = sanitizeInput($_GET['type']);
        }
        
        // Filter by date range
        if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
            $dateFrom = sanitizeInput($_GET['date_from']);
            $dateTo = sanitizeInput($_GET['date_to']);
            
            validateDate($dateFrom);
            validateDate($dateTo);
            
            if (isset($_GET['type']) && in_array($_GET['type'], ['income', 'expense'])) {
                $sql = "SELECT * FROM transactions WHERE type = ? AND date BETWEEN ? AND ? ORDER BY date DESC, created_at DESC";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            } else {
                $sql = "SELECT * FROM transactions WHERE date BETWEEN ? AND ? ORDER BY date DESC, created_at DESC";
                $params = [$dateFrom, $dateTo];
            }
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
        
        jsonResponse(true, $transactions);
    } catch (Exception $e) {
        jsonResponse(false, null, 'Error fetching transactions: ' . $e->getMessage());
    }
}

function handlePost($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(false, null, 'Invalid JSON input');
        }
        
        $input = sanitizeInput($input);
        
        // Validate required fields
        validateRequired($input, ['type', 'amount', 'description', 'category', 'date']);
        
        // Validate data types and values
        validateTransactionType($input['type']);
        validateAmount($input['amount']);
        validateDate($input['date']);
        
        // Insert transaction
        $stmt = $db->prepare("
            INSERT INTO transactions (type, amount, description, category, date, user_created)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['type'],
            $input['amount'],
            $input['description'],
            $input['category'],
            $input['date'],
            $_SESSION['order_user']
        ]);
        
        $transactionId = $db->lastInsertId();
        
        // Fetch the created transaction
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        jsonResponse(true, $transaction, 'Transaction created successfully');
    } catch (Exception $e) {
        jsonResponse(false, null, 'Error creating transaction: ' . $e->getMessage());
    }
}

function handlePut($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(false, null, 'Invalid JSON input');
        }
        
        $input = sanitizeInput($input);
        
        // Validate required fields
        validateRequired($input, ['id', 'type', 'amount', 'description', 'category', 'date']);
        
        // Validate data types and values
        validateTransactionType($input['type']);
        validateAmount($input['amount']);
        validateDate($input['date']);
        
        // Check if transaction exists
        $stmt = $db->prepare("SELECT id FROM transactions WHERE id = ?");
        $stmt->execute([$input['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(false, null, 'Transaction not found');
        }
        
        // Update transaction
        $stmt = $db->prepare("
            UPDATE transactions 
            SET type = ?, amount = ?, description = ?, category = ?, date = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['type'],
            $input['amount'],
            $input['description'],
            $input['category'],
            $input['date'],
            $input['id']
        ]);
        
        // Fetch the updated transaction
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$input['id']]);
        $transaction = $stmt->fetch();
        
        jsonResponse(true, $transaction, 'Transaction updated successfully');
    } catch (Exception $e) {
        jsonResponse(false, null, 'Error updating transaction: ' . $e->getMessage());
    }
}

function handleDelete($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            jsonResponse(false, null, 'Transaction ID is required');
        }
        
        $id = sanitizeInput($input['id']);
        
        // Check if transaction exists
        $stmt = $db->prepare("SELECT id FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(false, null, 'Transaction not found');
        }
        
        // Delete transaction
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(true, null, 'Transaction deleted successfully');
    } catch (Exception $e) {
        jsonResponse(false, null, 'Error deleting transaction: ' . $e->getMessage());
    }
}
?>