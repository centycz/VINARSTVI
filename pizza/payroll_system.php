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
        if ($_POST['action'] === 'create_period' && $is_admin) {
            $stmt = $pdo->prepare("
                INSERT INTO payroll_periods (period_name, start_date, end_date, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['period_name'],
                $_POST['start_date'],
                $_POST['end_date'],
                $current_user_id
            ]);
            
            $_SESSION['success_message'] = "✅ Nové mzdové období bylo vytvořeno!";
            header("Location: payroll_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'calculate_payroll' && $is_admin) {
            $period_id = $_POST['period_id'];
            
            // Načtení období
            $period_stmt = $pdo->prepare("SELECT * FROM payroll_periods WHERE id = ?");
            $period_stmt->execute([$period_id]);
            $period = $period_stmt->fetch();
            
            if (!$period) {
                throw new Exception("Mzdové období nebylo nalezeno!");
            }
            
            // Smazání starých výpočtů
            $pdo->prepare("DELETE FROM payroll_entries WHERE period_id = ?")->execute([$period_id]);
            
            // Načtení všech schválených směn v období
            $shifts_stmt = $pdo->prepare("
                SELECT 
                    sr.user_id,
                    u.full_name,
                    u.username,
                    s.shift_date,
                    st.start_time,
                    st.end_time,
                    st.duration_hours,
                    st.hourly_rate,
                    COUNT(*) as shift_count,
                    SUM(st.duration_hours) as total_hours,
                    AVG(st.hourly_rate) as avg_rate
                FROM shift_requests sr
                JOIN shifts s ON sr.shift_id = s.id
                JOIN shift_types st ON s.shift_type_id = st.id
                JOIN order_users u ON sr.user_id = u.id
                WHERE sr.status = 'approved' 
                AND s.shift_date BETWEEN ? AND ?
                GROUP BY sr.user_id
                ORDER BY u.full_name
            ");
            $shifts_stmt->execute([$period['start_date'], $period['end_date']]);
            $employee_shifts = $shifts_stmt->fetchAll();
            
            // Výpočet mezd pro každého zaměstnance
            foreach ($employee_shifts as $emp) {
                $hours_worked = $emp['total_hours'];
                $overtime_hours = max(0, $hours_worked - 160); // Přesčas nad 160h
                $regular_hours = min($hours_worked, 160);
                $base_rate = $emp['avg_rate'];
                $overtime_rate = $base_rate * 1.5;
                
                $gross_pay = ($regular_hours * $base_rate) + ($overtime_hours * $overtime_rate);
                $tax_rate = 0.15; // 15% daň
                $insurance_rate = 0.135; // 13.5% pojištění
                
                $tax_deduction = $gross_pay * $tax_rate;
                $insurance_deduction = $gross_pay * $insurance_rate;
                $net_pay = $gross_pay - $tax_deduction - $insurance_deduction;
                
                // Vložení do payroll_entries
                $entry_stmt = $pdo->prepare("
                    INSERT INTO payroll_entries 
                    (period_id, user_id, hours_worked, overtime_hours, base_hourly_rate, overtime_rate, 
                     gross_pay, tax_deduction, insurance_deduction, net_pay, calculated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $entry_stmt->execute([
                    $period_id, $emp['user_id'], $hours_worked, $overtime_hours, 
                    $base_rate, $overtime_rate, $gross_pay, $tax_deduction, 
                    $insurance_deduction, $net_pay
                ]);
            }
            
            // Aktualizace stavu období
            $pdo->prepare("UPDATE payroll_periods SET status = 'calculated', calculated_at = NOW() WHERE id = ?")
               ->execute([$period_id]);
            
            $_SESSION['success_message'] = "✅ Mzdy byly vypočítány pro " . count($employee_shifts) . " zaměstnanců!";
            header("Location: payroll_system.php");
            exit;
        }
        
        if ($_POST['action'] === 'mark_paid' && $is_admin) {
            $period_id = $_POST['period_id'];
            
            $pdo->prepare("UPDATE payroll_periods SET status = 'paid', paid_at = NOW() WHERE id = ?")
               ->execute([$period_id]);
            $pdo->prepare("UPDATE payroll_entries SET paid_at = NOW() WHERE period_id = ?")
               ->execute([$period_id]);
            
            $_SESSION['success_message'] = "✅ Mzdy byly označeny jako vyplacené!";
            header("Location: payroll_system.php");
            exit;
        }
        
    } catch(Exception $e) {
        $_SESSION['error_message'] = "❌ Chyba: " . $e->getMessage();
        header("Location: payroll_system.php");
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

// Načtení mzdových období
$periods_query = "
    SELECT 
        pp.*,
        COUNT(pe.id) as employee_count,
        SUM(pe.gross_pay) as total_gross,
        SUM(pe.net_pay) as total_net,
        creator.full_name as created_by_name
    FROM payroll_periods pp
    LEFT JOIN payroll_entries pe ON pp.id = pe.period_id
    LEFT JOIN order_users creator ON pp.created_by = creator.id
    GROUP BY pp.id
    ORDER BY pp.start_date DESC
";
$periods = $pdo->query($periods_query)->fetchAll();

// Načtení výplatních pásek pro aktuálního uživatele
$user_payroll_query = "
    SELECT 
        pe.*,
        pp.period_name,
        pp.start_date,
        pp.end_date,
        pp.status as period_status
    FROM payroll_entries pe
    JOIN payroll_periods pp ON pe.period_id = pp.id
    WHERE pe.user_id = ?
    ORDER BY pp.start_date DESC
    LIMIT 12
";
$stmt = $pdo->prepare($user_payroll_query);
$stmt->execute([$current_user_id]);
$user_payroll = $stmt->fetchAll();

// Statistiky
$stats_query = "
    SELECT 
        COUNT(DISTINCT pp.id) as total_periods,
        COUNT(DISTINCT pe.user_id) as total_employees,
        SUM(CASE WHEN pp.status = 'calculated' THEN pe.gross_pay END) as pending_gross,
        SUM(CASE WHEN pp.status = 'paid' AND MONTH(pp.start_date) = MONTH(CURDATE()) THEN pe.net_pay END) as current_month_paid
    FROM payroll_periods pp
    LEFT JOIN payroll_entries pe ON pp.id = pe.period_id
    WHERE pp.start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
";
$stats = $pdo->query($stats_query)->fetch();

// Detailní výplatní páska pro viewing
$selected_payroll = null;
if (isset($_GET['view_payroll'])) {
    $stmt = $pdo->prepare("
        SELECT 
            pe.*,
            pp.period_name,
            pp.start_date,
            pp.end_date,
            u.full_name,
            u.username
        FROM payroll_entries pe
        JOIN payroll_periods pp ON pe.period_id = pp.id
        JOIN order_users u ON pe.user_id = u.id
        WHERE pe.id = ? AND (pe.user_id = ? OR ?)
    ");
    $stmt->execute([$_GET['view_payroll'], $current_user_id, $is_admin]);
    $selected_payroll = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mzdová agenda - Pizza dal Cortile</title>
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
            grid-template-columns: 2fr 1fr;
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

        .period-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }

        .period-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .period-item.calculated {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }

        .period-item.paid {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }

        .period-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .period-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .period-dates {
            color: #666;
            font-size: 0.9rem;
        }

        .period-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .period-stat {
            color: #667eea;
            font-weight: 500;
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

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .payroll-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .payroll-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .payroll-detail {
            text-align: center;
        }

        .payroll-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .payroll-label {
            font-size: 0.9rem;
            color: #666;
        }

        .admin-controls {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-open {
            background: #e9ecef;
            color: #495057;
        }

        .status-calculated {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .payroll-detail-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .payroll-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .breakdown-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .payroll-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>💰 Mzdová agenda</h1>
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
                <a href="shifts_system.php" class="nav-link">⏰ Směny</a>
                <a href="payroll_system.php" class="nav-link active">💰 Mzdy</a>
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
                <div class="stat-number"><?= $stats['total_periods'] ?></div>
                <div class="stat-label">📊 Mzdových období</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['total_employees'] ?></div>
                <div class="stat-label">👥 Zaměstnanců</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['pending_gross'] ?? 0, 0, ',', ' ') ?> Kč</div>
                <div class="stat-label">⏳ K vyplacení</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['current_month_paid'] ?? 0, 0, ',', ' ') ?> Kč</div>
                <div class="stat-label">✅ Vyplaceno tento měsíc</div>
            </div>
        </div>

        <div class="main-grid">
            <!-- LEVÝ PANEL: MZDOVÁ OBDOBÍ -->
            <div class="panel">
                <div class="panel-title">
                    📊 Mzdová období
                </div>

                <?php if ($is_admin): ?>
                <div class="admin-controls">
                    <h4>👨‍💼 Vytvořit nové mzdové období</h4>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="create_period">
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
                            <input type="text" name="period_name" placeholder="Název období (např. Červenec 2025)" required>
                            <input type="date" name="start_date" required>
                            <input type="date" name="end_date" required>
                            <button type="submit" class="btn btn-primary btn-small">➕ Vytvořit</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($periods as $period): ?>
                        <div class="period-item <?= $period['status'] ?>">
                            <div class="period-header">
                                <div>
                                    <div class="period-title"><?= htmlspecialchars($period['period_name']) ?></div>
                                    <div class="period-dates">
                                        📅 <?= date('d.m.Y', strtotime($period['start_date'])) ?> - 
                                        <?= date('d.m.Y', strtotime($period['end_date'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $period['status'] ?>">
                                    <?php if ($period['status'] === 'open'): ?>
                                        📝 Otevřené
                                    <?php elseif ($period['status'] === 'calculated'): ?>
                                        ⏳ Vypočítané
                                    <?php elseif ($period['status'] === 'paid'): ?>
                                        ✅ Vyplacené
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="period-stats">
                                <div class="period-stat">👥 <?= $period['employee_count'] ?> zaměstnanců</div>
                                <div class="period-stat">💰 <?= number_format($period['total_gross'] ?? 0, 0, ',', ' ') ?> Kč hrubý</div>
                                <div class="period-stat">💵 <?= number_format($period['total_net'] ?? 0, 0, ',', ' ') ?> Kč čistý</div>
                            </div>
                            
                            <?php if ($is_admin): ?>
                                <div style="margin-top: 15px; display: flex; gap: 10px;">
                                    <?php if ($period['status'] === 'open'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="calculate_payroll">
                                            <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-small" 
                                                    onclick="return confirm('Opravdu vypočítat mzdy pro období <?= htmlspecialchars($period['period_name']) ?>?')">
                                                🧮 Vypočítat mzdy
                                            </button>
                                        </form>
                                    <?php elseif ($period['status'] === 'calculated'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="mark_paid">
                                            <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-small"
                                                    onclick="return confirm('Označit mzdy jako vyplacené?')">
                                                💳 Označit jako vyplacené
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($periods)): ?>
                        <div style="text-align: center; color: #666; padding: 40px;">
                            📭 Žádná mzdová období zatím nebyla vytvořena.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRAVÝ PANEL: MOJE VÝPLATNÍ PÁSKY -->
            <div class="panel">
                <div class="panel-title">
                    📄 Moje výplatní pásky
                </div>

                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($user_payroll as $payroll): ?>
                        <div class="payroll-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <div style="font-weight: bold; color: #333;">
                                        <?= htmlspecialchars($payroll['period_name']) ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #666;">
                                        📅 <?= date('d.m.Y', strtotime($payroll['start_date'])) ?> - 
                                        <?= date('d.m.Y', strtotime($payroll['end_date'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $payroll['period_status'] ?>">
                                    <?php if ($payroll['period_status'] === 'calculated'): ?>
                                        ⏳ K vyplacení
                                    <?php elseif ($payroll['period_status'] === 'paid'): ?>
                                        ✅ Vyplaceno
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="payroll-summary">
                                <div class="payroll-detail">
                                    <div class="payroll-amount"><?= number_format($payroll['hours_worked'], 1) ?>h</div>
                                    <div class="payroll-label">Odpracováno</div>
                                </div>
                                <div class="payroll-detail">
                                    <div class="payroll-amount"><?= number_format($payroll['gross_pay'], 0, ',', ' ') ?> Kč</div>
                                    <div class="payroll-label">Hrubá mzda</div>
                                </div>
                                <div class="payroll-detail">
                                    <div class="payroll-amount" style="color: #28a745;"><?= number_format($payroll['net_pay'], 0, ',', ' ') ?> Kč</div>
                                    <div class="payroll-label">Čistá mzda</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <a href="?view_payroll=<?= $payroll['id'] ?>" class="btn btn-primary btn-small">
                                    📄 Zobrazit detail
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($user_payroll)): ?>
                        <div style="text-align: center; color: #666; padding: 40px;">
                            📭 Zatím nemáte žádné výplatní pásky.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PRO DETAIL VÝPLATNÍ PÁSKY -->
    <?php if ($selected_payroll): ?>
    <div class="payroll-detail-modal" onclick="if(event.target === this) window.location.href='payroll_system.php'">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>📄 Výplatní páska</h2>
                <a href="payroll_system.php" class="btn btn-secondary btn-small">✕ Zavřít</a>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3><?= htmlspecialchars($selected_payroll['period_name']) ?></h3>
                <p>👤 <?= htmlspecialchars($selected_payroll['full_name']) ?> (<?= htmlspecialchars($selected_payroll['username']) ?>)</p>
                <p>📅 <?= date('d.m.Y', strtotime($selected_payroll['start_date'])) ?> - <?= date('d.m.Y', strtotime($selected_payroll['end_date'])) ?></p>
            </div>
            
            <div class="payroll-breakdown">
                <div class="breakdown-item">
                    <span>Odpracované hodiny:</span>
                    <strong><?= number_format($selected_payroll['hours_worked'], 1) ?>h</strong>
                </div>
                <div class="breakdown-item">
                    <span>Přesčasové hodiny:</span>
                    <strong><?= number_format($selected_payroll['overtime_hours'], 1) ?>h</strong>
                </div>
                <div class="breakdown-item">
                    <span>Základní sazba:</span>
                    <strong><?= number_format($selected_payroll['base_hourly_rate'], 0) ?> Kč/h</strong>
                </div>
                <div class="breakdown-item">
                    <span>Přesčasová sazba:</span>
                    <strong><?= number_format($selected_payroll['overtime_rate'], 0) ?> Kč/h</strong>
                </div>
                <div class="breakdown-item">
                    <span>Bonusy:</span>
                    <strong><?= number_format($selected_payroll['bonus'], 0) ?> Kč</strong>
                </div>
                <div class="breakdown-item">
                    <span>Srážky:</span>
                    <strong><?= number_format($selected_payroll['deductions'], 0) ?> Kč</strong>
                </div>
            </div>
            
            <div style="margin: 20px 0; padding: 15px; background: #e9ecef; border-radius: 8px;">
                <div class="breakdown-item">
                    <span>Hrubá mzda:</span>
                    <strong><?= number_format($selected_payroll['gross_pay'], 0, ',', ' ') ?> Kč</strong>
                </div>
                <div class="breakdown-item">
                    <span>Daň (15%):</span>
                    <strong>-<?= number_format($selected_payroll['tax_deduction'], 0, ',', ' ') ?> Kč</strong>
                </div>
                <div class="breakdown-item">
                    <span>Pojištění (13.5%):</span>
                    <strong>-<?= number_format($selected_payroll['insurance_deduction'], 0, ',', ' ') ?> Kč</strong>
                </div>
            </div>
            
            <div class="breakdown-total">
                <span>Čistá mzda k výplatě:</span>
                <strong style="color: #28a745;"><?= number_format($selected_payroll['net_pay'], 0, ',', ' ') ?> Kč</strong>
            </div>
            
            <?php if ($selected_payroll['notes']): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 8px;">
                    <strong>Poznámky:</strong><br>
                    <?= nl2br(htmlspecialchars($selected_payroll['notes'])) ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Vytisknout</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>