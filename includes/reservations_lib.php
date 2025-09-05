<?php
/**
 * reservations_lib.php – čistá verze
 *
 * - seatReservation: vytvoří / připojí session a označí stůl jako 'occupied'
 * - finishReservation: povolí jen bez outstanding položek a pak uzavře session + uvolní stůl
 * - cancelReservation: uvolní stůl jen pokud nejsou outstanding
 * - getOutstandingCountForTable kontroluje neuhrazené položky
 */

/////////////////////////////
// DB CONNECTION
/////////////////////////////
function getReservationDb() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4',
        'pizza_user',
        'pizza',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    return $pdo;
}

/////////////////////////////
// HELPERS
/////////////////////////////
function findCollision(PDO $pdo, DateTime $start, DateTime $end, int $tableNumber, ?int $excludeId = null) {
    $sql = "
        SELECT *
        FROM reservations
        WHERE table_number = ?
          AND status NOT IN ('cancelled','no_show')
          AND (
                (start_datetime < ? AND end_datetime > ?)
             OR (start_datetime < ? AND end_datetime > ?)
          )
    ";
    $params = [
        $tableNumber,
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s')
    ];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: false;
}

function validateWithinOpeningHours(string $time, string $open, string $close): bool {
    return ($time >= $open && $time <= $close);
}

function extractManualDuration($value, int $min=15, int $max=360): ?int {
    if ($value === '' || $value === null) return null;
    if (!is_numeric($value)) return null;
    $v = (int)$value;
    if ($v < $min || $v > $max) return null;
    return $v;
}

/////////////////////////////
// OPENING HOURS
/////////////////////////////
function createOpeningHoursTableIfNotExists(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservation_opening_hours (
            date DATE PRIMARY KEY,
            open_time TIME NOT NULL,
            close_time TIME NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getOpeningHours(PDO $pdo, string $date): array {
    try {
        createOpeningHoursTableIfNotExists($pdo);
        $st = $pdo->prepare("SELECT open_time, close_time FROM reservation_opening_hours WHERE date=?");
        $st->execute([$date]);
        $row = $st->fetch();
        return $row ?: ['open_time'=>'10:00','close_time'=>'23:00'];
    } catch (Throwable $e) {
        return ['open_time'=>'10:00','close_time'=>'23:00'];
    }
}

function setOpeningHours(PDO $pdo, string $date, string $open, string $close): array {
    try {
        createOpeningHoursTableIfNotExists($pdo);
        $st = $pdo->prepare("
            INSERT INTO reservation_opening_hours (date, open_time, close_time)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE open_time=VALUES(open_time), close_time=VALUES(close_time), updated_at=CURRENT_TIMESTAMP
        ");
        $st->execute([$date,$open,$close]);
        return ['ok'=>true];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/////////////////////////////
// CORE CRUD
/////////////////////////////
function createReservation(array $data): array {
    try {
        $pdo = getReservationDb();
        foreach (['customer_name','phone','party_size','reservation_date','reservation_time'] as $f) {
            if (empty($data[$f])) return ['ok'=>false,'error'=>"Pole '$f' je povinné"];
        }
        $time = $data['reservation_time'];
        if (!preg_match('/^\d{2}:\d{2}$/',$time)) return ['ok'=>false,'error'=>'Neplatný formát času'];
        $m = (int)explode(':',$time)[1];
        if (!in_array($m,[0,30],true)) return ['ok'=>false,'error'=>'Rezervace jen na :00 nebo :30'];

        $opening = getOpeningHours($pdo, $data['reservation_date']);
        if (!validateWithinOpeningHours($time,$opening['open_time'],$opening['close_time']))
            return ['ok'=>false,'error'=>'Mimo otevírací dobu'];

        $manualDuration = extractManualDuration($data['manual_duration_minutes'] ?? null);
        $start = new DateTime($data['reservation_date'].' '.$time);
        $end = clone $start;
        $end->modify('+'.($manualDuration ?? 120).' minutes');

        $tableNumber = !empty($data['table_number']) ? (int)$data['table_number'] : null;
        if ($tableNumber) {
            $collision = findCollision($pdo,$start,$end,$tableNumber);
            if ($collision) return ['ok'=>false,'error'=>"Kolize s rezervací ID {$collision['id']}"];
        }

        $hasExtended = false;
        try { $pdo->query("SELECT start_datetime, manual_duration_minutes FROM reservations LIMIT 1"); $hasExtended=true; } catch(Throwable $e) {}

        if ($hasExtended) {
            $st=$pdo->prepare("
                INSERT INTO reservations
                (customer_name, phone, email, party_size,
                 reservation_date, reservation_time,
                 table_number, status, notes,
                 start_datetime, end_datetime, manual_duration_minutes,
                 created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            $st->execute([
                $data['customer_name'],
                $data['phone'],
                $data['email'] ?? null,
                (int)$data['party_size'],
                $data['reservation_date'],
                $time,
                $tableNumber,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null,
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $manualDuration
            ]);
        } else {
            $st=$pdo->prepare("
                INSERT INTO reservations
                (customer_name, phone, email, party_size,
                 reservation_date, reservation_time,
                 table_number, status, notes,
                 created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            $st->execute([
                $data['customer_name'],
                $data['phone'],
                $data['email'] ?? null,
                (int)$data['party_size'],
                $data['reservation_date'],
                $time,
                $tableNumber,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null
            ]);
        }

        return ['ok'=>true,'id'=>$pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function updateReservation(array $data): array {
    try {
        $pdo = getReservationDb();
        if (empty($data['id'])) return ['ok'=>false,'error'=>'Chybí ID'];

        $st=$pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $st->execute([$data['id']]);
        $orig=$st->fetch();
        if(!$orig) return ['ok'=>false,'error'=>'Rezervace nenalezena'];

        $date=$data['reservation_date'] ?? $orig['reservation_date'];
        $time=$data['reservation_time'] ?? $orig['reservation_time'];

        if (!preg_match('/^\d{2}:\d{2}$/',$time)) return ['ok'=>false,'error'=>'Neplatný čas'];
        $m=(int)explode(':',$time)[1];
        if(!in_array($m,[0,30],true)) return ['ok'=>false,'error'=>'Jen :00 nebo :30'];

        $opening=getOpeningHours($pdo,$date);
        if(!validateWithinOpeningHours($time,$opening['open_time'],$opening['close_time']))
            return ['ok'=>false,'error'=>'Mimo otevírací dobu'];

        $manualDuration = array_key_exists('manual_duration_minutes',$data)
            ? extractManualDuration($data['manual_duration_minutes'])
            : (isset($orig['manual_duration_minutes']) ? extractManualDuration($orig['manual_duration_minutes']) : null);

        $start=new DateTime($date.' '.$time);
        $end=clone $start; $end->modify('+'.($manualDuration ?? 120).' minutes');

        $newTable = array_key_exists('table_number',$data)
            ? (!empty($data['table_number'])?(int)$data['table_number']:null)
            : $orig['table_number'];

        if ($newTable) {
            $collision=findCollision($pdo,$start,$end,$newTable,(int)$orig['id']);
            if($collision) return ['ok'=>false,'error'=>"Kolize s ID {$collision['id']}"];
        }

        $newStatus=$data['status'] ?? $orig['status'];

        $hasExtended=false;
        try { $pdo->query("SELECT start_datetime, manual_duration_minutes FROM reservations LIMIT 1"); $hasExtended=true; } catch(Throwable $e){}

        if($hasExtended){
            $sql="
                UPDATE reservations
                SET customer_name=?, phone=?, email=?, party_size=?, reservation_date=?,
                    reservation_time=?, table_number=?, status=?, notes=?,
                    start_datetime=?, end_datetime=?, manual_duration_minutes=?,
                    updated_at=NOW()
                WHERE id=?
            ";
            $params=[
                $data['customer_name'] ?? $orig['customer_name'],
                $data['phone'] ?? $orig['phone'],
                $data['email'] ?? $orig['email'],
                $data['party_size'] ?? $orig['party_size'],
                $date,$time,$newTable,$newStatus,
                $data['notes'] ?? $orig['notes'],
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $manualDuration,
                $orig['id']
            ];
        } else {
            $sql="
                UPDATE reservations
                SET customer_name=?, phone=?, email=?, party_size=?, reservation_date=?,
                    reservation_time=?, table_number=?, status=?, notes=?, updated_at=NOW()
                WHERE id=?
            ";
            $params=[
                $data['customer_name'] ?? $orig['customer_name'],
                $data['phone'] ?? $orig['phone'],
                $data['email'] ?? $orig['email'],
                $data['party_size'] ?? $orig['party_size'],
                $date,$time,$newTable,$newStatus,
                $data['notes'] ?? $orig['notes'],
                $orig['id']
            ];
        }

        $pdo->prepare($sql)->execute($params);
        return ['ok'=>true];
    } catch(Throwable $e){
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function setReservationStatus(int $id,string $status): array {
    try {
        $pdo=getReservationDb();
        $allowed=['pending','confirmed','seated','finished','cancelled','no_show'];
        if(!in_array($status,$allowed,true)) return ['ok'=>false,'error'=>'Neplatný stav'];
        $chk=$pdo->prepare("SELECT 1 FROM reservations WHERE id=?");
        $chk->execute([$id]);
        if(!$chk->fetch()) return ['ok'=>false,'error'=>'Nenalezeno'];
        $pdo->prepare("UPDATE reservations SET status=?, updated_at=NOW() WHERE id=?")->execute([$status,$id]);
        return ['ok'=>true];
    } catch(Throwable $e){
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/////////////////////////////
// OUTSTANDING / SESSION
/////////////////////////////
function getOutstandingCountForTable(PDO $pdo, int $tableNumber): int {
    $sql="
        SELECT COUNT(*)
        FROM order_items oi
        JOIN orders o ON oi.order_id=o.id
        JOIN table_sessions ts ON o.table_session_id=ts.id
        WHERE ts.table_number=?
          AND ts.is_active=1
          AND oi.status NOT IN ('cancelled')
          AND (oi.quantity - COALESCE(oi.paid_quantity,0)) > 0
    ";
    $st=$pdo->prepare($sql);
    $st->execute([$tableNumber]);
    return (int)$st->fetchColumn();
}

function seatReservation(int $id): array {
    try {
        $pdo=getReservationDb();
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT * FROM reservations WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $res=$st->fetch(PDO::FETCH_ASSOC);
        if(!$res){$pdo->rollBack();return ['ok'=>false,'error'=>'Rezervace nenalezena'];}
        if(!in_array($res['status'],['pending','confirmed','seated'],true)){
            $pdo->rollBack();return ['ok'=>false,'error'=>'Nelze posadit ze stavu '.$res['status']];
        }
        if(empty($res['table_number'])) { $pdo->rollBack(); return ['ok'=>false,'error'=>'Chybí stůl']; }
        $tableNumber=(int)$res['table_number'];

        $sess=$pdo->prepare("SELECT id FROM table_sessions WHERE table_number=? AND is_active=1 LIMIT 1 FOR UPDATE");
        $sess->execute([$tableNumber]);
        if(!$sess->fetch()){
            $pdo->prepare("INSERT INTO table_sessions (table_number,start_time,is_active) VALUES (?,NOW(),1)")
                ->execute([$tableNumber]);
        }

        $pdo->prepare("
            UPDATE restaurant_tables
            SET status='occupied', session_start=COALESCE(session_start,NOW())
            WHERE table_number=? AND status<>'occupied'
        ")->execute([$tableNumber]);

        if($res['status']!=='seated'){
            $pdo->prepare("UPDATE reservations SET status='seated', updated_at=NOW() WHERE id=?")->execute([$id]);
        }
        $pdo->commit();
        return ['ok'=>true,'message'=>'Rezervace posazena'];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function finishReservation(int $id): array {
    try{
        $pdo=getReservationDb();
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT * FROM reservations WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $res=$st->fetch(PDO::FETCH_ASSOC);
        if(!$res){$pdo->rollBack();return ['ok'=>false,'error'=>'Rezervace nenalezena'];}
        if($res['status']!=='seated'){ $pdo->rollBack(); return ['ok'=>false,'error'=>'Nelze dokončit ze stavu '.$res['status']]; }
        $tableNumber=(int)$res['table_number'];
        if($tableNumber){
            $out=getOutstandingCountForTable($pdo,$tableNumber);
            if($out>0){
                $pdo->rollBack();
                return ['ok'=>false,'error'=>"Nelze dokončit – stále neuhrazené položky ($out)"];
            }
        }
        $pdo->prepare("UPDATE reservations SET status='finished', updated_at=NOW() WHERE id=?")
            ->execute([$id]);

        if($tableNumber){
            $pdo->prepare("UPDATE table_sessions SET is_active=0, end_time=NOW()
                           WHERE table_number=? AND is_active=1")->execute([$tableNumber]);
            $pdo->prepare("UPDATE restaurant_tables SET status='free', session_start=NULL
                           WHERE table_number=?")->execute([$tableNumber]);
        }
        $pdo->commit();
        return ['ok'=>true,'message'=>'Rezervace dokončena a stůl uvolněn'];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function cancelReservation(int $id): array {
    try{
        $pdo=getReservationDb();
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT * FROM reservations WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $res=$st->fetch(PDO::FETCH_ASSOC);
        if(!$res){$pdo->rollBack();return ['ok'=>false,'error'=>'Rezervace nenalezena'];}
        if(in_array($res['status'],['finished','cancelled','no_show'],true)){
            $pdo->rollBack(); return ['ok'=>false,'error'=>'Nelze zrušit ze stavu '.$res['status']];
        }
        $pdo->prepare("UPDATE reservations SET status='cancelled', updated_at=NOW() WHERE id=?")
            ->execute([$id]);

        $tableNumber=(int)$res['table_number'];
        if($tableNumber){
            $out=getOutstandingCountForTable($pdo,$tableNumber);
            if($out===0){
                $pdo->prepare("UPDATE table_sessions SET is_active=0, end_time=NOW()
                               WHERE table_number=? AND is_active=1")->execute([$tableNumber]);
                $pdo->prepare("UPDATE restaurant_tables SET status='free', session_start=NULL
                               WHERE table_number=?")->execute([$tableNumber]);
            }
        }
        $pdo->commit();
        return ['ok'=>true,'message'=>'Rezervace zrušena'];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function finishReservationForTableIfSeated(PDO $pdo,int $tableNumber): void {
    $pdo->prepare("
        UPDATE reservations SET status='finished', updated_at=NOW()
        WHERE table_number=? AND status='seated'
    ")->execute([$tableNumber]);
}

function getReservations(array $filters=[]): array {
    $pdo=getReservationDb();
    $sql="SELECT * FROM reservations WHERE 1=1";
    $p=[];
    if(!empty($filters['date'])){ $sql.=" AND reservation_date=?"; $p[]=$filters['date']; }
    if(!empty($filters['status'])){ $sql.=" AND status=?"; $p[]=$filters['status']; }
    if(!empty($filters['table_number'])){ $sql.=" AND table_number=?"; $p[]=$filters['table_number']; }
    $sql.=" ORDER BY reservation_date,reservation_time";
    $st=$pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/////////////////////////////
// FREE TABLE HELPER
/////////////////////////////
function freeTableIfNoActive(
    PDO $pdo,
    int $tableNumber,
    array $activeOrderStatuses = ['pending','preparing','in_progress','served'],
    bool $ignoreTodayReservations=false
): bool {
    if (getOutstandingCountForTable($pdo,$tableNumber)>0) return false;

    $q=$pdo->prepare("SELECT 1 FROM table_sessions WHERE table_number=? AND is_active=1 LIMIT 1");
    $q->execute([$tableNumber]);
    if($q->fetch()) return false;

    if ($activeOrderStatuses) {
        $in="'".implode("','",array_map('addslashes',$activeOrderStatuses))."'";
        $q=$pdo->prepare("
            SELECT 1
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            WHERE ts.table_number=? AND o.status IN ($in)
            LIMIT 1
        ");
        $q->execute([$tableNumber]);
        if($q->fetch()) return false;
    }

    if (!$ignoreTodayReservations) {
        $q=$pdo->prepare("
            SELECT 1 FROM reservations
            WHERE table_number=?
              AND reservation_date = CURDATE()
              AND status NOT IN ('cancelled','finished','no_show')
            LIMIT 1
        ");
        $q->execute([$tableNumber]);
        if($q->fetch()) return false;
    }

    $pdo->prepare("
        UPDATE restaurant_tables
        SET status='free', session_start=NULL
        WHERE table_number=? AND status IN ('occupied','to_clean')
    ")->execute([$tableNumber]);

    return true;
}