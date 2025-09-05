<?php
/**
 * export_pdf.php – verze s table_code (P12, O2, ...)
 * GET:
 *   date=YYYY-MM-DD
 *   group=time|table
 */

// (Volitelně smaž po otestování)
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['order_user'])) {
    http_response_code(403);
    echo "Neautorizováno";
    exit;
}

$dateInput = isset($_GET['date']) ? trim($_GET['date']) : '';
$groupMode = isset($_GET['group']) ? trim($_GET['group']) : 'time';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
    http_response_code(400);
    echo "Neplatný parametr date (YYYY-MM-DD)";
    exit;
}
if (!in_array($groupMode, ['time','table'], true)) {
    $groupMode = 'time';
}

/* DB */
$dbHost = '127.0.0.1';
$dbName = 'pizza_orders';
$dbUser = 'pizza_user';
$dbPass = 'pizza';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn,$dbUser,$dbPass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB připojení selhalo: ".htmlspecialchars($e->getMessage());
    exit;
}

/*
 * JOIN na restaurant_tables -> table_code
 * Mapování:
 *   start_time = reservation_time
 *   end_time   = TIME(end_datetime)
 *   persons    = party_size
 *   note       = notes
 */
try {
    $sql = "
        SELECT
            r.id,
            r.table_number,
            t.table_code,
            r.customer_name,
            TIME_FORMAT(r.reservation_time, '%H:%i') AS start_time,
            TIME_FORMAT(r.end_datetime, '%H:%i')     AS end_time,
            r.party_size AS persons,
            r.notes AS note,
            r.status
        FROM reservations r
        LEFT JOIN restaurant_tables t ON t.table_number = r.table_number
        WHERE r.reservation_date = :d
        ORDER BY r.reservation_time, r.table_number
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':d'=>$dateInput]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo "Chyba dotazu: ".htmlspecialchars($e->getMessage());
    exit;
}

/* Skupiny */
$grouped = [];
if ($groupMode === 'time') {
    foreach ($rows as $r) {
        $k = $r['start_time'];
        $grouped[$k][] = $r;
    }
    ksort($grouped,SORT_STRING);
} else {
    foreach ($rows as $r) {
        $displayTable = $r['table_code'] ?: $r['table_number'];
        // Pokud chceš mít prefix jen u fallbacku: if(!$r['table_code']) $displayTable = 'Stůl '.$displayTable;
        $grouped[$displayTable][] = $r;
    }
    ksort($grouped,SORT_NATURAL);
}
$totalReservations = count($rows);
$totalPersons = array_sum(array_map(fn($r)=>(int)$r['persons'],$rows));

/* HTML */
ob_start();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Rezervace <?= htmlspecialchars($dateInput) ?> (<?= $groupMode==='time'?'podle času':'podle stolů' ?>)</title>
<style>
@page { margin:15mm 12mm 18mm 12mm; }
body { font-family:"DejaVu Sans",sans-serif; font-size:12px; color:#111; }
h1 { font-size:20px; margin:0 0 8px 0; }
h2 { margin:25px 0 5px; font-size:16px; border-bottom:1px solid #333; padding-bottom:2px; }
table { width:100%; border-collapse:collapse; margin-bottom:6px; table-layout:fixed; }
th,td { border:1px solid #444; padding:4px 6px; font-size:11px; vertical-align:top; word-break:break-word; }
th { background:#f0f0f0; font-weight:600; text-align:left; }
.summary { margin:0 0 10px; font-size:12px; }
.group-meta { font-size:11px; color:#555; margin-bottom:4px; }
.status { font-size:10px; padding:2px 4px; border-radius:3px; display:inline-block; background:#e3e3e3; }
.status.confirmed { background:#d1f4d1; }
.status.cancelled { background:#f7c1c1; }
.status.seated { background:#d1e6f4; }
.status.finished { background:#ddd; }
.status.pending { background:#ffe7b8; }
.status.no_show { background:#ffdddd; }
.now { background:#fff9d6; }
footer { margin-top:12px; font-size:10px; color:#666; text-align:right; }
</style>
</head>
<body>
<h1>Rezervace – <?= htmlspecialchars($dateInput) ?> (<?= $groupMode==='time'?'podle času':'podle stolů' ?>)</h1>
<p class="summary">
    Celkem rezervací: <strong><?= $totalReservations ?></strong>,
    osob dohromady: <strong><?= $totalPersons ?></strong>,
    generováno: <?= date('Y-m-d H:i') ?>.
</p>
<?php if ($totalReservations === 0): ?>
<p><em>Žádné rezervace pro zvolené datum.</em></p>
<?php else:
$nowTime = date('H:i');
$first = true;
foreach ($grouped as $groupKey => $list):
    if (!$first) {
        // echo '<div class="break"></div>';
    }
    $first = false;
    $countGroup = count($list);
    $personsGroup = array_sum(array_map(fn($r)=>(int)$r['persons'], $list));
?>
<h2><?= htmlspecialchars($groupKey) ?></h2>
<div class="group-meta">Rezervací: <?= $countGroup ?> | Osob: <?= $personsGroup ?></div>
<table>
    <thead>
    <tr>
        <?php if ($groupMode!=='table'): ?><th style="width:55px;">Stůl</th><?php endif; ?>
        <?php if ($groupMode!=='time'): ?>
            <th style="width:46px;">Začátek</th>
            <th style="width:46px;">Konec</th>
        <?php else: ?>
            <th style="width:46px;">Konec</th>
        <?php endif; ?>
        <th style="width:120px;">Jméno</th>
        <th style="width:35px;">Os.</th>
        <th>Poznámka</th>
        <th style="width:60px;">Stav</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($list as $r):
        $isNow = (!empty($r['start_time']) && !empty($r['end_time'])
            && $r['start_time'] <= $nowTime && $r['end_time'] > $nowTime);
        $rowClass = $isNow ? 'now' : '';
        $statusRaw = strtolower($r['status'] ?? '');
        $tableLabel = $r['table_code'] ?: $r['table_number']; // pro řádky v režimu time
    ?>
    <tr class="<?= $rowClass ?>">
        <?php if ($groupMode!=='table'): ?>
            <td><?= htmlspecialchars($tableLabel) ?></td>
        <?php endif; ?>
        <?php if ($groupMode!=='time'): ?>
            <td><?= htmlspecialchars($r['start_time']) ?></td>
            <td><?= htmlspecialchars($r['end_time']) ?></td>
        <?php else: ?>
            <td><?= htmlspecialchars($r['end_time']) ?></td>
        <?php endif; ?>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= (int)$r['persons'] ?></td>
        <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
        <td><span class="status <?= preg_match('/^(pending|confirmed|seated|finished|cancelled|no_show)$/',$statusRaw)?$statusRaw:'' ?>">
            <?= htmlspecialchars($r['status'] ?? '') ?>
        </span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; endif; ?>
<footer>Vygenerováno uživatelem: <?= htmlspecialchars($_SESSION['order_user']) ?></footer>
</body>
</html>
<?php
$html = ob_get_clean();

/* Autoload (symlink + fallback) */
$autoloads = [
    __DIR__.'/../../vendor/autoload.php',
    __DIR__.'/../../pizza/vendor/autoload.php',
];
$ok=false;
foreach($autoloads as $p){
    if(is_file($p)){
        require_once $p;
        if(class_exists('Dompdf\\Dompdf')){ $ok=true; break; }
    }
}
if(!$ok){
    http_response_code(500);
    echo "Autoload / Dompdf nenalezen";
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html,'UTF-8');
$orientation = ($groupMode==='table') ? 'landscape':'portrait';
$dompdf->setPaper('A4', $orientation);
$dompdf->render();

try {
    $canvas = $dompdf->get_canvas();
    $font = $dompdf->getFontMetrics()->get_font('DejaVu Sans','normal');
    $x = ($orientation==='portrait')?510:760;
    $y = ($orientation==='portrait')?820:580;
    $canvas->page_text($x,$y,"Strana {PAGE_NUM} / {PAGE_COUNT}",$font,9,[0,0,0]);
} catch(Throwable $e){}

$filename = 'rezervace_'.$dateInput.'_'.$groupMode.'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
echo $dompdf->output();