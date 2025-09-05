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

// Check if user has permission to access finance system
if (!in_array($user_role, ['admin', 'ragazzi'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanční sledování - Příjmy a výdaje</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Finanční sledování</h1>
            <p>Sledování příjmů a výdajů - Pizza dal Cortile</p>
            
            <!-- User Info Section -->
            <div class="user-info-header">
                <div class="user-welcome">
                    🙋‍♂️ Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> 
                    <span class="user-role">(<?= ucfirst($user_role) ?>)</span>
                </div>
                <a href="/index.php" class="back-btn">← Zpět na hlavní stránku</a>
            </div>
        </header>

        <!-- Statistics Overview -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card balance">
                <div class="stat-value" id="totalBalance">0 Kč</div>
                <div class="stat-label">Celkový zůstatek</div>
            </div>
            <div class="stat-card income">
                <div class="stat-value" id="totalIncome">0 Kč</div>
                <div class="stat-label">Celkové příjmy</div>
            </div>
            <div class="stat-card expense">
                <div class="stat-value" id="totalExpenses">0 Kč</div>
                <div class="stat-label">Celkové výdaje</div>
            </div>
        </div>

        <!-- Add Transaction Form -->
        <div class="card">
            <h2>Přidat transakci</h2>
            <form id="transactionForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="type">Typ</label>
                        <select id="type" name="type" required>
                            <option value="">Vyberte typ</option>
                            <option value="income">Příjem</option>
                            <option value="expense">Výdaj</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount">Částka (Kč)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Kategorie</label>
                        <input type="text" id="category" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="date">Datum</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Popis</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn">Přidat transakci</button>
                <button type="button" class="btn btn-secondary" id="cancelEdit" style="display: none;">Zrušit úpravy</button>
            </form>
        </div>

        <!-- Filters -->
        <div class="card">
            <h2>Filtrování transakcí</h2>
            <div class="filters">
                <div class="form-group">
                    <label for="filterType">Typ</label>
                    <select id="filterType">
                        <option value="">Všechny</option>
                        <option value="income">Příjmy</option>
                        <option value="expense">Výdaje</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filterDateFrom">Od data</label>
                    <input type="date" id="filterDateFrom">
                </div>
                <div class="form-group">
                    <label for="filterDateTo">Do data</label>
                    <input type="date" id="filterDateTo">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-small" id="applyFilters">Filtrovat</button>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary btn-small" id="clearFilters">Vymazat</button>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="card">
            <h2>Seznam transakcí</h2>
            <div id="transactionsList">
                <div class="loading">Načítání transakcí...</div>
            </div>
        </div>

        <!-- Monthly Statistics -->
        <div class="card">
            <h2>Měsíční přehled</h2>
            <div class="monthly-chart" id="monthlyChart">
                <div class="loading">Načítání statistik...</div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upravit transakci</h3>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <form id="editTransactionForm">
                <input type="hidden" id="editId" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editType">Typ</label>
                        <select id="editType" name="type" required>
                            <option value="income">Příjem</option>
                            <option value="expense">Výdaj</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editAmount">Částka (Kč)</label>
                        <input type="number" id="editAmount" name="amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editCategory">Kategorie</label>
                        <input type="text" id="editCategory" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="editDate">Datum</label>
                        <input type="date" id="editDate" name="date" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editDescription">Popis</label>
                    <textarea id="editDescription" name="description" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn">Uložit změny</button>
            </form>
        </div>
    </div>

    <!-- Alert Messages -->
    <div id="alertContainer"></div>

    <script>
        // Pass PHP session data to JavaScript
        window.userData = {
            username: '<?= htmlspecialchars($user_name) ?>',
            fullName: '<?= htmlspecialchars($full_name) ?>',
            role: '<?= htmlspecialchars($user_role) ?>'
        };
    </script>
    <script src="js/app.js"></script>
</body>
</html>