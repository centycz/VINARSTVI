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

// Připojení k databázi (stejné jako v orders_system.php)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Chyba připojení: " . $e->getMessage());
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /index.php");
    exit;
}

$current_user = $_SESSION['order_user'];
$current_user_id = $_SESSION['order_user_id'];
$current_full_name = $_SESSION['order_full_name'];
$is_admin = $_SESSION['is_admin'] ?? false;

// Zpracování akcí
if ($_POST['action'] ?? false) {
    try {
        if ($_POST['action'] === 'request_shift') {
            // Kontrola, zda už není přihlášen na tuto směnu
            $check_stmt = $pdo->prepare("SELECT id FROM shift_requests WHERE shift_id = ? AND user_id = ?");
            $check_stmt->execute([$_POST['shift_id'], $current_user_id]);
            
            if ($check_stmt->fetch()) {
                $_SESSION['error_message'] = "❌ Už jste přihlášen na tuto směnu!";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO shift_requests (shift_id, user_id, request_note, priority) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['shift_id'],
                    $current_user_id,
                    $_POST['request_note'] ?: null,
                    $_POST['priority'] ?? 'normal'
                ]);
                
                $_SESSION['success_message'] = "✅ Přihláška na směnu byla odeslána!";
            }
            header("Location: shifts_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'cancel_request') {
            $stmt = $pdo->prepare("
                UPDATE shift_requests 
                SET status = 'cancelled' 
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$_POST['request_id'], $current_user_id]);
            
            $_SESSION['success_message'] = "✅ Přihláška byla zrušena!";
            header("Location: shifts_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'approve_request' && $is_admin) {
            $stmt = $pdo->prepare("
                UPDATE shift_requests 
                SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, admin_note = ?
                WHERE id = ?
            ");
            $stmt->execute([$current_user_id, $_POST['admin_note'] ?? null, $_POST['request_id']]);
            
            $_SESSION['success_message'] = "✅ Přihláška byla schválena!";
            header("Location: shifts_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'reject_request' && $is_admin) {
            $stmt = $pdo->prepare("
                UPDATE shift_requests 
                SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, admin_note = ?
                WHERE id = ?
            ");
            $stmt->execute([$current_user_id, $_POST['admin_note'] ?? null, $_POST['request_id']]);
            
            $_SESSION['success_message'] = "❌ Přihláška byla zamítnuta!";
            header("Location: shifts_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'create_shift' && $is_admin) {
            $stmt = $pdo->prepare("
                INSERT INTO shifts (shift_type_id, shift_date, notes, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['shift_type_id'],
                $_POST['shift_date'],
                $_POST['notes'] ?: null,
                $current_user_id
            ]);
            
            $_SESSION['success_message'] = "✅ Nová směna byla vytvořena!";
            header("Location: shifts_system.php");
            exit;
        }
        
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Chyba: " . $e->getMessage();
        header("Location: shifts_system.php");
        exit;
    }
}

// Načtení zpráv ze session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Načtení typů směn
$shift_types = $pdo->query("SELECT * FROM shift_types ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);

// Načtení směn (příští 2 týdny)
$shifts_query = "
    SELECT 
        s.*,
        st.name as type_name,
        st.icon as type_icon,
        st.color as type_color,
        st.start_time,
        st.end_time,
        st.duration_hours,
        st.max_employees,
        st.hourly_rate,
        COUNT(sr.id) as total_requests,
        COUNT(CASE WHEN sr.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN sr.status = 'pending' THEN 1 END) as pending_count
    FROM shifts s
    JOIN shift_types st ON s.shift_type_id = st.id
    LEFT JOIN shift_requests sr ON s.id = sr.shift_id AND sr.status IN ('pending', 'approved')
    WHERE s.shift_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    GROUP BY s.id
    ORDER BY s.shift_date, st.start_time
";
$shifts = $pdo->query($shifts_query)->fetchAll(PDO::FETCH_ASSOC);

// Načtení přihlášek uživatele
$user_requests = [];
$user_requests_query = "
    SELECT sr.*, s.shift_date, st.name as type_name, st.icon as type_icon
    FROM shift_requests sr
    JOIN shifts s ON sr.shift_id = s.id
    JOIN shift_types st ON s.shift_type_id = st.id
    WHERE sr.user_id = ? AND s.shift_date >= CURDATE()
    ORDER BY s.shift_date, st.start_time
";
$stmt = $pdo->prepare($user_requests_query);
$stmt->execute([$current_user_id]);
$user_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Načtení všech přihlášek pro admin
$all_requests = [];
if ($is_admin) {
    $all_requests_query = "
        SELECT 
            sr.*,
            s.shift_date,
            st.name as type_name,
            st.icon as type_icon,
            st.color as type_color,
            u.username,
            u.full_name
        FROM shift_requests sr
        JOIN shifts s ON sr.shift_id = s.id
        JOIN shift_types st ON s.shift_type_id = st.id
        JOIN order_users u ON sr.user_id = u.id
        WHERE s.shift_date >= CURDATE()
        ORDER BY 
            FIELD(sr.status, 'pending', 'approved', 'rejected', 'cancelled'),
            FIELD(sr.priority, 'urgent', 'preferred', 'normal'),
            s.shift_date, st.start_time
    ";
    $all_requests = $pdo->query($all_requests_query)->fetchAll(PDO::FETCH_ASSOC);
}

// Statistiky
$stats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN sr.status = 'pending' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN sr.status = 'approved' AND s.shift_date = CURDATE() THEN 1 END) as today_approved,
        COUNT(DISTINCT sr.user_id) as active_employees,
        COUNT(CASE WHEN s.shift_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as upcoming_shifts
    FROM shift_requests sr 
    JOIN shifts s ON sr.shift_id = s.id
    WHERE s.shift_date >= CURDATE()
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plánování směn - Pizza dal Cortile</title>
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
            max-width: 1400px;
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

        .navigation {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .nav-link {
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .shift-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }

        .shift-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .shift-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .shift-info {
            flex: 1;
        }

        .shift-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .shift-time {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .shift-date {
            color: #667eea;
            font-weight: 500;
        }

        .shift-stats {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .shift-stat {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .shift-stat.approved {
            background: #d4edda;
            color: #155724;
        }

        .shift-stat.pending {
            background: #fff3cd;
            color: #856404;
        }

        .shift-stat.full {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
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
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-small {
            padding: 6px 10px;
            font-size: 0.8rem;
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

        .request-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .request-item.pending {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }

        .request-item.approved {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }

        .request-item.rejected {
            border-left: 4px solid #dc3545;
            background: #fdf2f2;
        }

        .request-item.urgent {
            border-left: 4px solid #dc3545;
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

        .login-prompt {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }

        .admin-controls {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .requests-container {
            max-height: 500px;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>⏰ Plánování směn</h1>
            <div class="user-info">
                    <span>👤 <?= htmlspecialchars($current_full_name) ?> (<?= htmlspecialchars($current_user) ?>)</span>
                    <span>📅 <?= date('d.m.Y H:i') ?></span>
                    <?php if ($is_admin): ?>
                        <span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">ADMIN</span>
                    <?php endif; ?>
                    <a href="?logout=1" class="btn btn-warning">🚪 Odhlásit</a>
            </div>
        </div>

        <!-- NAVIGACE -->
        <div class="navigation">
            <div class="nav-links">
                <a href="orders_system.php" class="nav-link">🛒 Objednávky</a>
                <a href="shifts_system.php" class="nav-link active">⏰ Směny</a>
                <a href="payroll_system.php" class="nav-link">💰 Mzdy</a>
                <span style="color: #999;">|</span>
                <a href="../" class="nav-link">← Hlavní stránka</a>
            </div>
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
                <div class="stat-number"><?= $stats['pending_requests'] ?></div>
                <div class="stat-label">📋 Čekající přihlášky</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['today_approved'] ?></div>
                <div class="stat-label">✅ Dnes schválené</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['active_employees'] ?></div>
                <div class="stat-label">👥 Aktivní zaměstnanci</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['upcoming_shifts'] ?></div>
                <div class="stat-label">📅 Nadcházející směny</div>
            </div>
        </div>

        <div class="main-grid">
            <!-- LEVÝ PANEL: DOSTUPNÉ SMĚNY -->
            <div class="panel">
                <div class="panel-title">
                    📅 Dostupné směny (příští 2 týdny)
                </div>

                <?php if ($is_admin): ?>
                <div class="admin-controls">
                    <h4>👨‍💼 Vytvořit novou směnu</h4>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="create_shift">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 2fr auto; gap: 10px; align-items: end;">
                            <select name="shift_type_id" required>
                                <?php foreach ($shift_types as $type): ?>
                                    <option value="<?= $type['id'] ?>"><?= $type['icon'] ?> <?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="shift_date" required min="<?= date('Y-m-d') ?>">
                            <input type="text" name="notes" placeholder="Poznámka (nepovinné)">
                            <button type="submit" class="btn btn-primary btn-small">➕ Vytvořit</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="requests-container">
                    <?php foreach ($shifts as $shift): ?>
                        <div class="shift-item">
                            <div class="shift-header">
                                <div class="shift-info">
                                    <div class="shift-title">
                                        <?= $shift['type_icon'] ?> <?= htmlspecialchars($shift['type_name']) ?>
                                    </div>
                                    <div class="shift-time">
                                        🕐 <?= date('H:i', strtotime($shift['start_time'])) ?> - <?= date('H:i', strtotime($shift['end_time'])) ?>
                                        (<?= $shift['duration_hours'] ?>h)
                                    </div>
                                    <div class="shift-date">
                                        📅 <?= date('d.m.Y', strtotime($shift['shift_date'])) ?> (<?= date('l', strtotime($shift['shift_date'])) ?>)
                                    </div>
                                    <?php if ($shift['notes']): ?>
                                        <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                            📝 <?= htmlspecialchars($shift['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: #667eea;">
                                        <?= $shift['hourly_rate'] ?> Kč/h
                                    </div>
                                </div>
                            </div>
                            
                            <div class="shift-stats">
                                <div class="shift-stat approved">
                                    ✅ <?= $shift['approved_count'] ?>/<?= $shift['max_employees'] ?> schváleno
                                </div>
                                <div class="shift-stat pending">
                                    ⏳ <?= $shift['pending_count'] ?> čeká
                                </div>
                                <?php if ($shift['approved_count'] >= $shift['max_employees']): ?>
                                    <div class="shift-stat full">📋 Obsazeno</div>
                                <?php endif; ?>
                            </div>

                            <?php if ($shift['approved_count'] < $shift['max_employees']): ?>
                                <?php
                                // Kontrola, zda už je uživatel přihlášen
                                $user_request_stmt = $pdo->prepare("SELECT status FROM shift_requests WHERE shift_id = ? AND user_id = ?");
                                $user_request_stmt->execute([$shift['id'], $current_user_id]);
                                $user_request_status = $user_request_stmt->fetchColumn();
                                ?>
                                
                                <?php if (!$user_request_status): ?>
                                    <form method="POST" style="margin-top: 15px;">
                                        <input type="hidden" name="action" value="request_shift">
                                        <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 10px;">
                                            <input type="text" name="request_note" placeholder="Poznámka k přihlášce...">
                                            <select name="priority">
                                                <option value="normal">📅 Normální</option>
                                                <option value="preferred">⭐ Preferuji</option>
                                                <option value="urgent">🔥 Urgentní</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-small">
                                                ✋ Přihlásit se
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div style="margin-top: 15px; padding: 10px; background: #e9ecef; border-radius: 5px; font-size: 0.9rem;">
                                        <?php if ($user_request_status === 'pending'): ?>
                                            ⏳ Vaše přihláška čeká na schválení
                                        <?php elseif ($user_request_status === 'approved'): ?>
                                            ✅ Jste přihlášen na tuto směnu
                                        <?php elseif ($user_request_status === 'rejected'): ?>
                                            ❌ Vaše přihláška byla zamítnuta
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($shifts)): ?>
                        <div style="text-align: center; color: #666; padding: 40px;">
                            📭 Žádné směny zatím nebyly vytvořeny.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRAVÝ PANEL: MOJE PŘIHLÁŠKY / ADMIN PANEL -->
            <div class="panel">
                <div class="panel-title">
                    <?= $is_admin ? '👨‍💼 Správa přihlášek' : '📋 Moje přihlášky' ?>
                </div>

                <div class="requests-container">
                        <?php if ($is_admin): ?>
                            <!-- ADMIN: Všechny přihlášky -->
                            <?php foreach ($all_requests as $req): ?>
                                <div class="request-item <?= $req['status'] ?> <?= $req['priority'] === 'urgent' ? 'urgent' : '' ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <div>
                                            <div style="font-weight: bold;">
                                                👤 <?= htmlspecialchars($req['full_name']) ?> (<?= htmlspecialchars($req['username']) ?>)
                                            </div>
                                            <div style="font-size: 0.9rem; color: #666;">
                                                <?= $req['type_icon'] ?> <?= htmlspecialchars($req['type_name']) ?> - 
                                                📅 <?= date('d.m.Y', strtotime($req['shift_date'])) ?>
                                            </div>
                                        </div>
                                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 500;
                                                     background: <?= $req['status'] === 'pending' ? '#fff3cd' : ($req['status'] === 'approved' ? '#d4edda' : '#f8d7da') ?>;
                                                     color: <?= $req['status'] === 'pending' ? '#856404' : ($req['status'] === 'approved' ? '#155724' : '#721c24') ?>;">
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <?= $req['priority'] === 'urgent' ? '🔥 URGENTNÍ' : '⏳ Čeká' ?>
                                            <?php elseif ($req['status'] === 'approved'): ?>
                                                ✅ Schváleno
                                            <?php else: ?>
                                                ❌ Zamítnuto
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($req['request_note']): ?>
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                                            💬 <?= htmlspecialchars($req['request_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['admin_note']): ?>
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-style: italic;">
                                            👨‍💼 Poznámka admin: <?= htmlspecialchars($req['admin_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="approve_request">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="text" name="admin_note" placeholder="Poznámka (nepovinné)" style="width: 200px; padding: 6px; margin-right: 5px;">
                                                <button type="submit" class="btn btn-success btn-small">✅ Schválit</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="reject_request">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="text" name="admin_note" placeholder="Důvod zamítnutí" style="width: 200px; padding: 6px; margin-right: 5px;">
                                                <button type="submit" class="btn btn-danger btn-small">❌ Zamítnout</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- UŽIVATEL: Pouze jeho přihlášky -->
                            <?php foreach ($user_requests as $req): ?>
                                <div class="request-item <?= $req['status'] ?> <?= $req['priority'] === 'urgent' ? 'urgent' : '' ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <div>
                                            <div style="font-weight: bold;">
                                                <?= $req['type_icon'] ?> <?= htmlspecialchars($req['type_name']) ?>
                                            </div>
                                            <div style="font-size: 0.9rem; color: #666;">
                                                📅 <?= date('d.m.Y', strtotime($req['shift_date'])) ?>
                                            </div>
                                        </div>
                                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 500;
                                                     background: <?= $req['status'] === 'pending' ? '#fff3cd' : ($req['status'] === 'approved' ? '#d4edda' : '#f8d7da') ?>;
                                                     color: <?= $req['status'] === 'pending' ? '#856404' : ($req['status'] === 'approved' ? '#155724' : '#721c24') ?>;">
                                            <?php if ($req['status'] === 'pending'): ?>
                                                ⏳ Čeká na schválení
                                            <?php elseif ($req['status'] === 'approved'): ?>
                                                ✅ Schváleno
                                            <?php else: ?>
                                                ❌ Zamítnuto
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($req['request_note']): ?>
                                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                                            💬 Vaše poznámka: <?= htmlspecialchars($req['request_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('Opravdu zrušit přihlášku?')">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-small">🗑️ Zrušit přihlášku</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (empty($is_admin ? $all_requests : $user_requests)): ?>
                            <div style="text-align: center; color: #666; padding: 40px;">
                                📭 <?= $is_admin ? 'Žádné přihlášky zatím nebyly odeslány.' : 'Nemáte žádné aktivní přihlášky.' ?>
                            </div>
                        <?php endif; ?>
                    </div>
            </div>
        </div>
    </div>
</body>
</html>