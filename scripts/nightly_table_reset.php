<?php
/**
 * Nightly reset stolů – CRON 05:00
 * - Zavře osiřelé sessions (bez aktivní objednávky)
 * - Uvolní stoly (occupied/to_clean) bez aktivní session, objednávky a dnešní živé rezervace
 * - Fallback úklid
 *
 * CRON:
 * 0 5 * * * /usr/bin/php /cesta/ke/projektu/scripts/nightly_table_reset.php >> /var/log/pizza/nightly_table_reset.log 2>&1
 */
date_default_timezone_set('Europe/Prague');
require_once __DIR__ . '/../includes/reservations_lib.php';

$pdo = getReservationDb();
$ACTIVE_ORDER_STATUSES = ['pending','preparing','in_progress','served']; // uprav dle reálných stavů
$log = [];
$log[] = "=== Nightly table reset start: ".date('Y-m-d H:i:s')." ===";

try {
    $statusesSql = implode("','", array_map('addslashes', $ACTIVE_ORDER_STATUSES));

    // 1) Zavření osiřelých sessions
    $sqlClose = "
        UPDATE table_sessions ts
        LEFT JOIN (
            SELECT o.table_session_id
            FROM orders o
            WHERE o.status IN ('{$statusesSql}')
            GROUP BY o.table_session_id
        ) ao ON ao.table_session_id = ts.id
        SET ts.is_active=0,
            ts.end_time = IF(ts.end_time IS NULL, NOW(), ts.end_time)
        WHERE ts.is_active=1
          AND ao.table_session_id IS NULL
    ";
    $aff1 = $pdo->exec($sqlClose);
    $log[] = "Closed orphan sessions: $aff1";

    // 2) Uvolnění stolů
    $sqlFree = "
        UPDATE restaurant_tables rt
        LEFT JOIN (
            SELECT DISTINCT table_number FROM table_sessions WHERE is_active=1
        ) s ON s.table_number = rt.table_number
        LEFT JOIN (
            SELECT DISTINCT ts.table_number
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            WHERE o.status IN ('{$statusesSql}')
        ) ao ON ao.table_number = rt.table_number
        LEFT JOIN (
            SELECT DISTINCT table_number
            FROM reservations
            WHERE reservation_date = CURDATE()
              AND status NOT IN ('cancelled','finished','no_show')
        ) r ON r.table_number = rt.table_number
        SET rt.status='free', rt.session_start=NULL
        WHERE rt.status IN ('occupied','to_clean')
          AND s.table_number IS NULL
          AND ao.table_number IS NULL
          AND r.table_number IS NULL
          AND (rt.status <> 'out_of_order')
    ";
    $aff2 = $pdo->exec($sqlFree);
    $log[] = "Freed tables: $aff2";

    // 3) Fallback
    $sqlFallback = "
        UPDATE restaurant_tables rt
        LEFT JOIN (
            SELECT DISTINCT table_number FROM table_sessions WHERE is_active=1
        ) s ON s.table_number = rt.table_number
        LEFT JOIN (
            SELECT DISTINCT ts.table_number
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            WHERE o.status IN ('{$statusesSql}')
        ) ao ON ao.table_number = rt.table_number
        SET rt.status='free', rt.session_start=NULL
        WHERE rt.status='occupied'
          AND s.table_number IS NULL
          AND ao.table_number IS NULL
    ";
    $aff3 = $pdo->exec($sqlFallback);
    $log[] = "Fallback freed: $aff3";

    $log[] = "=== Nightly table reset done ===";
} catch (Throwable $e) {
    $log[] = "ERROR: ".$e->getMessage();
}

$out = implode("\n",$log)."\n";
if (PHP_SAPI === 'cli') {
    echo $out;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $out;
}