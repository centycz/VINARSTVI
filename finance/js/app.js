class FinanceApp {
    constructor() {
        this.currentEditId = null;
        this.transactions = [];
        this.stats = null;
        
        this.initializeApp();
        this.attachEventListeners();
        this.setDefaultDate();
    }

    initializeApp() {
        this.loadTransactions();
        this.loadStatistics();
    }

    attachEventListeners() {
        // Form submission
        document.getElementById('transactionForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        // Edit form submission
        document.getElementById('editTransactionForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleEditSubmit();
        });

        // Filter controls
        document.getElementById('applyFilters').addEventListener('click', () => {
            this.applyFilters();
        });

        document.getElementById('clearFilters').addEventListener('click', () => {
            this.clearFilters();
        });

        // Modal controls
        document.getElementById('closeModal').addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('cancelEdit').addEventListener('click', () => {
            this.resetForm();
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('editModal');
            if (e.target === modal) {
                this.closeModal();
            }
        });
    }

    setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date').value = today;
    }

    async loadTransactions(filters = {}) {
        try {
            let url = 'api/transactions.php';
            const params = new URLSearchParams();
            
            if (filters.type) params.append('type', filters.type);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);
            
            if (params.toString()) {
                url += '?' + params.toString();
            }

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this.transactions = data.data;
                this.renderTransactions();
            } else {
                this.showAlert('Chyba při načítání transakcí: ' + data.message, 'error');
            }
        } catch (error) {
            this.showAlert('Chyba při načítání transakcí: ' + error.message, 'error');
        }
    }

    async loadStatistics() {
        try {
            const response = await fetch('api/stats.php');
            const data = await response.json();

            if (data.success) {
                this.stats = data.data;
                this.renderStatistics();
                this.renderMonthlyChart();
            } else {
                this.showAlert('Chyba při načítání statistik: ' + data.message, 'error');
            }
        } catch (error) {
            this.showAlert('Chyba při načítání statistik: ' + error.message, 'error');
        }
    }

    renderStatistics() {
        if (!this.stats) return;

        const { overview } = this.stats;
        
        document.getElementById('totalBalance').textContent = this.formatCurrency(overview.balance);
        document.getElementById('totalIncome').textContent = this.formatCurrency(overview.totalIncome);
        document.getElementById('totalExpenses').textContent = this.formatCurrency(overview.totalExpenses);
        
        // Update balance color based on positive/negative
        const balanceCard = document.querySelector('.stat-card.balance .stat-value');
        balanceCard.style.color = overview.balance >= 0 ? '#28a745' : '#dc3545';
    }

    renderTransactions() {
        const container = document.getElementById('transactionsList');
        
        if (this.transactions.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>Žádné transakce</h3>
                    <p>Zatím nemáte žádné transakce.</p>
                </div>
            `;
            return;
        }

        const table = document.createElement('table');
        table.className = 'transactions-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Typ</th>
                    <th>Popis</th>
                    <th>Kategorie</th>
                    <th>Částka</th>
                    <th>Vložil uživatel</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                ${this.transactions.map(transaction => this.renderTransactionRow(transaction)).join('')}
            </tbody>
        `;

        container.innerHTML = '';
        container.appendChild(table);
    }

    renderTransactionRow(transaction) {
        const typeLabel = transaction.type === 'income' ? 'Příjem' : 'Výdaj';
        const amountClass = transaction.type === 'income' ? 'income' : 'expense';
        const amountPrefix = transaction.type === 'income' ? '+' : '-';
        const userCreated = transaction.user_created || 'Neznámý';
        
        return `
            <tr>
                <td>${this.formatDate(transaction.date)}</td>
                <td>
                    <span class="transaction-type ${transaction.type}">${typeLabel}</span>
                </td>
                <td>${this.escapeHtml(transaction.description)}</td>
                <td>${this.escapeHtml(transaction.category)}</td>
                <td class="transaction-amount ${amountClass}">
                    ${amountPrefix}${this.formatCurrency(Math.abs(transaction.amount))}
                </td>
                <td class="user-created">${this.escapeHtml(userCreated)}</td>
                <td>
                    <div class="actions">
                        <button class="btn btn-small btn-secondary" onclick="app.editTransaction(${transaction.id})">
                            Upravit
                        </button>
                        <button class="btn btn-small" style="background: #dc3545;" onclick="app.deleteTransaction(${transaction.id})">
                            Smazat
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    renderMonthlyChart() {
        if (!this.stats) return;

        const container = document.getElementById('monthlyChart');
        const { monthly } = this.stats;

        if (monthly.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>Žádná data pro měsíční přehled</p></div>';
            return;
        }

        const monthsWithData = monthly.filter(month => month.income > 0 || month.expenses > 0);
        
        if (monthsWithData.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>Žádná data pro měsíční přehled</p></div>';
            return;
        }

        container.innerHTML = monthsWithData.map(month => `
            <div class="monthly-item">
                <div class="month-name">${month.monthName}</div>
                <div class="month-stats">
                    <div class="month-stat">
                        <div class="value" style="color: #28a745;">+${this.formatCurrency(month.income)}</div>
                        <div class="label">Příjmy</div>
                    </div>
                    <div class="month-stat">
                        <div class="value" style="color: #dc3545;">-${this.formatCurrency(month.expenses)}</div>
                        <div class="label">Výdaje</div>
                    </div>
                    <div class="month-stat">
                        <div class="value" style="color: ${month.balance >= 0 ? '#28a745' : '#dc3545'};">
                            ${this.formatCurrency(month.balance)}
                        </div>
                        <div class="label">Zůstatek</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async handleFormSubmit() {
        const formData = new FormData(document.getElementById('transactionForm'));
        const data = Object.fromEntries(formData.entries());

        // Validate form
        if (!this.validateForm(data)) {
            return;
        }

        try {
            const response = await fetch('api/transactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Transakce byla úspěšně přidána', 'success');
                this.resetForm();
                this.loadTransactions();
                this.loadStatistics();
            } else {
                this.showAlert('Chyba při přidávání transakce: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Chyba při přidávání transakce: ' + error.message, 'error');
        }
    }

    async handleEditSubmit() {
        const formData = new FormData(document.getElementById('editTransactionForm'));
        const data = Object.fromEntries(formData.entries());

        // Validate form
        if (!this.validateForm(data)) {
            return;
        }

        try {
            const response = await fetch('api/transactions.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Transakce byla úspěšně upravena', 'success');
                this.closeModal();
                this.loadTransactions();
                this.loadStatistics();
            } else {
                this.showAlert('Chyba při úpravě transakce: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Chyba při úpravě transakce: ' + error.message, 'error');
        }
    }

    editTransaction(id) {
        const transaction = this.transactions.find(t => t.id == id);
        if (!transaction) {
            this.showAlert('Transakce nebyla nalezena', 'error');
            return;
        }

        // Fill edit form
        document.getElementById('editId').value = transaction.id;
        document.getElementById('editType').value = transaction.type;
        document.getElementById('editAmount').value = transaction.amount;
        document.getElementById('editCategory').value = transaction.category;
        document.getElementById('editDate').value = transaction.date;
        document.getElementById('editDescription').value = transaction.description;

        // Show modal
        document.getElementById('editModal').style.display = 'block';
    }

    async deleteTransaction(id) {
        if (!confirm('Opravdu chcete smazat tuto transakci?')) {
            return;
        }

        try {
            const response = await fetch('api/transactions.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id })
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Transakce byla úspěšně smazána', 'success');
                this.loadTransactions();
                this.loadStatistics();
            } else {
                this.showAlert('Chyba při mazání transakce: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Chyba při mazání transakce: ' + error.message, 'error');
        }
    }

    applyFilters() {
        const filters = {};
        
        const type = document.getElementById('filterType').value;
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;

        if (type) filters.type = type;
        if (dateFrom) filters.date_from = dateFrom;
        if (dateTo) filters.date_to = dateTo;

        this.loadTransactions(filters);
    }

    clearFilters() {
        document.getElementById('filterType').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        this.loadTransactions();
    }

    validateForm(data) {
        if (!data.type || !data.amount || !data.description || !data.category || !data.date) {
            this.showAlert('Všechna pole jsou povinná', 'error');
            return false;
        }

        if (isNaN(data.amount) || parseFloat(data.amount) <= 0) {
            this.showAlert('Částka musí být kladné číslo', 'error');
            return false;
        }

        return true;
    }

    resetForm() {
        document.getElementById('transactionForm').reset();
        this.setDefaultDate();
        document.getElementById('cancelEdit').style.display = 'none';
        this.currentEditId = null;
    }

    closeModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editTransactionForm').reset();
    }

    showAlert(message, type) {
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;

        container.appendChild(alert);

        // Remove alert after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('cs-CZ', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount) + ' Kč';
    }

    formatDate(dateString) {
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString('cs-CZ');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new FinanceApp();
});