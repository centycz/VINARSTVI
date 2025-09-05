<?php
require_once 'auth_check.php';

// Přesměrování na phpMyAdmin (tvá správná URL)
$phpmyadmin_url = '/phpmyadmin/';

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přesměrování na phpMyAdmin</title>
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
        .redirect-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .manual-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
    <script>
        // Automatické přesměrování ihned
        setTimeout(function() {
            window.location.href = '<?= $phpmyadmin_url ?>';
        }, 500);
    </script>
</head>
<body>
    <div class="redirect-container">
        <h2>🗄️ Otevírám phpMyAdmin</h2>
        <div class="spinner"></div>
        <p>Přesměrovávám na phpMyAdmin...</p>
        <a href="<?= $phpmyadmin_url ?>" class="manual-link">
            Otevřít manuálně
        </a>
    </div>
</body>
</html>