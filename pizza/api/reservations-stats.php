<?php
/**
 * Reservations Statistics API Endpoint
 * GET /pizza/api/reservations-stats.php?date=YYYY-MM-DD&open_time=HH:MM&close_time=HH:MM
 * 
 * Returns JSON with:
 * - ok: boolean
 * - date: string  
 * - reservation_count: int (excluding cancelled, no_show)
 * - total_persons: int (excluding cancelled, no_show)
 * - slots: array of {time: "HH:MM", persons: int}
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
    
    // Get and validate date parameter
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Neplatný formát data');
    }
    
    // Get opening hours - either from parameters or use defaults
    $openTime = $_GET['open_time'] ?? '16:00';
    $closeTime = $_GET['close_time'] ?? '22:00';
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $openTime) || !preg_match('/^\d{2}:\d{2}$/', $closeTime)) {
        throw new Exception('Neplatný formát času');
    }
    
    $pdo = getReservationDb();
    
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
    ";
    
    $stmt = $pdo->prepare($reservationsQuery);
    $stmt->execute([$date]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate 30-minute slots between open and close times
    $slots = [];
    $openTimestamp = strtotime($openTime);
    $closeTimestamp = strtotime($closeTime);
    
    // Generate slots (30-minute intervals)
    for ($time = $openTimestamp; $time < $closeTimestamp; $time += 1800) { // 1800 seconds = 30 minutes
        $slotTime = date('H:i', $time);
        $slotStart = $time;
        $slotEnd = $time + 1800; // 30 minutes later
        
        $personsInSlot = 0;
        
        // Check each reservation to see if it overlaps with this slot
        foreach ($reservations as $reservation) {
            // Determine reservation start and end times
            if (!empty($reservation['start_datetime']) && !empty($reservation['end_datetime'])) {
                // Use new datetime columns
                $reservationStart = strtotime($reservation['start_datetime']);
                $reservationEnd = strtotime($reservation['end_datetime']);
            } else {
                // Fallback to old columns - assume 2-hour duration
                $reservationStart = strtotime($date . ' ' . $reservation['reservation_time']);
                $reservationEnd = $reservationStart + (2 * 3600); // 2 hours
            }
            
            // Check if reservation overlaps with slot (start inclusive, end exclusive)
            if ($reservationStart < $slotEnd && $reservationEnd > $slotStart) {
                $personsInSlot += (int)$reservation['party_size'];
            }
        }
        
        $slots[] = [
            'time' => $slotTime,
            'persons' => $personsInSlot
        ];
    }
    
    // Return the stats
    echo json_encode([
        'ok' => true,
        'date' => $date,
        'reservation_count' => (int)$dailyStats['reservation_count'],
        'total_persons' => (int)$dailyStats['total_persons'],
        'slots' => $slots
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>