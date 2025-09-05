<?php
session_start();
if (!isset($_SESSION['order_user'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Rezervace stolů - Timeline</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    min-height:100vh;
    padding:20px;
    color:#222;
}
.container { max-width:1700px; margin:0 auto; background:#fff; border-radius:15px; box-shadow:0 20px 40px rgba(0,0,0,0.1); overflow:hidden; }
.header { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:30px; text-align:center; }
.header h1 { font-size:2.35em; margin-bottom:10px; text-shadow:2px 2px 4px rgba(0,0,0,.35); font-weight:600; }
.header p { opacity:.9; letter-spacing:.3px; }
.nav-links { display:flex; justify-content:center; gap:15px; margin-top:18px; flex-wrap:wrap; }
.nav-link { padding:10px 20px; background:rgba(255,255,255,0.21); color:#fff; text-decoration:none; border-radius:25px; font-weight:500; transition:.3s; }
.nav-link:hover { background:rgba(255,255,255,0.34); transform:translateY(-2px); }

.main-content { display:flex; height:calc(100vh - 215px); min-height:640px; }
.left-panel { width:395px; padding:30px; border-right:1px solid #e9ecef; background:#f8f9fa; overflow-y:auto; }
.left-panel h3 { margin-bottom:20px; color:#667eea; font-size:20px; }

.edit-badge { display:none; background:#f39c12; color:#fff; font-size:12px; font-weight:600; padding:6px 10px; border-radius:6px; margin-bottom:15px; letter-spacing:.4px; }
.edit-badge.active { display:inline-block; }

.form-group { margin-bottom:18px; }
label { display:block; margin-bottom:6px; font-weight:600; color:#333; font-size:13px; }
.required { color:#e74c3c; }
input, select, textarea {
    width:100%; padding:11px 12px; border:2px solid #e3e7ee; border-radius:8px;
    font-size:14px; transition:border-color .25s, background .25s; background:#fff;
}
input:focus, select:focus, textarea:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,0.15); }
textarea { resize:vertical; }
.inline-help { font-size:11px; color:#666; margin-top:4px; min-height:14px; }

.btn { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; padding:12px 22px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:.2s; width:100%; margin-bottom:10px; letter-spacing:.4px; }
.btn:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,0.35); }
.btn-success { background:linear-gradient(135deg,#27ae60,#2ecc71); }
.btn-warning { background:linear-gradient(135deg,#f39c12,#e67e22); }
.btn-danger  { background:linear-gradient(135deg,#e74c3c,#c0392b); }
.btn-secondary { background:linear-gradient(135deg,#95a5a6,#7f8c8d); }
.btn-outline { background:#fff; color:#667eea; border:2px solid #667eea; width:auto; margin-bottom:0; padding:9px 18px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
.btn-outline:hover { background:#667eea; color:#fff; }
.small-btn { width:auto; font-size:12px; padding:7px 14px; }

.right-panel { flex:1; padding:24px 28px 28px 28px; overflow:auto; }

.timeline-controls { display:flex; gap:20px; margin-bottom:22px; align-items:stretch; width:100%; flex-wrap:wrap; }
.date-control, .opening-hours-form, .display-options {
    background:#f8f9fa; border:1px solid #dee2e6; border-radius:10px; padding:16px 18px;
    display:flex; flex-direction:column; flex:1 1 0; min-width:260px; position:relative;
}
.date-control label, .opening-hours-form header, .display-options header {
    font-weight:600; color:#444; margin-bottom:12px; display:flex; align-items:center; gap:6px; font-size:14px;
}
.date-row, .oh-row, .display-row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.date-row input[type=date] { flex:1; min-width:170px; }
.oh-row input[type=time] { flex:1; min-width:130px; padding:7px 10px; font-size:14px; }
.display-row button { flex:1; }

.legend { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.legend-item { display:flex; align-items:center; gap:4px; font-size:11px; background:#f1f1f1; padding:4px 8px; border-radius:5px; }
.legend-color { width:14px; height:14px; border-radius:3px; border:1px solid #888; }

.timeline-container { position:relative; overflow:auto; border:1px solid #d9dfe5; border-radius:10px; background:#fff; min-height:520px; }
.timeline { display:grid; min-width:1100px; position:relative; }
.time-header { background:#f1f3f6; border-bottom:2px solid #d3d8df; font-weight:600; padding:15px 5px; text-align:center; position:sticky; top:0; z-index:100; font-size:13px; }
.table-header { background:#e7ebf0; border-bottom:2px solid #d3d8df; font-weight:600; padding:15px 5px; text-align:center; position:sticky; top:0; z-index:100; font-size:13px; }
.time-slot { height:64px; border-right:1px solid #edf0f3; border-bottom:1px solid #edf0f3; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:500; background:#fafbfc; }
.table-slot { height:64px; border-right:1px solid #edf0f3; border-bottom:1px solid #edf0f3; position:relative; cursor:pointer; overflow:visible; }
.table-slot:hover { background:rgba(102,126,234,0.07); }

/* Flash při kliknutí */
.slot-flash { animation: slotFlash 1.2s ease-out; }
@keyframes slotFlash {
    0% { box-shadow: inset 0 0 0 0 rgba(56,161,105,0.9); background:rgba(56,161,105,0.18); }
    60% { box-shadow: inset 0 0 0 3px rgba(56,161,105,0.4); background:rgba(56,161,105,0.09); }
    100% { box-shadow: inset 0 0 0 0 rgba(56,161,105,0); background:transparent; }
}

:root {
    --res-font: 13px;
    --res-font-details: 12px;
    --res-font-name: 15px;
    --res-padding: 7px 8px 6px;
}
.reservation-block {
    position:absolute; left:3px; right:3px; top:3px; border-radius:7px; padding:var(--res-padding);
    font-size:var(--res-font); overflow:hidden; cursor:pointer; transition:transform .2s, box-shadow .2s; z-index:10;
    display:flex; flex-direction:column; gap:2px; line-height:1.22;
}
.reservation-block:hover { transform:scale(1.045); z-index:20; box-shadow:0 6px 15px rgba(0,0,0,0.18); }

.reservation-block.pending   { background:linear-gradient(135deg,#fff5d6,#ffe28a); border:1px solid #e0b832; color:#6b5200; }
.reservation-block.confirmed { background:linear-gradient(135deg,#d2f6f6,#a7f3e4); border:1px solid #22a06b; color:#0d5a3d; }
.reservation-block.seated    { background:linear-gradient(135deg,#d1ecf9,#9cd6ff); border:1px solid #1594c1; color:#0a4d60; }
.reservation-block.finished  { background:linear-gradient(135deg,#e6e8ea,#c9ccd1); border:1px solid #7d858d; color:#3e444b; }
.reservation-block.cancelled { background:linear-gradient(135deg,#f9d5d8,#f5b2b0); border:1px solid #d95b57; color:#6d1d19; text-decoration:line-through; }
.reservation-block.no_show   { background:linear-gradient(135deg,#f9d5d8,#f8d4a8); border:1px solid #d87a5d; color:#6d2d12; opacity:.85; }

.reservation-name { font-size:var(--res-font-name); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:.2px; }
.reservation-details { font-size:var(--res-font-details); opacity:.95; }

.big-reservations { --res-font: 15px; --res-font-details: 13px; --res-font-name: 17px; --res-padding: 9px 10px 7px; }
.big-reservations .reservation-block { line-height:1.26; }

.modal { display:none; position:fixed; z-index:1000; inset:0; background:rgba(0,0,0,0.55); }
.modal-content { background:#fff; margin:4% auto; padding:30px 32px; border-radius:18px; width:90%; max-width:580px;
    box-shadow:0 22px 44px rgba(0,0,0,0.35); animation:fadeIn .25s ease; }
@keyframes fadeIn { from{opacity:0; transform:translateY(12px);} to{opacity:1; transform:none;} }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:14px; }
.modal-title { font-size:20px; font-weight:600; color:#333; letter-spacing:.5px; }
.close { color:#888; font-size:30px; font-weight:bold; cursor:pointer; line-height:1; }
.close:hover { color:#333; }

.alert { padding:14px 16px; border-radius:8px; margin-bottom:18px; font-weight:500; font-size:14px; line-height:1.4; }
.alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.alert-warning { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }

.modal-actions { display:flex; gap:10px; margin-top:25px; flex-wrap:wrap; }
.modal-actions .btn { width:auto; flex:1; min-width:150px; margin-bottom:0; }

.status-label { padding:4px 8px; border-radius:6px; font-size:12px; font-weight:600; background:#eee; display:inline-block; }
.status-label.pending { background:#ffe08a; }
.status-label.confirmed { background:#a3e4a3; }
.status-label.seated { background:#9fd5f1; }
.status-label.finished { background:#c8c9ca; }
.status-label.cancelled { background:#f5a3a3; text-decoration:line-through; }
.status-label.no_show { background:#f8c291; }

.flex-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
#table-availability-hint { min-height:14px; }

.stats-panel { background:#f8f9fa; border:1px solid #dee2e6; border-radius:10px; padding:16px; margin:12px 0; }
.stats-header { display:flex; align-items:center; font-weight:600; color:#444; font-size:14px; margin-bottom:12px; }
.stats-content { display:flex; flex-direction:column; gap:16px; }
.stats-summary { display:flex; gap:16px; }
.stat-item { flex:1; background:rgba(102,126,234,0.1); border-radius:8px; padding:12px; text-align:center; min-width:80px; }
.stat-number { font-size:1.8em; font-weight:bold; color:#667eea; line-height:1.2; }
.stat-label { font-size:0.85em; color:#666; margin-top:4px; }
.stats-slots { border-top:1px solid #e9ecef; padding-top:12px; }
.slots-header { font-weight:600; color:#444; font-size:13px; margin-bottom:8px; }
.slots-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:8px; max-height:120px; overflow-y:auto; }
.slot-item { background:rgba(102,126,234,0.08); border-radius:6px; padding:6px 8px; text-align:center; font-size:11px; transition:background-color 0.2s; }
.slot-item.has-persons { background:rgba(102,126,234,0.15); font-weight:600; }
.slot-time { font-weight:bold; color:#444; }
.slot-persons { color:#667eea; font-size:10px; }
.slot-item.slot-cap-critical { background:rgba(220,53,69,0.15); border:2px solid rgba(220,53,69,0.3); font-weight:700; }
.slot-item.slot-cap-warning { background:rgba(255,193,7,0.15); border:2px solid rgba(255,193,7,0.3); font-weight:600; }
.slot-item.slot-cap-normal { background:rgba(40,167,69,0.15); border:2px solid rgba(40,167,69,0.3); }
.slot-capacity { color:#666; font-size:9px; margin-top:2px; }
.slot-item.slot-cap-critical .slot-capacity { color:#dc3545; font-weight:600; }
.slot-item.slot-cap-warning .slot-capacity { color:#ffc107; font-weight:600; }
.slot-item.slot-cap-normal .slot-capacity { color:#28a745; }

.stats-toggle-container { text-align:center; margin:8px 0; }

@media (max-width:1200px){
    .main-content { flex-direction:column; height:auto; }
    .left-panel { width:100%; border-right:none; border-bottom:1px solid #e9ecef; }
}
@media (max-width:768px){
    .container { margin:10px; }
    .left-panel { padding:22px; }
    .timeline { min-width:560px; }
    .timeline-controls { flex-direction:column; }
    .date-control, .opening-hours-form, .display-options { min-width:100%; }
}

/* ===== Drag & Drop styling ===== */
.dragging-reservation {
    opacity:.85;
    box-shadow:0 10px 25px rgba(0,0,0,0.35);
    cursor:grabbing!important;
    z-index:2000!important;
    pointer-events:none; /* klíčové */
}
.dnd-drop-target-ok {
    outline:3px solid #22a06b;
    outline-offset:-3px;
}
.dnd-drop-target-bad {
    outline:3px solid #d9534f;
    outline-offset:-3px;
}
.dnd-slot-preview {
    position:absolute;
    left:2px; right:2px; top:2px;
    border:2px dashed #22a06b;
    border-radius:7px;
    pointer-events:none;
    z-index:1500;
    background:repeating-linear-gradient(45deg,rgba(34,160,107,0.10),rgba(34,160,107,0.10) 6px,rgba(34,160,107,0.05) 6px,rgba(34,160,107,0.05) 12px);
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🍽️ Rezervace stolů - Timeline</h1>
        <p>Moderní systém + flexibilní délka</p>
        <div class="nav-links">
            <a href="../index.php" class="nav-link">Hlavní stránka</a>
            <a href="reservations_legacy.php" class="nav-link">Starý systém</a>
        </div>
    </div>

    <div class="main-content">
        <div class="left-panel">
            <h3>📝 Nová / Upravit rezervaci</h3>
            <div id="form-alert-container"></div>
            <div id="editBadge" class="edit-badge">REŽIM ÚPRAVY (ID: <span id="editIdLabel"></span>)</div>

            <form id="reservation-form">
                <div class="form-group">
                    <label>Čas začátku <span class="required">*</span></label>
                    <select id="reservation_time" required>
                        <option value="">Vyberte čas</option>
                    </select>
                    <div class="inline-help">Začátek.</div>
                </div>

                <div class="form-group">
                    <label>Délka</label>
                    <select id="reservation_duration"></select>
                    <div class="inline-help" id="duration-hint"></div>
                </div>

                <div class="form-group">
                    <label>Stůl</label>
                    <select id="table_number">
                        <option value="">(Vyberte čas)</option>
                    </select>
                    <div class="inline-help" id="table-availability-hint"></div>
                </div>

                <div class="form-group">
                    <label>Jméno zákazníka <span class="required">*</span></label>
                    <input type="text" id="customer_name" required>
                </div>
                <div class="form-group">
                    <label>Telefon <span class="required">*</span></label>
                    <input type="tel" id="phone" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email">
                </div>
                <div class="form-group">
                    <label>Počet osob <span class="required">*</span></label>
                    <select id="party_size" required>
                        <option value="">Vyberte</option>
                        <option>1</option><option>2</option><option>3</option><option>4</option>
                        <option>5</option><option>6</option><option>8</option><option>10</option><option>12</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="status">
                        <option value="pending">Čeká na potvrzení</option>
                        <option value="confirmed">Potvrzeno</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Poznámka</label>
                    <textarea id="notes" rows="3" placeholder="Speciální požadavky..."></textarea>
                </div>

                <button type="submit" class="btn" id="submitBtn">💾 Vytvořit rezervaci</button>
                <div class="flex-row" id="editButtons" style="display:none; margin-top:5px;">
                    <button type="button" class="btn-secondary small-btn" onclick="cancelEdit()">↩️ Zrušit úpravu</button>
                    <button type="button" class="btn-danger small-btn" onclick="deleteEditingReservation()">🗑️ Smazat</button>
                </div>
            </form>
        </div>

        <div class="right-panel">
            <div class="timeline-controls">
                <div class="date-control">
                    <label>📅 Datum</label>
                    <div class="date-row">
                        <input type="date" id="timeline-date">
                        <button class="btn-outline" onclick="loadTimeline()" type="button">🔄 Načíst</button>
                    </div>
                </div>
                <div class="opening-hours-form">
                    <header>🕐 Otevírací doba</header>
                    <div class="oh-row">
                        <input type="time" id="open_time" step="1800">
                        <span style="font-weight:600;">-</span>
                        <input type="time" id="close_time" step="1800">
                        <button type="button" onclick="saveOpeningHours()" class="btn-outline">💾 Uložit</button>
                    </div>
                    <div id="opening-hours-status" style="font-size:12px; min-height:16px;"></div>
                </div>
                <div class="display-options">
                    <header>👁️ Zobrazení</header>
                    <div class="display-row">
                        <button type="button" id="fontToggleBtn" class="btn-outline" onclick="toggleBigBlocks()">🔍 Větší bloky</button>
                        <button type="button" class="btn-outline" onclick="reloadAndReset()">♻️ Reset</button>
                    </div>
                </div>
                <div class="display-options">
                    <header>📄 Export</header>
                    <div class="display-row">
                        <button type="button" class="btn-outline" onclick="exportPdf('time')">🖨️ PDF (podle času)</button>
                        <button type="button" class="btn-outline" onclick="exportPdf('table')">🖨️ PDF (podle stolů)</button>
                    </div>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item"><span class="legend-color" style="background:#ffe28a; border-color:#e0b832;"></span> Čeká</div>
                <div class="legend-item"><span class="legend-color" style="background:#a7f3e4; border-color:#22a06b;"></span> Potvrz.</div>
                <div class="legend-item"><span class="legend-color" style="background:#9cd6ff; border-color:#1594c1;"></span> Posazení</div>
                <div class="legend-item"><span class="legend-color" style="background:#c9ccd1; border-color:#7d858d;"></span> Dokonč.</div>
                <div class="legend-item"><span class="legend-color" style="background:#f5b2b0; border-color:#d95b57;"></span> Zrušeno</div>
            </div>

            <div class="stats-panel" id="statsPanel" style="display:none;">
                <div class="stats-header">
                    📊 Statistiky pro <span id="statsDate">-</span>
                    <button type="button" class="btn-outline small-btn" onclick="toggleStatsPanel()" style="margin-left:auto; padding:4px 8px; font-size:11px;">Skrýt</button>
                </div>
                <div class="stats-content">
                    <div class="stats-summary">
                        <div class="stat-item">
                            <div class="stat-number" id="totalReservations">-</div>
                            <div class="stat-label">Rezervace</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="totalPersons">-</div>
                            <div class="stat-label">Celkem osob</div>
                        </div>
                    </div>
                    <div class="stats-slots">
                        <div class="slots-header">
                            30minutové sloty:
                            <div style="font-size:9px; margin-top:4px; color:#666;">
                                <span style="color:#28a745;">■</span> &lt;40%
                                <span style="color:#ffc107;">■</span> 40-70%
                                <span style="color:#dc3545;">■</span> &gt;70%
                                kapacita pecí
                            </div>
                        </div>
                        <div class="slots-list" id="slotsList"></div>
                    </div>
                </div>
            </div>

            <div class="stats-toggle-container">
                <button type="button" class="btn-outline small-btn" onclick="toggleStatsPanel()" id="showStatsBtn">📊 Zobrazit statistiky</button>
            </div>

            <div id="timeline-alert-container"></div>
            <div class="timeline-container">
                <div class="timeline" id="timeline"></div>
            </div>
        </div>
    </div>
</div>

<div id="reservationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Detail rezervace</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modal-alert-container"></div>
        <div id="modal-content"></div>
        <div class="modal-actions" id="modal-actions"></div>
    </div>
</div>

<script>
const DEBUG = false;
const DRAG_START_THRESHOLD = 6; // px pro rozlišení klik vs drag
const HOLD_SUPPRESS_CLICK_MS = 350; // dlouhé podržení bez pohybu = potlačit modal
/* =========================
   KONSTANTY & GLOBÁLNÍ
========================= */
const DEFAULT_OPEN  = '16:00';
const DEFAULT_CLOSE = '22:00';
const DEFAULT_DURATION = 120;
const DURATION_OPTIONS = [30,45,60,75,90,105,120,135,150,165,180,210,240,300,360];
const SLOT_MINUTES = 30;
const SLOT_HEIGHT = 64;
const EXCLUDED_TABLE_CODES = ['XX','S1','S2','S3','S4','S5','S6','S7','S8','S9','V1','V2','V3'];

const timeSlots = [];
let tables = [];
let tableCodeByNumber = {};
let currentReservations = [];
let currentDate = new Date().toISOString().split('T')[0];
let currentOpeningHours = { open_time: DEFAULT_OPEN, close_time: DEFAULT_CLOSE };
let editingReservationId = null;

/* Drag & Drop context */
let dragCtx = null;
let lastDragEndAt = 0;
let lastDragReservationId = null;
let lastSuppressedHoldAt = 0;
let lastSuppressedHoldReservationId = null;
/* =========================
   INIT
========================= */
document.addEventListener('DOMContentLoaded', () => {
    buildDurationSelect();
    setDefaultDate();
    presetOpeningInputs();
    loadData();
    document.getElementById('reservation-form').addEventListener('submit', handleFormSubmit);
    document.getElementById('timeline-date').addEventListener('change', e => {
        currentDate = e.target.value;
        cancelEdit();
        loadData();
    });
    document.getElementById('reservation_time').addEventListener('change', () => {
        updateAvailableTablesForSelectedTime();
        updateDurationHint();
    });
    document.getElementById('reservation_duration').addEventListener('change', () => {
        updateAvailableTablesForSelectedTime();
        updateDurationHint();
    });
});

function buildDurationSelect() {
    const sel = document.getElementById('reservation_duration');
    sel.innerHTML='';
    DURATION_OPTIONS.forEach(min=>{
        const o=document.createElement('option');
        o.value=min; o.textContent=min+' min';
        if (min===DEFAULT_DURATION) o.selected=true;
        sel.appendChild(o);
    });
    updateDurationHint();
}

/* =========================
   HELPERS – ČAS
========================= */
function normalizeTime24(val) {
    if (!val) return '';
    val = val.trim();
    const ampmMatch = val.match(/(AM|PM)$/i);
    let isPM = false;
    if (ampmMatch) {
        isPM = ampmMatch[1].toUpperCase() === 'PM';
        val = val.replace(/\s?(AM|PM)$/i,'');
    }
    val = val.replace(/:(\d{2}):\d{2}$/,':$1');
    const parts = val.split(':');
    if (parts.length < 2) return '';
    let h = parseInt(parts[0],10);
    const m = parts[1].substring(0,2);
    if (isPM && h < 12) h += 12;
    if (!isPM && h === 12 && ampmMatch) h = 0;
    return `${h.toString().padStart(2,'0')}:${m}`;
}
function toMinutes(hhmm){ const [h,m]=hhmm.split(':').map(Number); return h*60+m; }
function addMinutesToTime(hhmm,mins){ const base=toMinutes(hhmm)+mins; const h=Math.floor(base/60); const m=base%60; return `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`; }
function getSelectedDuration(){
    const v=parseInt(document.getElementById('reservation_duration').value,10);
    if (isNaN(v)||v<15||v>600) return DEFAULT_DURATION;
    return v;
}
function updateDurationHint(){
    const start=document.getElementById('reservation_time').value;
    const hint=document.getElementById('duration-hint');
    if(!hint) return;
    if(!start){ hint.textContent=''; return; }
    const dur=getSelectedDuration();
    const end=addMinutesToTime(start,dur);
    hint.textContent=`Konec: ${end} (délka ${dur} min)`;
}

/* =========================
   LOAD SEQUENCE
========================= */
async function loadData() {
    try {
        await loadTables();
        await loadOpeningHours();
        generateTimeline();
        await loadReservations();
        updateAvailableTablesForSelectedTime();
        updateDurationHint();
    } catch (e) {
        console.error(e);
        showAlert('Chyba při načítání dat','error','timeline-alert-container');
    }
}
function setDefaultDate(){
    const today=new Date().toISOString().split('T')[0];
    document.getElementById('timeline-date').value=today;
    currentDate=today;
}
function presetOpeningInputs(){
    document.getElementById('open_time').value=DEFAULT_OPEN;
    document.getElementById('close_time').value=DEFAULT_CLOSE;
}

/* =========================
   TIME SLOTS
========================= */
function generateTimeSlots(openTime=null, closeTime=null) {
    const timeSelect=document.getElementById('reservation_time');
    timeSelect.innerHTML='<option value="">Vyberte čas</option>';
    const oT=openTime||DEFAULT_OPEN;
    const cT=closeTime||DEFAULT_CLOSE;
    const startHour=parseInt(oT.split(':')[0]);
    const startMinute=parseInt(oT.split(':')[1]);
    const endHour=parseInt(cT.split(':')[0]);
    const endMinute=parseInt(cT.split(':')[1]);

    timeSlots.length=0;
    const closeTotal = endHour*60 + endMinute;

    for(let total=startHour*60+startMinute; total <= closeTotal - SLOT_MINUTES; total += SLOT_MINUTES){
        const hour=Math.floor(total/60);
        const minute=total%60;
        const ts=`${hour.toString().padStart(2,'0')}:${minute.toString().padStart(2,'0')}`;
        timeSlots.push(ts);
        const opt=document.createElement('option');
        opt.value=ts; opt.textContent=ts;
        timeSelect.appendChild(opt);
    }
    if (DEBUG) console.log('Generated timeSlots', timeSlots);
}

/* =========================
   TABLES
========================= */
function parseTableCode(codeRaw){
    const code=(codeRaw||'').trim();
    const m = code.match(/^([A-Za-z]+)(\d+)([A-Za-z]?)$/);
    if(!m){
        return { prefix: code, prefixOrder: 99, num: 999999, suffix: '', suffixOrder: 99 };
    }
    const prefix=m[1];
    const num=parseInt(m[2],10);
    const suffix=m[3]||'';
    const prefixMap={ P:1, O:2, S:3, V:4, A:5 };
    const suffixMap={ '':0, 'a':1, 'b':2 };
    return {
        prefix,
        prefixOrder: prefixMap[prefix] ?? 50,
        num,
        suffix,
        suffixOrder: suffixMap[suffix] ?? 10
    };
}
function sortTables(){
    tables.sort((a,b)=>{
        const pa=parseTableCode(a.table_code);
        const pb=parseTableCode(b.table_code);
        return (pa.prefixOrder-pb.prefixOrder)
            || (pa.num-pb.num)
            || (pa.suffixOrder-pb.suffixOrder)
            || pa.prefix.localeCompare(pb.prefix)
            || pa.suffix.localeCompare(pb.suffix);
    });
}
function isExcludedTable(tableObj){
    const code=(tableObj.table_code||'').toUpperCase().trim();
    return EXCLUDED_TABLE_CODES.includes(code);
}
async function loadTables(){
    try{
        const resp=await fetch(`/pizza/api/restaurant-api.php?action=tables-with-reservations&date=${currentDate}`);
        const data=await resp.json();
        if(data.success){
            const raw=data.data.map(t=>({
                table_number:t.table_number,
                table_code:t.table_code||`Stůl ${t.table_number}`,
                status:t.status||'free'
            }));
            tables=raw.filter(t=>!isExcludedTable(t));
            sortTables();
            buildTableCodeMap();
        } else tables=[];
    }catch(e){ console.error(e); tables=[]; }
}
function buildTableCodeMap(){
    tableCodeByNumber={};
    tables.forEach(t=>tableCodeByNumber[Number(t.table_number)]=t.table_code||`Stůl ${t.table_number}`);
}
function populateTableSelect(filtered=null){
    const sel=document.getElementById('table_number');
    sel.innerHTML='';
    const list=filtered||tables;
    if(!list.length){
        sel.innerHTML='<option value="">(Žádné volné stoly)</option>';
        return;
    }
    sel.appendChild(new Option('Automatické přiřazení',''));
    list.forEach(t=>sel.appendChild(new Option(t.table_code,t.table_number)));

    if(editingReservationId){
        const res=currentReservations.find(r=>r.id==editingReservationId);
        if(res && res.table_number && !list.some(t=>t.table_number==res.table_number)){
            const opt=new Option(getTableCode(res.table_number)+' (obsazeno / konflikt)',res.table_number);
            opt.disabled=true;
            sel.appendChild(opt);
            sel.value=res.table_number;
        }
    }
}
function getTableCode(num){ return tableCodeByNumber[Number(num)]||`Stůl ${num}`; }

/* =========================
   OPENING HOURS
========================= */
async function loadOpeningHours(){
    try{
        const resp=await fetch(`/api/reservations/opening_hours.php?date=${currentDate}`);
        const data=await resp.json();
        if(data.ok){
            let open=normalizeTime24(data.open_time);
            let close=normalizeTime24(data.close_time);
            if(!open||!close) throw new Error('Formát');
            if(open==='10:00' && close==='23:00'){ open=DEFAULT_OPEN; close=DEFAULT_CLOSE; }
            currentOpeningHours={open_time:open,close_time:close};
        } else {
            currentOpeningHours={open_time:DEFAULT_OPEN,close_time:DEFAULT_CLOSE};
            showAlert('Nepodařilo se načíst otevírací dobu – použit default 16–22','warning','timeline-alert-container');
        }
    }catch{
        currentOpeningHours={open_time:DEFAULT_OPEN,close_time:DEFAULT_CLOSE};
    }
    document.getElementById('open_time').value=currentOpeningHours.open_time;
    document.getElementById('close_time').value=currentOpeningHours.close_time;
    generateTimeSlots(currentOpeningHours.open_time,currentOpeningHours.close_time);
}
async function saveOpeningHours(){
    const openTime=document.getElementById('open_time').value;
    const closeTime=document.getElementById('close_time').value;
    if(!openTime||!closeTime) return showAlert('Zadej oba časy','error','opening-hours-status');
    if(openTime>=closeTime) return showAlert('Otevření musí být dříve než zavření','error','opening-hours-status');
    try{
        const resp=await fetch('/api/reservations/opening_hours.php',{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({date:currentDate,open_time:openTime,close_time:closeTime})
        });
        const data=await resp.json();
        if(data.ok){
            currentOpeningHours={open_time:openTime,close_time:closeTime};
            showAlert('Otevírací doba uložena','success','opening-hours-status');
            generateTimeSlots(openTime,closeTime);
            generateTimeline();
            await loadReservations();
            updateAvailableTablesForSelectedTime();
        }else showAlert(data.error||'Chyba uložení','error','opening-hours-status');
    }catch{ showAlert('Chyba komunikace','error','opening-hours-status'); }
}

/* =========================
   RESERVATIONS LOAD / RENDER
========================= */
async function loadReservations(){
    try{
        const resp=await fetch(`/api/reservations/list.php?date=${currentDate}`);
        const data=await resp.json();
        if(data.ok){
            currentReservations=data.data
                .map(r=>({...r, table_code:r.table_code||tableCodeByNumber[Number(r.table_number)]||null }))
                .filter(r=>!isExcludedTable({table_code:r.table_code}));
            if (DEBUG) console.log('Reservations loaded', currentReservations);
            renderReservations();
        } else showAlert('Chyba načtení rezervací: '+data.error,'error','timeline-alert-container');
    }catch{
        showAlert('Chyba načítání rezervací','error','timeline-alert-container');
    }
}

function generateTimeline(){
    const timeline=document.getElementById('timeline');
    timeline.innerHTML='';
    const openHour=parseInt(currentOpeningHours.open_time.split(':')[0]);
    const openMin=parseInt(currentOpeningHours.open_time.split(':')[1]);
    const closeHour=parseInt(currentOpeningHours.close_time.split(':')[0]);
    const closeMin=parseInt(currentOpeningHours.close_time.split(':')[1]);
    timeline.style.gridTemplateColumns=`90px repeat(${tables.length}, 130px)`;

    const th=document.createElement('div'); th.className='time-header'; th.textContent='Čas'; timeline.appendChild(th);
    tables.forEach(t=>{
        const h=document.createElement('div'); h.className='table-header'; h.textContent=t.table_code; timeline.appendChild(h);
    });

    for(let hour=openHour; hour<=closeHour; hour++){
        for(let minute=0; minute<60; minute+=SLOT_MINUTES){
            if(hour===openHour && minute<openMin) continue;
            if(hour===closeHour && minute>=closeMin) break;
            const timeString=`${hour.toString().padStart(2,'0')}:${minute.toString().padStart(2,'0')}`;
            const ts=document.createElement('div');
            ts.className='time-slot';
            ts.textContent=timeString;
            timeline.appendChild(ts);
            tables.forEach(t=>{
                const slot=document.createElement('div');
                slot.className='table-slot';
                slot.dataset.time=timeString;
                slot.dataset.table=t.table_number;
                slot.addEventListener('click',()=>handleEmptySlotClick(timeString,t.table_number,slot));
                timeline.appendChild(slot);
            });
        }
    }
}

function renderReservations(){
    document.querySelectorAll('.reservation-block').forEach(b=>b.remove());
    currentReservations.forEach(r=>{
        if(!r.table_number) return;
        const startTime=r.reservation_time.substring(0,5);
        const cell=document.querySelector(`[data-time="${startTime}"][data-table="${r.table_number}"]`);
        if(!cell){
            if(DEBUG) console.warn('Skip render – no slot for', r.id, startTime, r.table_number);
            return;
        }
        const block=document.createElement('div');
        block.className=`reservation-block ${r.status}`;
        const dur=parseInt(r.manual_duration_minutes || r.duration_minutes || DEFAULT_DURATION,10);
        const endTime = r.computed_end_time || addMinutesToTime(startTime,dur);
        block.innerHTML=`
            <div class="reservation-name" title="${escapeHtml(r.customer_name)}">${escapeHtml(r.customer_name)}</div>
            <div class="reservation-details">${r.party_size} os.</div>
            <div class="reservation-details">${startTime}–${endTime}</div>
        `;
        const unitBlocks = dur / SLOT_MINUTES;
        block.style.height=`${(unitBlocks * SLOT_HEIGHT) - 6}px`;

        // Klik vždy otevře modal (drag klik neproběhne)
        block.addEventListener('click', ev=>{
    ev.stopPropagation();
    if (shouldSuppressModal(r.id)) {
        if (DEBUG) console.log('Modal suppressed for reservation', r.id);
        return;
    }
    showReservationModal(r);
});

        // Zahájení potenciálního dragu
        block.addEventListener('mousedown', ev => startDragReservation(ev, r));
        block.addEventListener('touchstart', ev => startDragReservation(ev, r), {passive:false});

        cell.appendChild(block);
    });
}

/* =========================
   DRAG & DROP REZERVACÍ
========================= */
function startDragReservation(ev, res){
    if (ev.type==='mousedown' && ev.button!==0) return;

    const block = ev.currentTarget;
    const rect  = block.getBoundingClientRect();
    const p     = getPointer(ev);
    const dur   = parseInt(res.manual_duration_minutes || res.duration_minutes || DEFAULT_DURATION,10);

    dragCtx = {
  id: res.id,
  resObj: res,
  origEl: block,
  duration: dur,
  startX: p.x,
  startY: p.y,
  lastX: p.x,
  lastY: p.y,
  offsetX: p.x - rect.left,
  offsetY: p.y - rect.top,
  started: false,
  downTime: Date.now(),
  hadMovement: false
};

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', endDragReservation);
    window.addEventListener('touchmove', onDragMove, {passive:false});
    window.addEventListener('touchend', endDragReservation);
    window.addEventListener('touchcancel', endDragReservation);
}

function createDragGhost(){
    if (!dragCtx || dragCtx.started) return;
    const block = dragCtx.origEl;
    const rect  = block.getBoundingClientRect();

    const ghost = block.cloneNode(true);
    ghost.style.position='fixed';
    ghost.style.left = (dragCtx.startX - dragCtx.offsetX)+'px';
    ghost.style.top  = (dragCtx.startY - dragCtx.offsetY)+'px';
    ghost.style.width = rect.width+'px';
    ghost.style.height= rect.height+'px';
    ghost.classList.add('dragging-reservation');
    ghost.dataset.dragging='1';
    ghost.style.pointerEvents='none';

    document.body.appendChild(ghost);
    dragCtx.ghostEl = ghost;

    block.style.visibility='hidden';
    block.dataset.dragging='1';
    dragCtx.started = true;
}

function getPointer(ev){
    if(ev.touches && ev.touches.length){
        return {x:ev.touches[0].clientX, y:ev.touches[0].clientY};
    }
    return {x:ev.clientX, y:ev.clientY};
}

function onDragMove(ev){
    if(!dragCtx) return;
    if(ev.cancelable) ev.preventDefault();
    const p=getPointer(ev);
    dragCtx.lastX = p.x;
    dragCtx.lastY = p.y;

    if(!dragCtx.started){
        const dx = Math.abs(p.x - dragCtx.startX);
        const dy = Math.abs(p.y - dragCtx.startY);
        if (dx < DRAG_START_THRESHOLD && dy < DRAG_START_THRESHOLD){
            return; // ještě nezačneme
        }
        createDragGhost();
    }

    const g=dragCtx.ghostEl;
    if(g){
        g.style.left=(p.x - dragCtx.offsetX)+'px';
        g.style.top =(p.y - dragCtx.offsetY)+'px';
    }
    highlightDropTarget(p.x,p.y);
}

function highlightDropTarget(x,y){
    document.querySelectorAll('.dnd-drop-target-ok,.dnd-drop-target-bad').forEach(el=>{
        el.classList.remove('dnd-drop-target-ok','dnd-drop-target-bad');
    });
    document.querySelectorAll('#dnd-slot-preview').forEach(p=>p.remove());

    const el=document.elementFromPoint(x,y);
    if(!el){
        dragCtx.target=null;
        return;
    }
    const slot = el.closest('.table-slot');
    if(!slot){
        dragCtx.target=null;
        return;
    }

    const newTable=slot.dataset.table;
    const newStart=slot.dataset.time;
    const valid=isValidDrop(newTable,newStart,dragCtx.duration,dragCtx.id);

    if (DEBUG) console.log('Hover slot', newTable, newStart, 'valid=', valid);

    dragCtx.target={slot,newTable,newStart,valid};

    slot.classList.add(valid?'dnd-drop-target-ok':'dnd-drop-target-bad');

    const preview=document.createElement('div');
    preview.id='dnd-slot-preview';
    preview.className='dnd-slot-preview';
    const units=dragCtx.duration / SLOT_MINUTES;
    preview.style.height=((units * SLOT_HEIGHT)-6)+'px';
    slot.appendChild(preview);
}

function isValidDrop(tableNumber,startTime,duration,movingId){
    const startMin=toMinutes(startTime);
    const endMin=startMin+duration;
    if(endMin>toMinutes(currentOpeningHours.close_time)) return false;

    for(const r of currentReservations){
        if(!r.table_number) continue;
        if(['cancelled','no_show'].includes(r.status)) continue;
        if(r.id==movingId) continue;
        if(String(r.table_number)!==String(tableNumber)) continue;
        const rStart=toMinutes(r.reservation_time.substring(0,5));
        const rDur=parseInt(r.manual_duration_minutes || r.duration_minutes || DEFAULT_DURATION,10);
        const rEnd=rStart + rDur;
        if((rStart < endMin) && (rEnd > startMin)) return false;
    }
    return true;
}

function endDragReservation(ev){
    if(!dragCtx) return;

    // Pokud jsme reálný drag nezačali -> šlo o klik, jen uklidíme
   if(!dragCtx.started){
    // dlouhé držení bez dragu? potlačíme click
    const holdTime = Date.now() - dragCtx.downTime;
    if(holdTime >= HOLD_SUPPRESS_CLICK_MS){
        lastSuppressedHoldAt = Date.now();
        lastSuppressedHoldReservationId = dragCtx.id;
    }
    cleanupDrag();
    return;
}

    if(!dragCtx.target){
        const p = getPointer(ev || {});
        highlightDropTarget(p.x, p.y);
    }

    const {origEl,ghostEl,target,resObj}=dragCtx;

    if(target && target.valid){
        performDragUpdate(resObj.id, target.newTable, target.newStart, dragCtx.duration)
            .then(()=>{ loadTimeline(); })
            .catch(err=>{
                showAlert('Chyba přesunu: '+err.message,'error','timeline-alert-container');
                origEl.style.visibility='';
                origEl.dataset.dragging='0';
            });
    } else {
        origEl.style.visibility='';
        origEl.dataset.dragging='0';
    }
lastDragEndAt = Date.now();
lastDragReservationId = dragCtx.id;
    if(ghostEl) ghostEl.remove();
    document.querySelectorAll('#dnd-slot-preview').forEach(p=>p.remove());
    cleanupDrag();
}

function performDragUpdate(id, tableNumber, startTime, duration){
    const payload = {
        id,
        table_number: tableNumber,
        reservation_time: startTime, // HH:MM
        reservation_date: currentDate,
        manual_duration_minutes: duration
    };
    if (DEBUG) console.log('Drag update payload', payload);

    return fetch('/api/reservations/update.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r=>r.json())
    .then(data=>{
        if (DEBUG) console.log('Drag update response', data);
        if(!data.ok) throw new Error(data.error || 'Update failed');
        showAlert('Rezervace přesunuta','success','timeline-alert-container');
    });
}

function cleanupDrag(){
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', endDragReservation);
    window.removeEventListener('touchmove', onDragMove);
    window.removeEventListener('touchend', endDragReservation);
    window.removeEventListener('touchcancel', endDragReservation);
    dragCtx=null;
}

/* =========================
   CLICK PRAZDNY SLOT
========================= */
function handleEmptySlotClick(timeString, tableNumber, slotEl){
    if(slotEl.querySelector('.reservation-block')) return;
    document.getElementById('reservation_time').value=timeString;
    updateDurationHint();
    updateAvailableTablesForSelectedTime();
    const unavailable=computeUnavailableTables(timeString);
    if(!unavailable.has(String(tableNumber))){
        const sel=document.getElementById('table_number');
        if([...sel.options].some(o=>o.value===String(tableNumber))) sel.value=tableNumber;
    }
    if(editingReservationId) cancelEdit();
    slotEl.classList.remove('slot-flash'); void slotEl.offsetWidth; slotEl.classList.add('slot-flash');
    document.querySelector('.left-panel').scrollTo({top:0,behavior:'smooth'});
    document.getElementById('customer_name').focus({preventScroll:true});
}

/* =========================
   TABLE AVAILABILITY
========================= */
function updateAvailableTablesForSelectedTime(){
    const start=document.getElementById('reservation_time').value;
    const hint=document.getElementById('table-availability-hint');
    if(!start){
        populateTableSelect([]);
        hint.textContent='Nejprve vyberte čas.';
        return;
    }
    const dur=getSelectedDuration();
    const endCandidate=toMinutes(start)+dur;
    const closingTotal=toMinutes(currentOpeningHours.close_time);
    if(endCandidate>closingTotal){
        populateTableSelect([]);
        hint.textContent='Čas + délka přesahují zavírací dobu.';
        return;
    }
    const unavailable=computeUnavailableTables(start);
    const free=tables.filter(t=>!unavailable.has(String(t.table_number)));
    populateTableSelect(free);
    hint.textContent= free.length ? `Volných stolů: ${free.length} (délka ${dur} min)` : 'Žádné volné stoly';
}

function computeUnavailableTables(startTime){
    const set=new Set();
    const startMin=toMinutes(startTime);
    const selDur=getSelectedDuration();
    const endMin=startMin+selDur;
    currentReservations.forEach(r=>{
        if(['cancelled','no_show'].includes(r.status)) return;
        if(editingReservationId && r.id==editingReservationId) return;
        const rStart=toMinutes(r.reservation_time.substring(0,5));
        const rDur=parseInt(r.manual_duration_minutes || r.duration_minutes || DEFAULT_DURATION,10);
        const rEnd=rStart + rDur;
        const overlap = (rStart < endMin) && (rEnd > startMin);
        if(overlap && r.table_number) set.add(String(r.table_number));
    });
    return set;
}

/* =========================
   MODAL
========================= */
function showReservationModal(res){
    const modal=document.getElementById('reservationModal');
    const mc=document.getElementById('modal-content');
    const ma=document.getElementById('modal-actions');
    const dur=parseInt(res.manual_duration_minutes || res.duration_minutes || DEFAULT_DURATION,10);
    const endTime=res.computed_end_time || addMinutesToTime(res.reservation_time.substring(0,5),dur);
    mc.innerHTML=`
        <p><strong>Zákazník:</strong> ${escapeHtml(res.customer_name)}</p>
        <p><strong>Telefon:</strong> ${escapeHtml(res.phone)}</p>
        ${res.email ? `<p><strong>Email:</strong> ${escapeHtml(res.email)}</p>` : ''}
        <p><strong>Počet osob:</strong> ${res.party_size}</p>
        <p><strong>Datum:</strong> ${res.reservation_date}</p>
        <p><strong>Čas:</strong> ${res.reservation_time.substring(0,5)} – ${endTime} (${dur} min)</p>
        <p><strong>Stůl:</strong> ${getReservationTableLabel(res)}</p>
        <p><strong>Stav:</strong> <span class="status-label ${res.status}">${getStatusText(res.status)}</span></p>
        ${res.notes ? `<p><strong>Poznámka:</strong> ${escapeHtml(res.notes)}</p>` : ''}
        <p style="font-size:11px;color:#777;margin-top:8px;">ID: ${res.id}</p>
    `;
    ma.innerHTML=getModalActions(res);
    modal.style.display='block';
}
function getReservationTableLabel(r){
    if(r.table_code) return escapeHtml(r.table_code);
    if(r.table_number) return getTableCode(r.table_number);
    return 'Nepřiřazen';
}
function getModalActions(r){
    let a='';
    if(r.status==='pending') a+=`<button class="btn btn-success" onclick="confirmReservation(${r.id})">✅ Potvrdit</button>`;
    if(['pending','confirmed'].includes(r.status)) a+=`<button class="btn btn-success" style="background:linear-gradient(135deg,#1d976c,#2ecc71)" onclick="seatReservation(${r.id})">🪑 Posadit</button>`;
    if(r.status==='seated') a+=`<button class="btn btn-secondary" onclick="finishReservation(${r.id})">✅ Dokončit</button>`;
    if(!['finished','cancelled','no_show'].includes(r.status)){
        a+=`<button class="btn btn-warning" onclick="startEditReservation(${r.id})">✏️ Upravit / Přesunout</button>`;
        a+=`<button class="btn btn-danger" onclick="cancelReservation(${r.id})">❌ Zrušit</button>`;
    }
    return a;
}
function closeModal(){ document.getElementById('reservationModal').style.display='none'; clearAlert('modal-alert-container'); }
window.onclick=e=>{ const m=document.getElementById('reservationModal'); if(e.target===m) closeModal(); };

/* =========================
   ACTIONS (STATUS)
========================= */
async function confirmReservation(id){ await performReservationAction('/api/reservations/confirm.php',{id},'Potvrzení'); }
async function seatReservation(id){ await performReservationAction('/api/reservations/seat.php',{id},'Posazení'); }
async function finishReservation(id){ await performReservationAction('/api/reservations/finish.php',{id},'Dokončení'); }
async function cancelReservation(id){
    if(!confirm('Opravdu zrušit rezervaci?')) return;
    await performReservationAction('/api/reservations/cancel.php',{id},'Zrušení');
}
async function performReservationAction(url,data,actionName){
    try{
        const fd=new FormData(); Object.entries(data).forEach(([k,v])=>fd.append(k,v));
        const resp=await fetch(url,{method:'POST',body:fd});
        const result=await resp.json();
        if(result.ok){
            showAlert(result.message || `${actionName} úspěšné`,'success','modal-alert-container');
            setTimeout(()=>{ closeModal(); loadTimeline(); },800);
        } else showAlert('Chyba: '+(result.error||'Neznámá'),'error','modal-alert-container');
    }catch(e){ showAlert('Chyba: '+e.message,'error','modal-alert-container'); }
}

/* =========================
   EDIT MODE
========================= */
function startEditReservation(id){
    const res=currentReservations.find(r=>r.id==id);
    if(!res) return;
    closeModal();
    editingReservationId=id;
    document.getElementById('editBadge').classList.add('active');
    document.getElementById('editIdLabel').textContent=id;
    document.getElementById('customer_name').value=res.customer_name;
    document.getElementById('phone').value=res.phone;
    document.getElementById('email').value=res.email||'';
    document.getElementById('party_size').value=res.party_size;
    document.getElementById('reservation_time').value=res.reservation_time.substring(0,5);
    const dur=parseInt(res.manual_duration_minutes || res.duration_minutes || DEFAULT_DURATION,10);
    const durSel=document.getElementById('reservation_duration');
    if([...durSel.options].some(o=>+o.value===dur)){
        durSel.value=dur;
    }else{
        const o=document.createElement('option'); o.value=dur; o.textContent=dur+' min'; durSel.appendChild(o); durSel.value=dur;
    }
    updateDurationHint();
    document.getElementById('status').value=res.status;
    document.getElementById('notes').value=res.notes||'';
    updateAvailableTablesForSelectedTime();
    if(res.table_number) document.getElementById('table_number').value=res.table_number;
    document.getElementById('submitBtn').textContent='💾 Uložit změny';
    document.getElementById('editButtons').style.display='flex';
    scrollToFormTop();
}
function cancelEdit(){
    editingReservationId=null;
    document.getElementById('editBadge').classList.remove('active');
    document.getElementById('reservation-form').reset();
    document.getElementById('status').value='pending';
    buildDurationSelect();
    document.getElementById('submitBtn').textContent='💾 Vytvořit rezervaci';
    document.getElementById('editButtons').style.display='none';
    updateAvailableTablesForSelectedTime();
    clearAlert('form-alert-container');
}
async function deleteEditingReservation(){
    if(!editingReservationId) return;
    if(!confirm('Opravdu smazat / zrušit tuto rezervaci?')) return;
    try{
        const resp=await fetch('/api/reservations/cancel.php',{
            method:'POST',
            body:(()=>{ const f=new FormData(); f.append('id',editingReservationId); return f; })()
        });
        const result=await resp.json();
        if(result.ok){
            showAlert('Rezervace zrušena','success','form-alert-container');
            cancelEdit();
            loadTimeline();
        } else showAlert(result.error,'error','form-alert-container');
    }catch(e){ showAlert(e.message,'error','form-alert-container'); }
}
function scrollToFormTop(){ document.querySelector('.left-panel').scrollTo({top:0,behavior:'smooth'}); }

/* =========================
   FORM SUBMIT
========================= */
async function handleFormSubmit(e){
    e.preventDefault();
    clearAlert('form-alert-container');

    const formData = {
        customer_name: document.getElementById('customer_name').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        email: document.getElementById('email').value.trim(),
        party_size: parseInt(document.getElementById('party_size').value, 10),
        reservation_date: currentDate,
        reservation_time: document.getElementById('reservation_time').value,
        table_number: document.getElementById('table_number').value || null,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value.trim(),
        manual_duration_minutes: getSelectedDuration()
    };

    if (!formData.reservation_time)
        return showAlert('Vyberte čas','error','form-alert-container');
    if (!formData.customer_name || !formData.phone || !formData.party_size)
        return showAlert('Vyplň povinná pole','error','form-alert-container');

    if (editingReservationId) {
        formData.id = editingReservationId;
        const original = currentReservations.find(r => r.id == editingReservationId);
        if (original) {
            if (!formData.table_number && original.table_number) {
                formData.table_number = original.table_number;
            }
            if ((!formData.manual_duration_minutes || isNaN(formData.manual_duration_minutes)) &&
                (original.manual_duration_minutes || original.duration_minutes)) {
                formData.manual_duration_minutes = parseInt(
                    original.manual_duration_minutes || original.duration_minutes,
                    10
                );
            }
        }
    }

    const url = editingReservationId ? '/api/reservations/update.php' : '/api/reservations/create.php';

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type':'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await resp.json();

        if (result.ok) {
            showAlert(
                editingReservationId ? '✅ Změny uloženy' : '✅ Rezervace vytvořena',
                'success',
                'form-alert-container'
            );
            cancelEdit();
            loadTimeline();
        } else {
            showAlert(result.error || 'Chyba','error','form-alert-container');
        }
    } catch (err) {
        showAlert(err.message,'error','form-alert-container');
    }
}

/* =========================
   DISPLAY / TOGGLES
========================= */
function toggleBigBlocks(){
    document.body.classList.toggle('big-reservations');
    const btn=document.getElementById('fontToggleBtn');
    btn.textContent=document.body.classList.contains('big-reservations')
        ? '🔍 Standardní velikost'
        : '🔍 Větší bloky';
}
function reloadAndReset(){
    document.body.classList.remove('big-reservations');
    document.getElementById('fontToggleBtn').textContent='🔍 Větší bloky';
    cancelEdit();
    loadTimeline();
}

/* =========================
   HELPERS
========================= */
function getStatusText(s){
    const map={ pending:'Čeká na potvrzení', confirmed:'Potvrzeno', seated:'Posazeni', finished:'Dokončeno', cancelled:'Zrušeno', no_show:'Nedorazil' };
    return map[s]||s;
}
function escapeHtml(t){
    const map={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"};
    return t ? t.replace(/[&<>"']/g,m=>map[m]) : '';
}
function showAlert(message,type,containerId){
    const el=document.getElementById(containerId);
    if(!el) return;
    const cls= type==='success'?'alert-success' : type==='warning'?'alert-warning' : 'alert-error';
    el.innerHTML=`<div class="alert ${cls}">${message}</div>`;
    if(!['modal-alert-container','form-alert-container'].includes(containerId))
        setTimeout(()=>clearAlert(containerId),5000);
}
function clearAlert(id){
    const el=document.getElementById(id);
    if(el) el.innerHTML='';
}
async function loadTimeline(){
    await loadData();
    await loadStats();
}

function shouldSuppressModal(resId){
    const now = Date.now();

    // Bezprostředně po dragu (do 300 ms) – potlačit
    if (lastDragReservationId === resId && (now - lastDragEndAt) < 300) {
        return true;
    }

    // Dlouhé podržení bez pohybu – potlačit (do 300 ms po uvolnění)
    if (lastSuppressedHoldReservationId === resId && (now - lastSuppressedHoldAt) < 300) {
        return true;
    }

    return false;
}

/* =========================
   STATISTICS
========================= */
let statsVisible=false;
async function loadStats(){
    try{
        const resp=await fetch(`/api/reservations/stats.php?date=${currentDate}`);
        const data=await resp.json();
        if(data.ok) updateStatsDisplay(data);
    }catch(e){ if(DEBUG) console.warn('stats error',e); }
}
function updateStatsDisplay(data){
    document.getElementById('statsDate').textContent=data.date;
    document.getElementById('totalReservations').textContent=data.reservation_count||0;
    document.getElementById('totalPersons').textContent=data.total_persons||0;
    const slotsList=document.getElementById('slotsList');
    slotsList.innerHTML='';
    if(data.slots && data.slots.length){
        data.slots.forEach(slot=>{
            const el=document.createElement('div');
            let cls=`slot-item ${slot.persons>0?'has-persons':''}`;
            if(typeof slot.capacity_pct!=='undefined'){
                if(slot.capacity_pct>=1.0) cls+=' slot-cap-critical';
                else if(slot.capacity_pct>=0.70) cls+=' slot-cap-warning';
                else if(slot.capacity_pct>=0.40) cls+=' slot-cap-normal';
            }
            el.className=cls;
            let html=`<div class="slot-time">${slot.time}</div><div class="slot-persons">${slot.persons} os.</div>`;
            if(typeof slot.capacity_pct!=='undefined' && typeof slot.rolling_hour_pizzas!=='undefined'){
                html+=`<div class="slot-capacity">${Math.round(slot.capacity_pct*100)}% (${slot.rolling_hour_pizzas}🍕/h)</div>`;
            }
            el.innerHTML=html;
            slotsList.appendChild(el);
        });
    } else {
        slotsList.innerHTML='<div style="grid-column:1/-1;text-align:center;color:#666;font-size:11px;">Žádná data</div>';
    }
}
function toggleStatsPanel(){
    const panel=document.getElementById('statsPanel');
    const showBtn=document.getElementById('showStatsBtn');
    statsVisible=!statsVisible;
    if(statsVisible){ panel.style.display='block'; showBtn.style.display='none'; }
    else { panel.style.display='none'; showBtn.style.display='inline-block'; }
}

function exportPdf(groupMode='time'){
    const d = currentDate;
    const url = `/api/reservations/export_pdf.php?date=${encodeURIComponent(d)}&group=${encodeURIComponent(groupMode)}`;
    window.open(url, '_blank');
}

/* =========================
   EXPORT
========================= */
window.confirmReservation=confirmReservation;
window.seatReservation=seatReservation;
window.finishReservation=finishReservation;
window.cancelReservation=cancelReservation;
window.startEditReservation=startEditReservation;
window.cancelEdit=cancelEdit;
window.deleteEditingReservation=deleteEditingReservation;
window.toggleStatsPanel=toggleStatsPanel;
</script>
</body>
</html>