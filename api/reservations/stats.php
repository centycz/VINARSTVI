<?php
/**
 * Reservations Statistics API Endpoint
 * GET /api/reservations/stats.php?date=YYYY-MM-DD[&debug=1]
 * 
 * Returns JSON with:
 * - ok: boolean
 * - date: string  
 * - reservation_count: int (excluding cancelled, no_show)
 * - total_persons: int (excluding cancelled, no_show)
 * - slots: array of {time: "HH:MM", persons: int, rolling_hour_pizzas?: float, capacity_pct?: float}
 * - calculation_mode: string ("production_queue")
 * - pizzas_per_hour: float
 * - params: object (calculation parameters)
 * - reservation_meta?: array (if debug=1)
 */

session_start();

// Check authentication
if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Neautorizovaný přístup']);
    exit;
}

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/reservations_lib.php';
    
    // Production queue algorithm constants (easily tweakable)
    $pizzas_per_hour_effective = 45.0;
    $pizzas_per_person_factor = 0.95;
    $base_overhead_min = 12;
    $group_overhead_per_4 = 2;
    $fudge_factor = 1.15;
    $min_dwell = 30;
    $max_dwell = 75;
    $round_to = 5;
    
    // Get and validate parameters
    $date = $_GET['date'] ?? date('Y-m-d');
    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Neplatný formát data');
    }
    
    $pdo = getReservationDb();
    
    // Get opening hours for the date using existing function
    $openingHours = getOpeningHours($pdo, $date);
    $openTime = $openingHours['open_time'];
    $closeTime = $openingHours['close_time'];
    
    // Get daily aggregates (excluding cancelled, no_show)
    $dailyQuery = "
        SELECT 
            COUNT(*) as reservation_count,
            COALESCE(SUM(party_size), 0) as total_persons
        FROM reservations 
        WHERE reservation_date = ? 
        AND status NOT IN ('cancelled', 'no_show')
    ";
    
    $stmt = $pdo->prepare($dailyQuery);
    $stmt->execute([$date]);
    $dailyStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all reservations for the date (excluding cancelled, no_show)
    $reservationsQuery = "
        SELECT id, party_size, reservation_time, status, start_datetime, end_datetime
        FROM reservations 
        WHERE reservation_date = ? 
        AND status NOT IN ('cancelled', 'no_show')
        ORDER BY reservation_time, party_size DESC
    ";
    
    $stmt = $pdo->prepare($reservationsQuery);
    $stmt->execute([$date]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Helper function to round up to nearest multiple
    function roundUp($value, $multiple) {
        return ceil($value / $multiple) * $multiple;
    }
    
    // Process reservations with production queue algorithm
    $processedReservations = [];
    $timeGroups = [];
    
    // Group reservations by reservation_time
    foreach ($reservations as $reservation) {
        $resTime = $reservation['reservation_time'];
        if (!isset($timeGroups[$resTime])) {
            $timeGroups[$resTime] = [];
        }
        $timeGroups[$resTime][] = $reservation;
    }
    
    // Process each time group
    foreach ($timeGroups as $resTime => $groupReservations) {
        // Sort by party size DESC (largest groups first)
        usort($groupReservations, function($a, $b) {
            return $b['party_size'] - $a['party_size'];
        });
        
        $cumulativeProduction = 0;
        
        foreach ($groupReservations as $reservation) {
            $partySize = (int)$reservation['party_size'];
            
            // Calculate production metrics
            $pizzasEst = $partySize * $pizzas_per_person_factor;
            $productionMinutes = $pizzasEst / ($pizzas_per_hour_effective / 60);
            $overhead = $base_overhead_min + ceil($partySize / 4) * $group_overhead_per_4;
            $rawDwell = ($overhead + $productionMinutes) * $fudge_factor;
            $dwellMinutes = max($min_dwell, min($max_dwell, roundUp($rawDwell, $round_to)));
            
            // Calculate queue offset
            $queueOffset = roundUp($cumulativeProduction, $round_to);
            $cumulativeProduction += $productionMinutes;
            
            // Calculate adjusted times
            $originalStart = strtotime($date . ' ' . $resTime);
            $adjustedStart = $originalStart + ($queueOffset * 60);
            $adjustedEnd = $adjustedStart + ($dwellMinutes * 60);
            
            $processedReservations[] = [
                'id' => $reservation['id'],
                'party_size' => $partySize,
                'reservation_time' => $resTime,
                'pizzas_est' => round($pizzasEst, 1),
                'production_minutes' => round($productionMinutes, 1),
                'dwell_minutes' => $dwellMinutes,
                'queue_offset_minutes' => $queueOffset,
                'adjusted_start' => $adjustedStart,
                'adjusted_end' => $adjustedEnd,
                'adjusted_start_time' => date('H:i', $adjustedStart),
                'adjusted_end_time' => date('H:i', $adjustedEnd)
            ];
        }
    }
    
    // Generate 30-minute slots between open and close times
    $slots = [];
    $openTimestamp = strtotime($date . ' ' . $openTime);
    $closeTimestamp = strtotime($date . ' ' . $closeTime);
    
    // Generate slots (30-minute intervals)
    for ($time = $openTimestamp; $time < $closeTimestamp; $time += 1800) { // 1800 seconds = 30 minutes
        $slotTime = date('H:i', $time);
        $slotStart = $time;
        $slotEnd = $time + 1800; // 30 minutes later
        
        $personsInSlot = 0;
        $rollingHourPizzas = 0.0;
        
        // Count persons using adjusted times (for backward compatibility)
        foreach ($processedReservations as $reservation) {
            if ($reservation['adjusted_start'] < $slotEnd && $reservation['adjusted_end'] > $slotStart) {
                $personsInSlot += (int)$reservation['party_size'];
            }
        }
        
        // Calculate rolling hour pizzas (1-hour window starting from this slot)
        $hourEndTime = $slotStart + 3600; // 1 hour later
        foreach ($processedReservations as $reservation) {
            // Include if production starts within the hour window
            if ($reservation['adjusted_start'] >= $slotStart && $reservation['adjusted_start'] < $hourEndTime) {
                $rollingHourPizzas += $reservation['pizzas_est'];
            }
        }
        
        // Calculate capacity percentage
        $capacityPct = $rollingHourPizzas / $pizzas_per_hour_effective;
        
        $slotData = [
            'time' => $slotTime,
            'persons' => $personsInSlot,
            'rolling_hour_pizzas' => round($rollingHourPizzas, 1),
            'capacity_pct' => round($capacityPct, 2)
        ];
        
        $slots[] = $slotData;
    }
    
    // Prepare response
    $response = [
        'ok' => true,
        'date' => $date,
        'reservation_count' => (int)$dailyStats['reservation_count'],
        'total_persons' => (int)$dailyStats['total_persons'],
        'calculation_mode' => 'production_queue',
        'pizzas_per_hour' => $pizzas_per_hour_effective,
        'params' => [
            'pizzas_per_person_factor' => $pizzas_per_person_factor,
            'base_overhead_min' => $base_overhead_min,
            'group_overhead_per_4' => $group_overhead_per_4,
            'fudge_factor' => $fudge_factor,
            'min_dwell' => $min_dwell,
            'max_dwell' => $max_dwell,
            'round_to' => $round_to
        ],
        'slots' => $slots,
        'opening_hours' => [
            'open_time' => $openTime,
            'close_time' => $closeTime
        ]
    ];
    
    // Add debug information if requested
    if ($debug) {
        $reservationMeta = [];
        foreach ($processedReservations as $res) {
            $reservationMeta[] = [
                'id' => $res['id'],
                'party_size' => $res['party_size'],
                'reservation_time' => $res['reservation_time'],
                'adjusted_start' => $res['adjusted_start_time'],
                'adjusted_end' => $res['adjusted_end_time'],
                'dwell_minutes' => $res['dwell_minutes'],
                'queue_offset_minutes' => $res['queue_offset_minutes'],
                'production_minutes_rounded' => round($res['production_minutes'])
            ];
        }
        $response['reservation_meta'] = $reservationMeta;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>