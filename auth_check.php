<?php
session_start();

// Database connection
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $db_error = "Databáze není dostupná - přihlášení není možné.";
}

// Funkce pro ověření přihlášení
function checkAuth() {
    return isset($_SESSION['order_user']) && !empty($_SESSION['order_user']);
}

// Zpracování přihlášení
if (($_POST['password'] ?? false) && ($_POST['username'] ?? false)) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!isset($db_error)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM order_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables consistent with index.php
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
                
                header('Location: ' . $_POST['redirect_url']);
                exit;
            } else {
                $error = 'Nesprávné přihlašovací údaje!';
            }
        } catch(PDOException $e) {
            $error = 'Chyba databáze: ' . $e->getMessage();
        }
    } else {
        $error = $db_error;
    }
}

// Zpracování odhlášení
if ($_GET['logout'] ?? false) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Pokud není přihlášen, zobraz formulář
if (!checkAuth()) {
    // Správně načti redirect URL z GET parametru
    $redirect_url = $_GET['redirect'] ?? $_SERVER['REQUEST_URI'] ?? 'protected_statistics.php';
    
    // Pokud je redirect_url prázdný nebo obsahuje auth_check.php, nastav výchozí
    if (empty($redirect_url) || strpos($redirect_url, 'auth_check.php') !== false) {
        $redirect_url = 'protected_statistics.php';
    }
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Přihlášení - Admin</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .login-container {
                background: white;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 2rem;
                color: #333;
            }
            .form-group {
                margin-bottom: 1.5rem;
            }
            label {
                display: block;
                margin-bottom: 0.5rem;
                color: #555;
                font-weight: bold;
            }
            input[type="password"] {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 1rem;
                transition: border-color 0.3s;
                box-sizing: border-box;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            .login-btn {
                width: 100%;
                padding: 0.75rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 1rem;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .login-btn:hover {
                transform: translateY(-2px);
            }
            .error {
                color: #e74c3c;
                text-align: center;
                margin-bottom: 1rem;
                padding: 0.5rem;
                background: #ffeaea;
                border-radius: 5px;
            }
            .lock-icon {
                text-align: center;
                font-size: 3rem;
                margin-bottom: 1rem;
                color: #667eea;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="lock-icon">🔒</div>
            <div class="login-header">
                <h2>Administrátorské přihlášení</h2>
                <p>Vložte heslo pro přístup</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Uživatelské jméno:</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="např. admin">
                </div>
                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input type="password" id="password" name="password" required placeholder="Zadejte heslo">
                </div>
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($redirect_url) ?>">
                <button type="submit" class="login-btn">Přihlásit se</button>
            </form>
        </div>
        
        <script>
            // Automatické zaměření na username input
            document.getElementById('username').focus();
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>