<?php
session_start();

// Připojení k databázi
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Chyba připojení: " . $e->getMessage());
}

// Přesměrování pokud už je přihlášen
if (isset($_SESSION['order_user'])) {
    header("Location: orders_system.php");
    exit;
}

// Zpracování akcí
if ($_POST['action'] ?? false) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if ($username && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM order_users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Debug info - smaž po otestování
                    $password_valid = password_verify($password, $user['password_hash']);
                    
                    if ($password_valid) {
                        $_SESSION['order_user'] = $user['username'];
                        $_SESSION['order_user_id'] = $user['id'];
                        $_SESSION['order_full_name'] = $user['full_name'];
                        $_SESSION['is_admin'] = (bool)$user['is_admin'];
                        
                        $_SESSION['success_message'] = "✅ Úspěšně přihlášen jako {$user['full_name']}!";
                        header("Location: orders_system.php");
                        exit;
                    } else {
                        $error_message = "❌ Nesprávné heslo!";
                    }
                } else {
                    $error_message = "❌ Uživatel neexistuje!";
                }
            } catch(PDOException $e) {
                $error_message = "❌ Chyba databáze: " . $e->getMessage();
            }
        } else {
            $error_message = "❌ Vyplňte všechna pole!";
        }
    }
    
    if ($_POST['action'] === 'register') {
        $username = trim($_POST['reg_username']);
        $password = $_POST['reg_password'];
        $full_name = trim($_POST['reg_full_name']);
        
        if ($username && $password && $full_name) {
            try {
                // Kontrola jestli už uživatel existuje
                $stmt = $pdo->prepare("SELECT id FROM order_users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->fetch()) {
                    $error_message = "❌ Uživatel s tímto jménem už existuje!";
                } else {
                    // Vytvoření nového uživatele
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO order_users (username, password_hash, full_name, is_admin) 
                        VALUES (?, ?, ?, FALSE)
                    ");
                    $stmt->execute([$username, $password_hash, $full_name]);
                    
                    $success_message = "✅ Účet byl vytvořen! Můžete se přihlásit s uživatelským jménem: <strong>{$username}</strong>";
                }
            } catch(PDOException $e) {
                $error_message = "❌ Chyba při registraci: " . $e->getMessage();
            }
        } else {
            $error_message = "❌ Vyplňte všechna pole!";
        }
    }
}

// Načtení existujících uživatelů pro demo
try {
    $existing_users = $pdo->query("SELECT username, full_name, is_admin FROM order_users ORDER BY username")->fetchAll();
} catch(PDOException $e) {
    $existing_users = [];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Firemní objednávky</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
        }

        .form-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .form-tab.active {
            background: #667eea;
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
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

        .form-panel {
            display: none;
        }

        .form-panel.active {
            display: block;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .guest-access {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
        }

        .guest-access h3 {
            color: #495057;
            margin-bottom: 10px;
        }

        .guest-access p {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .existing-users {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .existing-users h4 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .user-list {
            font-size: 0.8rem;
            color: #424242;
        }

        .user-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #e1f5fe;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .admin-badge {
            background: #f44336;
            color: white;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
        }

        .password-hint {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #bf6000;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🛒 Firemní objednávky</h1>
            <p>Přihlaste se pro správu objednávek</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="form-tabs">
            <div class="form-tab active" onclick="switchTab('login')">Přihlášení</div>
            <div class="form-tab" onclick="switchTab('register')">Registrace</div>
        </div>

        <!-- PŘIHLÁŠENÍ -->
        <div id="login-panel" class="form-panel active">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label>Uživatelské jméno:</label>
                    <input type="text" name="username" required placeholder="např. centycz" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Heslo:</label>
                    <input type="password" name="password" required placeholder="Zadejte heslo">
                </div>

                <button type="submit" class="btn btn-primary">
                    🔓 Přihlásit se
                </button>
            </form>

        <!-- REGISTRACE -->
        <div id="register-panel" class="form-panel">
            <form method="POST">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label>Uživatelské jméno:</label>
                    <input type="text" name="reg_username" required placeholder="např. jan.novak" 
                           pattern="[a-zA-Z0-9._-]+" title="Povolené znaky: písmena, číslice, tečka, podtržítko, pomlčka">
                </div>

                <div class="form-group">
                    <label>Celé jméno:</label>
                    <input type="text" name="reg_full_name" required placeholder="Jan Novák">
                </div>

                <div class="form-group">
                    <label>Heslo:</label>
                    <input type="password" name="reg_password" required minlength="4" placeholder="Minimálně 4 znaky">
                </div>

                <button type="submit" class="btn btn-primary">
                    📝 Registrovat se
                </button>
            </form>
        </div>

        <!-- GUEST PŘÍSTUP -->
        <div class="guest-access">
            <h3>👁️ Náhled pro hosty</h3>
            <p>Prohlédněte si objednávky bez možnosti editace</p>
            <a href="orders_system.php?guest=1" class="btn btn-secondary">
                👀 Pokračovat jako host
            </a>
        </div>

        <!-- SEZNAM EXISTUJÍCÍCH UŽIVATELŮ -->
        <?php if (!empty($existing_users)): ?>
        <div class="existing-users">
            <h4>👥 Registrovaní uživatelé:</h4>
            <div class="user-list">
                <?php foreach ($existing_users as $user): ?>
                    <div class="user-item">
                        <span><strong><?= htmlspecialchars($user['username']) ?></strong> - <?= htmlspecialchars($user['full_name']) ?></span>
                        <?php if ($user['is_admin']): ?>
                            <span class="admin-badge">ADMIN</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="../">← Zpět na hlavní stránku</a>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Skrýt všechny panely
            document.querySelectorAll('.form-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Skrýt všechny taby
            document.querySelectorAll('.form-tab').forEach(tabEl => {
                tabEl.classList.remove('active');
            });
            
            // Zobrazit vybraný panel a tab
            document.getElementById(tab + '-panel').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>