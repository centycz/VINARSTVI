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

// Redirect to new data.php location
header('Location: /pizza/data.php');
exit;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiky - Pizza Orders</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
        .stats-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .admin-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .admin-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 1rem;
            border-radius: 5px;
            text-align: center;
            transition: transform 0.2s;
        }
        .admin-link:hover {
            transform: translateY(-2px);
        }
        .iframe-container {
            margin-top: 2rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-iframe {
            width: 100%;
            height: 800px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Administrátorské statistiky</h1>
        <a href="/index.php?logout=1" class="logout-btn">Odhlásit se</a>
    </div>
    
    <div class="stats-container">
        <h2>Vítejte v admin sekci!</h2>
        <p>Přístup povolen pro uživatele: <strong><?= htmlspecialchars($full_name) ?></strong></p>
        <p>Role: <strong><?= ucfirst($user_role) ?></strong></p>
        <p>Datum a čas: <strong><?= date('Y-m-d H:i:s') ?></strong></p>
        
        <div class="admin-links">
            <?php if ($user_role === 'admin'): ?>
            <a href="admin/users_management.php" class="admin-link">
                👥 Správa uživatelů
            </a>
            <?php endif; ?>
            <a href="<?= $statistics_url ?>" target="_blank" class="admin-link">
                📈 Otevřít statistiky v novém okně
            </a>
            <a href="#stats-frame" class="admin-link" onclick="toggleStats()">
                📊 Zobrazit/skrýt statistiky zde
            </a>
        </div>
    </div>

    <div class="iframe-container" id="stats-frame" style="display: none;">
        <iframe src="<?= $statistics_url ?>" class="stats-iframe"></iframe>
    </div>

    <script>
        function toggleStats() {
            const frame = document.getElementById('stats-frame');
            if (frame.style.display === 'none') {
                frame.style.display = 'block';
                frame.scrollIntoView({ behavior: 'smooth' });
            } else {
                frame.style.display = 'none';
            }
        }
    </script>
</body>
</html>