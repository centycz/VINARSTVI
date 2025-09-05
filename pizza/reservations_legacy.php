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
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervace stolů - Restaurant System</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }

         .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .nav-link {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: black;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .nav-link.active { background: white; color: #ff6b6b; }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
       
        .tab {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: white;
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }

        .tab:hover {
            background: #e9ecef;
        }

        .content {
            display: none;
            padding: 30px;
        }

        .content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .required {
            color: #e74c3c;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-right: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .reservation-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #667eea;
            transition: transform 0.2s ease;
        }

        .reservation-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reservation-time {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
        }

        .status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .table-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .table-card:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .table-card.reserved {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .table-card.occupied {
            background: #f8d7da;
            border-color: #dc3545;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #333;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .modal-content {
                width: 95%;
                margin: 2% auto;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍽️ Rezervace stolů</h1>
            <p>Systém pro správu rezervací restaurace</p>
        </div>
        <div class="nav-links">
            <a href="../index.php" class="nav-link" id="nav-obsluha">Zpět</a>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('new-reservation')">➕ Nová rezervace</button>
            <button class="tab" onclick="showTab('reservations-list')">📋 Seznam rezervací</button>
            <button class="tab" onclick="showTab('tables-overview')">🪑 Přehled stolů</button>
        </div>

        <!-- Nová rezervace -->
        <div id="new-reservation" class="content active">
            <h2>Nová rezervace</h2>
            <div id="alert-container"></div>
            
            <form id="reservation-form">
                <div class="form-row">
                    <div class="form-col">
                        <label>Jméno zákazníka <span class="required">*</span></label>
                        <input type="text" id="customer_name" required>
                    </div>
                    <div class="form-col">
                        <label>Telefonní číslo <span class="required">*</span></label>
                        <input type="tel" id="phone" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Email</label>
                        <input type="email" id="email">
                    </div>
                    <div class="form-col">
                        <label>Počet osob <span class="required">*</span></label>
                        <select id="party_size" required>
                            <option value="">Vyberte počet osob</option>
                            <option value="1">1 osoba</option>
                            <option value="2">2 osoby</option>
                            <option value="3">3 osoby</option>
                            <option value="4">4 osoby</option>
                            <option value="5">5 osob</option>
                            <option value="6">6 osob</option>
                            <option value="7">7 osob</option>
                            <option value="8">8 osob</option>
                            <option value="9">9 osob</option>
                            <option value="10">10 osob</option>
                            <option value="11">11 osob</option>
                            <option value="12">12 osob</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Datum rezervace <span class="required">*</span></label>
                        <input type="date" id="reservation_date" required>
                    </div>
                    <div class="form-col">
                        <label>Čas rezervace <span class="required">*</span></label>
                        <select id="reservation_time" required>
                            <option value="">Vyberte čas</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Stůl</label>
                        <select id="table_number">
                            <option value="">Automatické přiřazení</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label>Poznámka</label>
                        <textarea id="notes" rows="3" placeholder="Speciální požadavky, alergie..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn">💾 Uložit rezervaci</button>
            </form>
        </div>

        <!-- Seznam rezervací -->
        <div id="reservations-list" class="content">
            <h2>Seznam rezervací</h2>
            
            <div class="filters">
                <div>
                    <label>Datum:</label>
                    <input type="date" id="filter-date">
                </div>
                <div>
                    <label>Stav:</label>
                    <select id="filter-status">
                        <option value="">Všechny</option>
                        <option value="pending">Čekající</option>
                        <option value="confirmed">Potvrzené</option>
                        <option value="cancelled">Zrušené</option>
                    </select>
                </div>
                <button class="btn" onclick="loadReservations()">🔍 Filtrovat</button>
            </div>

            <div id="reservations-container">
                <!-- Rezervace se načtou zde -->
            </div>
        </div>

        <!-- Přehled stolů -->
        <div id="tables-overview" class="content">
            <h2>Přehled stolů</h2>
            
            <div class="filters">
                <div>
                    <label>Datum:</label>
                    <input type="date" id="tables-filter-date">
                </div>
                <button class="btn" onclick="loadTablesWithReservations()">🔍 Filtrovat</button>
            </div>
            
            <div id="tables-container" class="table-grid">
                <!-- Stoly se načtou zde -->
            </div>
        </div>
    </div>

    <!-- Modal pro editaci rezervace -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Upravit rezervaci</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="edit-reservation-form">
                <input type="hidden" id="edit_reservation_id">
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Jméno zákazníka <span class="required">*</span></label>
                        <input type="text" id="edit_customer_name" required>
                    </div>
                    <div class="form-col">
                        <label>Telefonní číslo <span class="required">*</span></label>
                        <input type="tel" id="edit_phone" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Email</label>
                        <input type="email" id="edit_email">
                    </div>
                    <div class="form-col">
                        <label>Počet osob <span class="required">*</span></label>
                        <select id="edit_party_size" required>
                            <option value="">Vyberte počet osob</option>
                            <option value="1">1 osoba</option>
                            <option value="2">2 osoby</option>
                            <option value="3">3 osoby</option>
                            <option value="4">4 osoby</option>
                            <option value="5">5 osob</option>
                            <option value="6">6 osob</option>
                            <option value="7">7 osob</option>
                            <option value="8">8 osob</option>
                            <option value="9">9 osob</option>
                            <option value="10">10 osob</option>
                            <option value="11">11 osob</option>
                            <option value="12">12 osob</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Datum rezervace <span class="required">*</span></label>
                        <input type="date" id="edit_reservation_date" required>
                    </div>
                    <div class="form-col">
                        <label>Čas rezervace <span class="required">*</span></label>
                        <select id="edit_reservation_time" required>
                            <option value="">Vyberte čas</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Stůl</label>
                        <select id="edit_table_number">
                            <option value="">Automatické přiřazení</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label>Poznámka</label>
                        <textarea id="edit_notes" rows="3" placeholder="Speciální požadavky, alergie..."></textarea>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Zrušit</button>
                    <button type="submit" class="btn">💾 Uložit změny</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Globální proměnné
        let currentEditingReservation = null;
        let allTables = [];

        // Inicializace při načtení stránky
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });

        function initializePage() {
            generateTimeSlots();
            generateEditTimeSlots();
            loadTables();
            setDefaultDate();
            
            // Načti rezervace pro dnešní datum
            document.getElementById('filter-date').value = new Date().toISOString().split('T')[0];
            loadReservations();
            
            // Nastav defaultní datum pro přehled stolů
            document.getElementById('tables-filter-date').value = new Date().toISOString().split('T')[0];
            loadTablesWithReservations();
        }

        function setDefaultDate() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('reservation_date').value = today;
            document.getElementById('filter-date').value = today;
        }

        function generateTimeSlots() {
            const timeSelect = document.getElementById('reservation_time');
            timeSelect.innerHTML = '<option value="">Vyberte čas</option>';
            
            // Generuj časové sloty od 10:00 do 22:00 po 15 minutách
            for (let hour = 10; hour <= 22; hour++) {
                for (let minute = 0; minute < 60; minute += 15) {
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    const option = document.createElement('option');
                    option.value = timeString;
                    option.textContent = timeString;
                    timeSelect.appendChild(option);
                }
            }
        }

        function generateEditTimeSlots() {
            const timeSelect = document.getElementById('edit_reservation_time');
            timeSelect.innerHTML = '<option value="">Vyberte čas</option>';
            
            // Generuj časové sloty od 10:00 do 22:00 po 15 minutách
            for (let hour = 10; hour <= 22; hour++) {
                for (let minute = 0; minute < 60; minute += 15) {
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    const option = document.createElement('option');
                    option.value = timeString;
                    option.textContent = timeString;
                    timeSelect.appendChild(option);
                }
            }
        }

        function showTab(tabName) {
            // Skryj všechny obsahy
            const contents = document.querySelectorAll('.content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Odeber aktivní třídu ze všech tabů
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Zobraz vybraný obsah
            document.getElementById(tabName).classList.add('active');
            
            // Aktivuj příslušný tab
            event.target.classList.add('active');
            
            // Načti data podle tabu
            if (tabName === 'reservations-list') {
                loadReservations();
            } else if (tabName === 'tables-overview') {
                loadTablesWithReservations();
            }
        }

        async function loadTables() {
            try {
                const response = await fetch('api/restaurant-api.php?action=tables');
                const data = await response.json();
                
                if (data.success) {
                    allTables = data.data.tables;
                    populateTableSelects();
                }
            } catch (error) {
                console.error('Chyba při načítání stolů:', error);
            }
        }

        function populateTableSelects() {
            const tableSelects = ['table_number', 'edit_table_number'];
            
            tableSelects.forEach(selectId => {
                const tableSelect = document.getElementById(selectId);
                tableSelect.innerHTML = '<option value="">Automatické přiřazení</option>';
                
                allTables.forEach(table => {
                    const option = document.createElement('option');
                    option.value = table.table_number;
                    option.textContent = table.table_code || `Stůl ${table.table_number}`;
                    tableSelect.appendChild(option);
                });
            });
        }

        // Odeslání formuláře rezervace
        document.getElementById('reservation-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                customer_name: document.getElementById('customer_name').value,
                phone: document.getElementById('phone').value,
                email: document.getElementById('email').value,
                party_size: document.getElementById('party_size').value,
                reservation_date: document.getElementById('reservation_date').value,
                reservation_time: document.getElementById('reservation_time').value,
                table_number: document.getElementById('table_number').value || null,
                notes: document.getElementById('notes').value
            };

            try {
                const response = await fetch('api/restaurant-api.php?action=add-reservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showAlert('✅ Rezervace byla úspěšně vytvořena!', 'success');
                    document.getElementById('reservation-form').reset();
                    setDefaultDate();
                    loadReservations(); // Refresh the list
                } else {
                    showAlert('❌ Chyba: ' + result.error, 'error');
                }
            } catch (error) {
                showAlert('❌ Chyba při ukládání rezervace: ' + error.message, 'error');
            }
        });

        // Odeslání formuláře editace rezervace
        document.getElementById('edit-reservation-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                id: document.getElementById('edit_reservation_id').value,
                customer_name: document.getElementById('edit_customer_name').value,
                phone: document.getElementById('edit_phone').value,
                email: document.getElementById('edit_email').value,
                party_size: document.getElementById('edit_party_size').value,
                reservation_date: document.getElementById('edit_reservation_date').value,
                reservation_time: document.getElementById('edit_reservation_time').value,
                table_number: document.getElementById('edit_table_number').value || null,
                notes: document.getElementById('edit_notes').value
            };

            try {
                const response = await fetch('api/restaurant-api.php?action=update-reservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showAlert('✅ Rezervace byla úspěšně upravena!', 'success');
                    closeEditModal();
                    loadReservations(); // Refresh the list
                } else {
                    showAlert('❌ Chyba: ' + result.error, 'error');
                }
            } catch (error) {
                showAlert('❌ Chyba při úpravě rezervace: ' + error.message, 'error');
            }
        });

        async function loadReservations() {
            const date = document.getElementById('filter-date').value;
            const status = document.getElementById('filter-status').value;
            
            let url = 'api/restaurant-api.php?action=get-reservations';
            if (date) url += `&date=${date}`;
            if (status) url += `&status=${status}`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayReservations(data.data);
                } else {
                    document.getElementById('reservations-container').innerHTML = '<p>Chyba při načítání rezervací.</p>';
                }
            } catch (error) {
                document.getElementById('reservations-container').innerHTML = '<p>Chyba při načítání rezervací.</p>';
            }
        }

        function displayReservations(reservations) {
            const container = document.getElementById('reservations-container');
            
            if (reservations.length === 0) {
                container.innerHTML = '<p>Žádné rezervace nenalezeny.</p>';
                return;
            }

            container.innerHTML = reservations.map(reservation => `
                <div class="reservation-card">
                    <div class="reservation-header">
                        <div class="reservation-time">${reservation.reservation_time} - ${reservation.customer_name}</div>
                        <div class="status ${reservation.status}">${getStatusText(reservation.status)}</div>
                    </div>
                    <div><strong>📞 Telefon:</strong> ${reservation.phone}</div>
                    ${reservation.email ? `<div><strong>📧 Email:</strong> ${reservation.email}</div>` : ''}
                    <div><strong>👥 Počet osob:</strong> ${reservation.party_size}</div>
                    <div><strong>🪑 Stůl:</strong> ${getTableDisplayName(reservation.table_number)}</div>
                    ${reservation.notes ? `<div><strong>📝 Poznámka:</strong> ${reservation.notes}</div>` : ''}
                    <div style="margin-top: 15px;">
                        <button class="btn btn-warning" onclick="editReservation(${reservation.id})">✏️ Upravit</button>
                        ${reservation.status !== 'confirmed' ? `<button class="btn btn-success" onclick="confirmReservation(${reservation.id})">✅ Potvrdit</button>` : ''}
                        <button class="btn btn-danger" onclick="deleteReservation(${reservation.id})">🗑️ Smazat</button>
                    </div>
                </div>
            `).join('');
        }

        function getTableDisplayName(tableNumber) {
            if (!tableNumber) return 'Automatické přiřazení';
            
            const table = allTables.find(t => t.table_number == tableNumber);
            return table ? (table.table_code || `Stůl ${tableNumber}`) : `Stůl ${tableNumber}`;
        }

        function getStatusText(status) {
            switch(status) {
                case 'pending': return 'Čeká';
                case 'confirmed': return 'Potvrzeno';
                case 'cancelled': return 'Zrušeno';
                default: return status;
            }
        }

        async function confirmReservation(id) {
            await updateReservationStatus(id, 'confirmed');
        }

        async function deleteReservation(id) {
            if (confirm('Opravdu chcete smazat tuto rezervaci? Tato akce je nevratná!')) {
                try {
                    const response = await fetch('api/restaurant-api.php?action=delete-reservation', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        loadReservations(); // Reload the list
                        showAlert('✅ Rezervace byla smazána!', 'success');
                    } else {
                        showAlert('❌ Chyba při mazání rezervace: ' + result.error, 'error');
                    }
                } catch (error) {
                    showAlert('❌ Chyba při mazání rezervace: ' + error.message, 'error');
                }
            }
        }

        async function updateReservationStatus(id, status) {
            try {
                const response = await fetch('api/restaurant-api.php?action=update-reservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        status: status
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    loadReservations(); // Reload the list
                    showAlert(`✅ Rezervace byla ${status === 'confirmed' ? 'potvrzena' : 'aktualizována'}!`, 'success');
                } else {
                    showAlert('❌ Chyba při aktualizaci rezervace: ' + result.error, 'error');
                }
            } catch (error) {
                showAlert('❌ Chyba při aktualizaci rezervace: ' + error.message, 'error');
            }
        }

        async function editReservation(reservationId) {
            try {
                // Načti detaily rezervace
                const response = await fetch(`api/restaurant-api.php?action=get-reservation-details&id=${reservationId}`);
                const data = await response.json();
                
                if (data.success) {
                    const reservation = data.data;
                    
                    // Naplň formulář
                    document.getElementById('edit_reservation_id').value = reservation.id;
                    document.getElementById('edit_customer_name').value = reservation.customer_name || '';
                    document.getElementById('edit_phone').value = reservation.phone || '';
                    document.getElementById('edit_email').value = reservation.email || '';
                    document.getElementById('edit_party_size').value = reservation.party_size || '';
                    document.getElementById('edit_reservation_date').value = reservation.reservation_date || '';
                    document.getElementById('edit_reservation_time').value = reservation.reservation_time || '';
                    document.getElementById('edit_table_number').value = reservation.table_number || '';
                    document.getElementById('edit_notes').value = reservation.notes || '';
                    
                    // Zobraz modal
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    showAlert('❌ Chyba při načítání rezervace: ' + data.error, 'error');
                }
            } catch (error) {
                showAlert('❌ Chyba při načítání rezervace: ' + error.message, 'error');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('edit-reservation-form').reset();
        }

        // Zavři modal při kliknutí mimo něj
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        async function loadTablesWithReservations() {
            const date = document.getElementById('tables-filter-date').value || new Date().toISOString().split('T')[0];
            
            try {
                const response = await fetch(`api/restaurant-api.php?action=tables-with-reservations&date=${date}`);
                const data = await response.json();
                
                if (data.success) {
                    displayTablesWithReservations(data.data);
                }
            } catch (error) {
                console.error('Chyba při načítání stolů s rezervacemi:', error);
            }
        }

        function displayTablesWithReservations(tables) {
            const container = document.getElementById('tables-container');
            
            container.innerHTML = tables.map(table => {
                let cardClass = 'table-card';
                let reservationInfo = '';
                
                if (table.reservations && table.reservations.length > 0) {
                    cardClass += ' reserved';
                    reservationInfo = table.reservations.map(res => {
                        const partyText = res.party_size > 1 ? `${res.party_size} osob` : '1 osoba';
                        return `<div>${res.reservation_time} - ${res.customer_name} (${partyText})</div>`;
                    }).join('');
                }
                
                if (table.status === 'occupied') {
                    cardClass += ' occupied';
                }

                return `
                    <div class="${cardClass}" onclick="showTableDetails(${table.table_number})">
                        <h3>${table.table_code || `Stůl ${table.table_number}`}</h3>
                        <div><strong>${table.status === 'occupied' ? '🔴 Obsazeno' : '🟢 Volno'}</strong></div>
                        ${reservationInfo}
                    </div>
                `;
            }).join('');
        }

        function showTableDetails(tableNumber) {
            // Implementace detailů stolu
            alert(`Detail stolu ${tableNumber} - implementovat podle potřeby`);
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            // Automaticky skryj alert po 5 sekundách
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>