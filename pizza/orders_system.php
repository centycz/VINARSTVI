<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    header('Location: /index.php');
    exit;
}

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['user_role'];

// Připojení k databázi
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Chyba připojení: " . $e->getMessage());
}

// Odhlášení
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /index.php");
    exit;
}

$current_user = $_SESSION['order_user'];
$current_user_id = $_SESSION['order_user_id'];
$current_full_name = $_SESSION['order_full_name'];
$is_admin = $_SESSION['is_admin'] ?? false;

if ($_POST['action'] ?? false) {
    try {
        if ($_POST['action'] === 'add_request') {
            $stmt = $pdo->prepare("
                INSERT INTO order_requests (user_id, category_id, product, quantity, note, priority) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_user_id,
                $_POST['category_id'],
                $_POST['product'],
                $_POST['quantity'],
                $_POST['note'] ?: null,
                $_POST['priority']
            ]);
            
            // ✅ PŘESMĚROVÁNÍ místo ukládání zprávy do proměnné
            $_SESSION['success_message'] = "✅ Požadavek byl úspěšně přidán!";
            header("Location: orders_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'mark_ordered' && $is_admin) {
            $stmt = $pdo->prepare("
                UPDATE order_requests 
                SET status = 'ordered', 
                    ordered_at = NOW(), 
                    ordered_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$current_user_id, $_POST['request_id']]);
            
            $_SESSION['success_message'] = "✅ Požadavek označen jako objednaný!";
            header("Location: orders_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'delete_request') {
            $check_stmt = $pdo->prepare("SELECT user_id FROM order_requests WHERE id = ?");
            $check_stmt->execute([$_POST['request_id']]);
            $request_owner = $check_stmt->fetchColumn();
            
            if ($is_admin || $request_owner == $current_user_id) {
                $stmt = $pdo->prepare("DELETE FROM order_requests WHERE id = ?");
                $stmt->execute([$_POST['request_id']]);
                
                $_SESSION['success_message'] = "🗑️ Požadavek byl smazán!";
            } else {
                $_SESSION['error_message'] = "❌ Nemáte oprávnění smazat tento požadavek!";
            }
            header("Location: orders_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'delete_old_ordered' && $is_admin) {
            $stmt = $pdo->prepare("
                DELETE FROM order_requests 
                WHERE status = 'ordered' 
                AND ordered_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $affected = $stmt->rowCount();
            
            $_SESSION['success_message'] = "🗑️ Smazáno {$affected} starých objednaných požadavků!";
            header("Location: orders_system.php");
            exit;
        }
        
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Chyba: " . $e->getMessage();
        header("Location: orders_system.php");
        exit;
    }
}

// ✅ NAČTENÍ ZPRÁV ZE SESSION
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Načtení kategorií
$categories = $pdo->query("SELECT * FROM order_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Načtení požadavků
$requests_query = "
    SELECT 
        r.*,
        u.username,
        u.full_name,
        c.name as category_name,
        c.icon as category_icon,
        c.color as category_color,
        ordered_user.username as ordered_by_username,
        ordered_user.full_name as ordered_by_full_name
    FROM order_requests r
    JOIN order_users u ON r.user_id = u.id
    LEFT JOIN order_categories c ON r.category_id = c.id
    LEFT JOIN order_users ordered_user ON r.ordered_by = ordered_user.id
    ORDER BY 
        FIELD(r.status, 'pending', 'ordered', 'cancelled'),
        FIELD(r.priority, 'urgent', 'normal', 'low'),
        r.created_at DESC
";
$requests = $pdo->query($requests_query)->fetchAll(PDO::FETCH_ASSOC);

// Statistiky
$stats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'ordered' AND DATE(ordered_at) = CURDATE() THEN 1 END) as ordered_today,
        COUNT(DISTINCT user_id) as active_users
    FROM order_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firemní objednávky - Pizza dal Cortile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #666;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .panel-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #28a745;
            color: white;
            font-size: 0.9rem;
            padding: 8px 15px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            font-size: 0.9rem;
            padding: 8px 15px;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
            font-size: 0.9rem;
            padding: 8px 15px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            font-size: 0.9rem;
            padding: 8px 15px;
        }

        .requests-container {
            max-height: 600px;
            overflow-y: auto;
        }

        .request-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }

        .request-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .request-item.ordered {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .request-item.pending {
            background: #fff3cd;
            border-color: #ffeaa7;
        }

        .request-item.urgent {
            border-left: 4px solid #dc3545;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .request-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .request-user {
            font-weight: bold;
            color: #333;
        }

        .request-date {
            font-size: 0.85rem;
            color: #666;
        }

        .request-content {
            margin: 10px 0;
        }

        .request-product {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .request-quantity {
            color: #667eea;
            font-weight: bold;
        }

        .request-note {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-ordered {
            background: #d4edda;
            color: #155724;
        }

        .status-urgent {
            background: #f8d7da;
            color: #721c24;
        }

        .admin-controls {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .admin-controls h3 {
            color: #495057;
            margin-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .disabled-form {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .login-prompt {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
        
              
        .nav-link.active {
            background: #667eea;
            color: white;
        }

        .nav-link:not(.active) {
            color: #667eea;
            border: 1px solid #667eea;
        }

        .nav-link:not(.active):hover {
            background: #667eea;
            color: white;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .panel-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tab-menu {
    background: #f7f7fc;
    margin-bottom: 28px;
    border-radius: 15px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    padding: 10px 24px;
    display: flex;
    gap: 14px;
    align-items: center;
}
.tab-item {
    background: none;
    border: none;
    color: #667eea;
    font-weight: 500;
    padding: 10px 22px;
    border-radius: 10px;
    font-size: 1.08em;
    transition: background .18s, color .18s;
    display: flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    border: 1.5px solid transparent;
}
.tab-item:not(.active):hover {
    background: #e0e7ff;
    color: #333;
    border-color: #c3dafe;
}
.tab-item.active {
    background: #667eea;
    color: white !important;
    border-color: #667eea;
    box-shadow: 0 4px 12px #667eea22;
    font-weight: bold;
}
.tab-icon {
    font-size: 1.2em;
    margin-right: 5px;
}
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🛒 Firemní objednávky</h1>
            <div class="user-info">
                    <span>👤 <?= htmlspecialchars($current_full_name) ?> (<?= htmlspecialchars($current_user) ?>)</span>
                    <span>📅 <?= date('d.m.Y H:i') ?></span>
                    <?php if ($is_admin): ?>
                        <span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">ADMIN</span>
                    <?php endif; ?>
                    <a href="?logout=1" class="btn btn-warning">🚪 Odhlásit</a>
                <a href="../" class="btn btn-secondary">← Zpět</a>
            </div>
               </div>
                <!-- NOVÉ MENU S TABY -->
<div class="tab-menu">
    <a href="orders_system.php" class="tab-item active">
        <span class="tab-icon">🛒</span> Objednávky
    </a>
    <a href="shifts_system.php" class="tab-item">
        <span class="tab-icon">⏰</span> Směny
    </a>
    <a href="payroll_system.php" class="tab-item">
        <span class="tab-icon">💰</span> Mzdy
    </a>
    <a href="../" class="tab-item">
        ← Hlavní stránka
    </a>
</div>



        <?php if (isset($success_message)): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <!-- STATISTIKY -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?= $stats['pending_count'] ?></div>
                <div class="stat-label">📋 Čekající požadavky</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['ordered_today'] ?></div>
                <div class="stat-label">✅ Objednané dnes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['active_users'] ?></div>
                <div class="stat-label">👥 Aktivní uživatelé</div>
            </div>
        </div>

        <div class="main-grid">
            <!-- LEVÝ PANEL: NOVÝ POŽADAVEK -->
            <div class="panel">
                <div class="panel-title">
                    ➕ Nový požadavek
                </div>

                <form method="POST">
                        <input type="hidden" name="action" value="add_request">
                        
                        <div class="form-group">
                            <label>Kategorie:</label>
                            <select name="category_id" required>
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Produkt:</label>
                            <input type="text" name="product" placeholder="např. Pilsner Urquell, Gouda sýr..." required>
                        </div>

                        <div class="form-group">
                            <label>Množství:</label>
                            <input type="text" name="quantity" placeholder="např. 5x, 20kg, 1 balení..." required>
                        </div>

                        <div class="form-group">
                            <label>Poznámka (nepovinné):</label>
                            <textarea name="note" rows="3" placeholder="Dodatečné informace..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Priorita:</label>
                            <select name="priority">
                                <option value="normal">📅 Normální</option>
                                <option value="urgent">🔥 Urgentní</option>
                                <option value="low">⏳ Nízká</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            ➕ Přidat požadavek
                        </button>
                    </form>

                <!-- ADMIN KONTROLY -->
                <?php if ($is_admin): ?>
                <div class="admin-controls">
                    <h3>👨‍💼 Admin kontroly</h3>
                    <form method="POST" onsubmit="return confirm('Opravdu smazat všechny objednané požadavky starší 7 dní?')">
                        <input type="hidden" name="action" value="delete_old_ordered">
                        <button type="submit" class="btn btn-danger">
                            🗑️ Smazat objednané (starší 7 dní)
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- PRAVÝ PANEL: SEZNAM POŽADAVKŮ -->
            <div class="panel">
                <div class="panel-title">
                    📋 Všechny požadavky
                    <span style="font-size: 0.8rem; color: #666;">(<?= count($requests) ?> položek)</span>
                </div>

                <div class="requests-container">
                    <?php foreach ($requests as $req): ?>
                        <div class="request-item <?= $req['status'] ?> <?= $req['priority'] === 'urgent' ? 'urgent' : '' ?>">
                            <div class="request-header">
                                <div class="request-meta">
                                    <div class="request-user">👤 <?= htmlspecialchars($req['full_name']) ?> (<?= htmlspecialchars($req['username']) ?>)</div>
                                    <div class="request-date">🕐 <?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></div>
                                </div>
                                <span class="status-badge status-<?= $req['status'] ?> <?= $req['priority'] === 'urgent' ? 'status-urgent' : '' ?>">
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <?= $req['priority'] === 'urgent' ? '🔥 URGENTNÍ' : '⏳ Čeká' ?>
                                    <?php else: ?>
                                        ✅ Objednáno
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="request-content">
                                <div class="request-product">
                                    <?= $req['category_icon'] ?? '📦' ?> <?= htmlspecialchars($req['product']) ?>
                                </div>
                                <div class="request-quantity">Množství: <?= htmlspecialchars($req['quantity']) ?></div>
                                <?php if ($req['note']): ?>
                                    <div class="request-note">Poznámka: <?= htmlspecialchars($req['note']) ?></div>
                                <?php endif; ?>
                                <?php if ($req['status'] === 'ordered' && $req['ordered_by_full_name']): ?>
                                    <div class="request-note">Objednal: <?= htmlspecialchars($req['ordered_by_full_name']) ?> (<?= date('d.m.Y H:i', strtotime($req['ordered_at'])) ?>)</div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($req['status'] === 'pending'): ?>
                                <div class="request-actions">
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="mark_ordered">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" class="btn btn-success">✅ Označit jako objednané</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['user_id'] == $current_user_id || $is_admin): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Opravdu smazat tento požadavek?')">
                                            <input type="hidden" name="action" value="delete_request">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" class="btn btn-danger">🗑️ Smazat</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($requests)): ?>
                        <div style="text-align: center; color: #666; padding: 40px;">
                            📭 Žádné požadavky zatím nebyly vytvořeny.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>