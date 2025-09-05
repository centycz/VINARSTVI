<?php
session_start();

// Database connection
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If database connection fails, show a warning but don't break the page
    $db_error = "Databáze není dostupná - některé funkce nebudou fungovat.";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle login and registration
$login_error = '';
$register_error = '';
$register_success = '';

if ($_POST['action'] ?? '' === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password && !isset($db_error)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM order_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['order_user'] = $user['username'];
                $_SESSION['order_user_id'] = $user['id'];
                $_SESSION['order_full_name'] = $user['full_name'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                
                // Set user role (check if column exists, fallback to is_admin logic)
                if (isset($user['user_role'])) {
                    $_SESSION['user_role'] = $user['user_role'];
                } else {
                    $_SESSION['user_role'] = $user['is_admin'] ? 'admin' : 'user';
                }
                
                header('Location: index.php');
                exit;
            } else {
                $login_error = 'Nesprávné přihlašovací údaje!';
            }
        } catch(PDOException $e) {
            $login_error = 'Chyba databáze: ' . $e->getMessage();
        }
    } else {
        $login_error = 'Vyplňte všechna pole!';
    }
}

if ($_POST['action'] ?? '' === 'register') {
    $username = trim($_POST['reg_username'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $full_name = trim($_POST['reg_full_name'] ?? '');
    
    if ($username && $password && $full_name && !isset($db_error)) {
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            $register_error = 'Uživatelské jméno může obsahovat pouze písmena, číslice, tečku, podtržítko a pomlčku!';
        } elseif (strlen($password) < 4) {
            $register_error = 'Heslo musí mít minimálně 4 znaky!';
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM order_users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->fetch()) {
                    $register_error = 'Uživatel s tímto jménem už existuje!';
                } else {
                    // Create new user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO order_users (username, password_hash, full_name, is_admin, user_role) 
                        VALUES (?, ?, ?, 0, 'user')
                    ");
                    $stmt->execute([$username, $password_hash, $full_name]);
                    
                    // Auto-login after successful registration
                    $_SESSION['order_user'] = $username;
                    $_SESSION['order_user_id'] = $pdo->lastInsertId();
                    $_SESSION['order_full_name'] = $full_name;
                    $_SESSION['is_admin'] = false;
                    $_SESSION['user_role'] = 'user';
                    
                    header('Location: index.php');
                    exit;
                }
            } catch(PDOException $e) {
                $register_error = 'Chyba při registraci: ' . $e->getMessage();
            }
        }
    } else {
        $register_error = 'Vyplňte všechna pole!';
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['order_user']);
$user_role = $_SESSION['user_role'] ?? null;
$full_name = $_SESSION['order_full_name'] ?? '';

// Define tabs based on roles
function canAccessTab($tab, $user_role) {
    if (!$user_role) return false;

    $tab_permissions = [
        'restaurant'   => ['admin','ragazzi','user','recepce'],
        'status'       => ['admin','ragazzi','user','recepce'],
        'reservations' => ['admin','ragazzi','user','recepce'], // všichni čtou, omezení tvorby řešíme jinde
        'orders'       => ['admin','ragazzi'],
        'shifts'       => ['admin','ragazzi','user','recepce'],
        'payroll'      => ['admin','ragazzi'],
        'finance'      => ['admin','ragazzi'],
        'statistics'   => ['admin','ragazzi'],
        'phpmyadmin'   => ['admin']
    ];

    return in_array($user_role, $tab_permissions[$tab] ?? []);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stávek Systém - Centrum aplikací</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .user-info {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-welcome {
            color: #2c3e50;
            font-weight: 600;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
            text-decoration: none;
            color: white;
        }

        .server-status {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .login-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .login-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
        }

        .form-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }

        .form-tab.active {
            background: #667eea;
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .form-tab:hover:not(.active) {
            background: #e9ecef;
            color: #333;
        }

        .form-panel {
            display: none;
        }

        .form-panel.active {
            display: block;
        }

        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #5a6fd8;
        }

        .login-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .service-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            text-decoration: none;
            color: #333;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), var(--accent-color-light));
        }

        .service-card.pizza::before {
            --accent-color: #FF6B35;
            --accent-color-light: #F7931E;
        }

        .service-card.database::before {
            --accent-color: #4285F4;
            --accent-color-light: #34A853;
        }

        .service-card.system::before {
            --accent-color: #9C27B0;
            --accent-color-light: #E91E63;
        }

        .service-card.statistics::before {
            --accent-color: #FF5722;
            --accent-color-light: #FF9800;
        }

        .service-card.finance::before {
            --accent-color: #4CAF50;
            --accent-color-light: #8BC34A;
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .service-card h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .service-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .service-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .status-online {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            opacity: 0.8;
        }

        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .info-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .info-item h4 {
            margin-bottom: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
        }

        .info-item p {
            font-size: 0.9rem;
            opacity: 0.95;
            color: #fff;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }

            .system-info {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🍓 Vinařství Stávek </h1>
            <p>Centrum aplikací a služeb</p>
        </div>

        <!-- User Info (shown when logged in) -->
        <?php if ($is_logged_in): ?>
        <div class="user-info">
            <div class="user-welcome">
                🙋‍♂️ Vítejte, <strong><?= htmlspecialchars($full_name) ?></strong> 
                <span style="color: #666; font-size: 0.9rem;">(<?= ucfirst($user_role) ?>)</span>
            </div>
            <a href="index.php?logout=1" class="logout-btn">Odhlásit se</a>
        </div>
        <?php endif; ?>

        <!-- Login Section (shown when not logged in) -->
        <?php if (!$is_logged_in): ?>
        <div class="login-section">
            <h2>🔐 Přihlášení & Registrace</h2>
            
            <!-- Tab Navigation -->
            <div class="form-tabs">
                <div class="form-tab <?= $register_error ? '' : 'active' ?>" onclick="switchTab('login')">Přihlášení</div>
                <div class="form-tab <?= $register_error ? 'active' : '' ?>" onclick="switchTab('register')">Registrace</div>
            </div>
            
            <!-- Login Panel -->
            <div id="login-panel" class="form-panel <?= $register_error ? '' : 'active' ?>">
                <?php if ($login_error): ?>
                    <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <?php if (isset($db_error)): ?>
                    <div class="login-error"><?= htmlspecialchars($db_error) ?></div>
                <?php endif; ?>
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="username">Uživatelské jméno:</label>
                        <input type="text" id="username" name="username" required placeholder="např. Diego Maradona" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Heslo:</label>
                        <input type="password" id="password" name="password" required placeholder="Zadejte heslo">
                    </div>
                    <button type="submit" class="login-btn">🔓 Přihlásit se</button>
                </form>
            </div>
            
            <!-- Registration Panel -->
            <div id="register-panel" class="form-panel <?= $register_error ? 'active' : '' ?>">
                <?php if ($register_error): ?>
                    <div class="login-error"><?= htmlspecialchars($register_error) ?></div>
                <?php endif; ?>
                <?php if ($register_success): ?>
                    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #c3e6cb;">
                        <?= htmlspecialchars($register_success) ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($db_error)): ?>
                    <div class="login-error"><?= htmlspecialchars($db_error) ?></div>
                <?php endif; ?>
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="reg_username">Uživatelské jméno:</label>
                        <input type="text" id="reg_username" name="reg_username" required 
                               placeholder="např. jan.novak" 
                               pattern="[a-zA-Z0-9\._-]+" 
                               title="Povolené znaky: písmena, číslice, tečka, podtržítko, pomlčka"
                               value="<?= htmlspecialchars($_POST['reg_username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="reg_full_name">Celé jméno:</label>
                        <input type="text" id="reg_full_name" name="reg_full_name" required 
                               placeholder="Jan Novák"
                               value="<?= htmlspecialchars($_POST['reg_full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="reg_password">Heslo:</label>
                        <input type="password" id="reg_password" name="reg_password" required 
                               minlength="4" 
                               placeholder="Minimálně 4 znaky">
                    </div>
                    <button type="submit" class="login-btn">📝 Registrovat se</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Server Status -->
        <div class="server-status">
            <h2><span class="status-indicator"></span>Stav serveru</h2>
            <p>Server běží správně a všechny služby jsou dostupné</p>
            
            <div class="system-info">
                <div class="info-item">
                    <h4>IP Adresa</h4>
                    <p>192.168.30.201</p>
                </div>
                <div class="info-item">
                    <h4>Systém</h4>
                    <p>Raspberry Pi OS</p>
                </div>
                <div class="info-item">
                    <h4>Web Server</h4>
                    <p>Apache + PHP</p>
                </div>
                <div class="info-item">
                    <h4>Databáze</h4>
                    <p>MySQL/MariaDB</p>
                </div>
            </div>
        </div>

        <!-- Services Grid -->
        <div class="services-grid">
            <!-- Pizza Application -->
            <?php if (canAccessTab('restaurant', $user_role)): ?>
            <a href="/pizza/" class="service-card pizza">
                <span class="service-icon">🍕</span>
                <h3>Restaurační systém</h3>
                <p>Kompletní restaurační systém pro objednávky, menu a správu. Včetně připojení k tiskárně pro účtenky.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Dashboard -->
            <?php if (canAccessTab('status', $user_role)): ?>
            <a href="/pizza/status_dashboard.php" class="service-card pizza">
                <span class="service-icon">🍽️</span>
                <h3>Aktuální stav</h3>
                <p>Zobrazuje aktuální stav objednávek, kolik zbýva kusů těsta a zbývající burratu.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Rezervace -->
            <?php if (canAccessTab('reservations', $user_role)): ?>
            <a href="/pizza/reservations.php" class="service-card pizza">
                <span class="service-icon">📅</span>
                <h3>Rezervace</h3>
                <p>Rezervační systém.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Objednavky -->
            <?php if (canAccessTab('orders', $user_role)): ?>
            <a href="/pizza/orders_system.php" class="service-card pizza">
                <span class="service-icon">🛒</span>
                <h3>Objednávky</h3>
                <p>Objednávkový systém pro příjetí objednávky.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Směny -->
            <?php if (canAccessTab('shifts', $user_role)): ?>
            <a href="/pizza/shifts_system.php" class="service-card pizza">
                <span class="service-icon">⏰</span>
                <h3>Směny</h3>
                <p>Výběr směn. ROZPRACOVÁNO</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Výplaty -->
            <?php if (canAccessTab('payroll', $user_role)): ?>
            <a href="/pizza/payroll_system.php" class="service-card pizza">
                <span class="service-icon">💰</span>
                <h3>Mzdy</h3>
                <p>Mzdy. ROZPRACOVÁNO</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- NEW: Financial Tracking -->
            <?php if (canAccessTab('finance', $user_role)): ?>
            <a href="/finance/" class="service-card finance">
                <span class="service-icon">📊</span>
                <h3>Finanční sledování</h3>
                <p>Sledování příjmů a výdajů, finanční přehledy a analýzy hospodaření restaurace.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Statistics -->
            <?php if (canAccessTab('statistics', $user_role)): ?>
            <a href="/pizza/data.php" class="service-card statistics">
                <span class="service-icon">📈</span>
                <h3>Statistiky a Data</h3>
                <p>Přehled statistik serveru, analýza dat a reporting. Detailní grafy a metriky výkonu systému.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- User Management (Admin only) -->
            <?php if ($user_role === 'admin'): ?>
            <a href="admin/users_management.php" class="service-card system">
                <span class="service-icon">👥</span>
                <h3>Správa uživatelů</h3>
                <p>Správa uživatelských účtů, rolí a oprávnění. Přidávání nových uživatelů a úprava existujících.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Database Management -->
            <?php if (canAccessTab('phpmyadmin', $user_role)): ?>
            <a href="phpmyadmin_redirect.php" class="service-card database">
                <span class="service-icon">🗄️</span>
                <h3>phpMyAdmin</h3>
                <p>Správa MySQL databází, tabulek a dat. Webové rozhraní pro administraci databázového serveru.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- System Info -->
            <a href="#" class="service-card system" onclick="showSystemInfo()">
                <span class="service-icon">⚙️</span>
                <h3>Systémové informace</h3>
                <p>Monitoring výkonu, využití zdrojů a systémové statistiky Raspberry Pi serveru.</p>
                <span class="service-status status-online">● Online</span>
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2025 Raspberry Pi Server | Poslední aktualizace: 21.7.2025</p>
        </div>
    </div>

    <script>
        function showSystemInfo() {
            alert('Systémové informace:\n\n' +
                  '• Raspberry Pi 5\n' +
                  '• IP: 192.168.30.201\n' +
                  '• OS: Raspberry Pi OS\n' +
                  '• Služby: Apache, PHP, MySQL\n' +
                  '• Aplikace: Pizza Restaurant, phpMyAdmin, Statistiky');
        }

        function switchTab(tab) {
            // Hide all panels
            document.querySelectorAll('.form-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Hide all tabs
            document.querySelectorAll('.form-tab').forEach(tabEl => {
                tabEl.classList.remove('active');
            });
            
            // Show selected panel and tab
            document.getElementById(tab + '-panel').classList.add('active');
            event.target.classList.add('active');
        }

        // Animate cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.service-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(30px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 200);
            });
        });
    </script>
</body>
</html>