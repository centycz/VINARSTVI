<?php
session_start();

// Database connection
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Databáze není dostupná: " . $e->getMessage());
}

// Check authentication - only admin can access
if (!isset($_SESSION['order_user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../auth_check.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    
    switch ($_POST['ajax_action']) {
        case 'toggle_status':
            $user_id = (int)$_POST['user_id'];
            $new_status = $_POST['status'] === 'active' ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE order_users SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                echo json_encode(['success' => true, 'message' => 'Stav uživatele byl změněn']);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Chyba databáze: ' . $e->getMessage()]);
            }
            break;
            
        case 'change_role':
            $user_id = (int)$_POST['user_id'];
            $new_role = $_POST['role'];
            
            if (!in_array($new_role, ['admin', 'ragazzi', 'user'])) {
                echo json_encode(['error' => 'Neplatná role']);
                break;
            }
            
            try {
                // Update role and is_admin flag
                $is_admin = $new_role === 'admin' ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE order_users SET user_role = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$new_role, $is_admin, $user_id]);
                echo json_encode(['success' => true, 'message' => 'Role uživatele byla změněna']);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Chyba databáze: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // Don't allow deleting current admin
            if ($user_id == $_SESSION['order_user_id']) {
                echo json_encode(['error' => 'Nemůžete smazat sebe sama']);
                break;
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM order_users WHERE id = ?");
                $stmt->execute([$user_id]);
                echo json_encode(['success' => true, 'message' => 'Uživatel byl smazán']);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Chyba databáze: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Handle form submissions
$message = '';
$error = '';

if ($_POST['action'] ?? '' === 'add_user') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Neplatný CSRF token';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        
        // Validation
        if (empty($username) || empty($password) || empty($full_name)) {
            $error = 'Uživatelské jméno, heslo a celé jméno jsou povinné';
        } elseif (!in_array($role, ['admin', 'ragazzi', 'user'])) {
            $error = 'Neplatná role';
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM order_users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->fetch()) {
                    $error = 'Uživatelské jméno již existuje';
                } else {
                    // Create user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $is_admin = $role === 'admin' ? 1 : 0;
                    
                    $stmt = $pdo->prepare("INSERT INTO order_users (username, password_hash, full_name, email, is_admin, user_role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $password_hash, $full_name, $email, $is_admin, $role]);
                    
                    $message = 'Uživatel byl úspěšně vytvořen';
                }
            } catch (PDOException $e) {
                $error = 'Chyba databáze: ' . $e->getMessage();
            }
        }
    }
}

// Get users with filtering
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($role_filter && in_array($role_filter, ['admin', 'ragazzi', 'user'])) {
    $where_conditions[] = "user_role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR COALESCE(email, '') LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Check if columns exist first
    $columns = $pdo->query("SHOW COLUMNS FROM order_users")->fetchAll(PDO::FETCH_COLUMN);
    $has_is_active = in_array('is_active', $columns);
    $has_email = in_array('email', $columns);
    $has_created_at = in_array('created_at', $columns);
    $has_user_role = in_array('user_role', $columns);
    
    $select_columns = "id, username, full_name";
    if ($has_email) $select_columns .= ", email";
    if ($has_user_role) $select_columns .= ", user_role";
    else $select_columns .= ", CASE WHEN is_admin = 1 THEN 'admin' ELSE 'user' END as user_role";
    if ($has_is_active) $select_columns .= ", is_active";
    else $select_columns .= ", 1 as is_active";
    if ($has_created_at) $select_columns .= ", created_at";
    else $select_columns .= ", NOW() as created_at";
    $select_columns .= ", is_admin";
    
    $stmt = $pdo->prepare("SELECT $select_columns FROM order_users $where_sql ORDER BY " . 
                         ($has_created_at ? "created_at" : "username") . " DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Chyba při načítání uživatelů: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa uživatelů - PdC Systém</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }

        .nav-links a:hover {
            background: #5a6fd8;
        }

        .nav-links a.danger {
            background: #e74c3c;
        }

        .nav-links a.danger:hover {
            background: #c0392b;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .controls {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .controls-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        .users-table-container {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-ragazzi {
            background: #ffc107;
            color: #212529;
        }

        .role-user {
            background: #28a745;
            color: white;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .add-user-form {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .add-user-form h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .add-user-form .form-group {
            margin-bottom: 15px;
        }

        .add-user-form .form-group input,
        .add-user-form .form-group select {
            width: 100%;
        }

        .message {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .users-table {
                font-size: 0.8rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 8px;
            }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 15px;
            width: 300px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .modal-content h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>👥 Správa uživatelů</h1>
            <div class="nav-links">
                <a href="../protected_statistics.php">📊 Statistiky</a>
                <a href="../index.php">🏠 Hlavní stránka</a>
                <a href="../auth_check.php?logout=1" class="danger">Odhlásit se</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            try {
                // Check if columns exist first
                $columns = $pdo->query("SHOW COLUMNS FROM order_users")->fetchAll(PDO::FETCH_COLUMN);
                $has_user_role = in_array('user_role', $columns);
                $has_is_active = in_array('is_active', $columns);
                
                if ($has_user_role && $has_is_active) {
                    $stmt = $pdo->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as admins,
                        SUM(CASE WHEN user_role = 'ragazzi' THEN 1 ELSE 0 END) as ragazzi,
                        SUM(CASE WHEN user_role = 'user' THEN 1 ELSE 0 END) as users,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                    FROM order_users");
                } else {
                    // Fallback for older database schema
                    $stmt = $pdo->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admins,
                        0 as ragazzi,
                        SUM(CASE WHEN is_admin = 0 THEN 1 ELSE 0 END) as users,
                        COUNT(*) as active
                    FROM order_users");
                }
                $stats = $stmt->fetch();
            } catch (PDOException $e) {
                $stats = ['total' => 0, 'admins' => 0, 'ragazzi' => 0, 'users' => 0, 'active' => 0];
            }
            ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Celkem uživatelů</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['admins'] ?></div>
                <div class="stat-label">Administrátoři</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['ragazzi'] ?></div>
                <div class="stat-label">Ragazzi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['users'] ?></div>
                <div class="stat-label">Běžní uživatelé</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active'] ?></div>
                <div class="stat-label">Aktivní</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <form method="GET" class="controls-row">
                <div class="form-group">
                    <label for="search">Hledat:</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Jméno, username, email...">
                </div>
                <div class="form-group">
                    <label for="role">Filtr role:</label>
                    <select id="role" name="role">
                        <option value="">Všechny role</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="ragazzi" <?= $role_filter === 'ragazzi' ? 'selected' : '' ?>>Ragazzi</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrovat</button>
                <a href="users_management.php" class="btn btn-warning">Reset</a>
            </form>
        </div>

        <div class="main-content">
            <!-- Users Table -->
            <div class="users-table-container">
                <h3>Seznam uživatelů</h3>
                
                <?php if ($message): ?>
                    <div class="message message-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message message-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Uživatelské jméno</th>
                            <th>Celé jméno</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Stav</th>
                            <th>Vytvořeno</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?= $user['id'] ?>">
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td>
                                <select class="role-select" data-user-id="<?= $user['id'] ?>">
                                    <option value="admin" <?= $user['user_role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="ragazzi" <?= $user['user_role'] === 'ragazzi' ? 'selected' : '' ?>>Ragazzi</option>
                                    <option value="user" <?= $user['user_role'] === 'user' ? 'selected' : '' ?>>User</option>
                                </select>
                            </td>
                            <td>
                                <button class="status-toggle btn btn-sm <?= ($user['is_active'] ?? 1) ? 'btn-success' : 'btn-danger' ?>" 
                                        data-user-id="<?= $user['id'] ?>" 
                                        data-status="<?= ($user['is_active'] ?? 1) ? 'active' : 'inactive' ?>">
                                    <?= ($user['is_active'] ?? 1) ? 'Aktivní' : 'Neaktivní' ?>
                                </button>
                            </td>
                            <td><?= date('d.m.Y', strtotime($user['created_at'] ?? 'now')) ?></td>
                            <td class="actions">
                                <?php if ($user['id'] != $_SESSION['order_user_id']): ?>
                                <button class="btn btn-danger btn-sm delete-user" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                                    Smazat
                                </button>
                                <?php else: ?>
                                <span style="color: #666; font-size: 0.8rem;">Vy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($users)): ?>
                    <p style="text-align: center; padding: 20px; color: #666;">
                        <?= $search || $role_filter ? 'Žádní uživatelé nevyhovují zadaným filtrům.' : 'Žádní uživatelé nenalezeni.' ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Add User Form -->
            <div class="add-user-form">
                <h3>Přidat nového uživatele</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label for="username">Uživatelské jméno *</label>
                        <input type="text" id="username" name="username" required placeholder="např. novak">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Heslo *</label>
                        <input type="password" id="password" name="password" required placeholder="Minimálně 6 znaků">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Celé jméno *</label>
                        <input type="text" id="full_name" name="full_name" required placeholder="Jan Novák">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="jan@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="ragazzi">Ragazzi</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        Vytvořit uživatele
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>Potvrzení akce</h3>
            <p id="confirmMessage"></p>
            <div class="modal-buttons">
                <button id="confirmYes" class="btn btn-danger">Ano</button>
                <button id="confirmNo" class="btn btn-primary">Ne</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        
        // Status toggle functionality
        document.querySelectorAll('.status-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const currentStatus = this.dataset.status;
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_status');
                formData.append('user_id', userId);
                formData.append('status', newStatus);
                formData.append('csrf_token', csrfToken);
                
                fetch('users_management.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.textContent = newStatus === 'active' ? 'Aktivní' : 'Neaktivní';
                        this.className = `status-toggle btn btn-sm ${newStatus === 'active' ? 'btn-success' : 'btn-danger'}`;
                        this.dataset.status = newStatus;
                        showMessage(data.message, 'success');
                    } else {
                        showMessage(data.error, 'error');
                    }
                })
                .catch(error => {
                    showMessage('Nastala chyba při komunikaci se serverem', 'error');
                });
            });
        });
        
        // Role change functionality
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', function() {
                const userId = this.dataset.userId;
                const newRole = this.value;
                
                const formData = new FormData();
                formData.append('ajax_action', 'change_role');
                formData.append('user_id', userId);
                formData.append('role', newRole);
                formData.append('csrf_token', csrfToken);
                
                fetch('users_management.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                    } else {
                        showMessage(data.error, 'error');
                        // Revert the select to previous value
                        location.reload();
                    }
                })
                .catch(error => {
                    showMessage('Nastala chyba při komunikaci se serverem', 'error');
                    location.reload();
                });
            });
        });
        
        // Delete user functionality
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const username = this.dataset.username;
                
                showConfirmModal(
                    `Opravdu chcete smazat uživatele "${username}"? Tato akce je nevratná.`,
                    () => {
                        const formData = new FormData();
                        formData.append('ajax_action', 'delete_user');
                        formData.append('user_id', userId);
                        formData.append('csrf_token', csrfToken);
                        
                        fetch('users_management.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.querySelector(`tr[data-user-id="${userId}"]`).remove();
                                showMessage(data.message, 'success');
                            } else {
                                showMessage(data.error, 'error');
                            }
                        })
                        .catch(error => {
                            showMessage('Nastala chyba při komunikaci se serverem', 'error');
                        });
                    }
                );
            });
        });
        
        // Utility functions
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message message-${type}`;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.users-table-container');
            container.insertBefore(messageDiv, container.querySelector('table'));
            
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
        
        function showConfirmModal(message, onConfirm) {
            const modal = document.getElementById('confirmModal');
            const messageEl = document.getElementById('confirmMessage');
            const yesBtn = document.getElementById('confirmYes');
            const noBtn = document.getElementById('confirmNo');
            
            messageEl.textContent = message;
            modal.style.display = 'block';
            
            yesBtn.onclick = () => {
                modal.style.display = 'none';
                onConfirm();
            };
            
            noBtn.onclick = () => {
                modal.style.display = 'none';
            };
            
            // Close modal when clicking outside
            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            };
        }
        
        // Auto-hide messages after 5 seconds
        document.querySelectorAll('.message').forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>