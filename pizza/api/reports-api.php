<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

/* ---------- DB ---------- */
function getDb() {
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4',
            'pizza_user',
            'pizza',
            [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
        );
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database connection failed: '.$e->getMessage()];
    }
}

/* ---------- DATE RANGE HELPERS ---------- */
function generateDateRangePHP($startDate, $endDate) {
    $dates = [];
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    $end   = $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period   = new DatePeriod($start, $interval, $end);
    foreach ($period as $date) {
        $dates[] = $date->format('Y-m-d');
    }
    error_log("generateDateRangePHP: $startDate to $endDate = ".implode(', ', $dates));
    return $dates;
}

/* ---------- CATEGORY PROCESS ---------- */
function processCategory($category) {
    if (is_array($category)) {
        $placeholders = str_repeat('?,', count($category) - 1) . '?';
        return ['condition' => "oi.item_type IN ($placeholders)", 'params' => $category];
    } elseif ($category !== 'all') {
        return ['condition' => "oi.item_type = ?", 'params' => [$category]];
    }
    return null;
}

/* ---------- PAYMENT FILTER ---------- */
function addPaymentFilter($sql_base, $params, $payment_method) {
    if ($payment_method === null || $payment_method === '') {
        return [$sql_base, $params];
    }
    $map = [
        'hotovost' => 'cash',
        'karta'    => 'card',
        'cash'     => 'cash',
        'card'     => 'card'
    ];
    $pm = $map[$payment_method] ?? $payment_method;
    $sql_base .= " AND oi.payment_method = ?";
    $params[] = $pm;
    return [$sql_base, $params];
}

/* ---------- COMMON EXPRESSIONS (HYBRID MODEL) ---------- */

/*
  Paid logic:
  - Legacy fully-paid: status='paid' AND paid_quantity IS NULL/0
  - New partial/full: paid_quantity > 0 OR paid_at set
  - Exclude cancelled.
*/
function paidFilter() {
    return " oi.status <> 'cancelled'
             AND (
                  oi.status='paid'
                  OR COALESCE(oi.paid_quantity,0) > 0
                  OR (oi.paid_at IS NOT NULL AND oi.paid_at <> '0000-00-00 00:00:00')
             ) ";
}

/*
  Date filter for PAID side: use paid_at fallback to created_at for legacy.
*/
function dateFilterPaid($placeholders) {
    return " DATE(COALESCE(NULLIF(oi.paid_at,'0000-00-00 00:00:00'), o.created_at)) IN ($placeholders) ";
}

/*
  Date filter for OPEN (unpaid/remaining) side: operate on creation date of order
  (can be changed to table_sessions or reservation date if needed).
*/
function dateFilterOpen($placeholders) {
    return " DATE(o.created_at) IN ($placeholders) ";
}

/*
  Paid quantity expression – if legacy paid (status='paid', paid_quantity=0) -> full oi.quantity
  else min(quantity, paid_quantity)
*/
function paidQuantityExpr() {
    return " CASE
               WHEN oi.status='paid' AND COALESCE(oi.paid_quantity,0)=0 THEN oi.quantity
               ELSE LEAST(oi.quantity, COALESCE(oi.paid_quantity,0))
             END ";
}

/*
  Paid revenue
*/
function paidRevenueExpr() {
    return " (oi.unit_price * ".paidQuantityExpr().") ";
}

/*
  Remaining (open) quantity = quantity - paid part (never negative)
*/
function remainingQuantityExpr() {
    return " GREATEST(oi.quantity - 
              CASE
                WHEN oi.status='paid' AND COALESCE(oi.paid_quantity,0)=0 THEN oi.quantity
                ELSE LEAST(oi.quantity, COALESCE(oi.paid_quantity,0))
              END
            , 0) ";
}

/*
  Open filter = Not cancelled, has some remaining quantity (not fully paid)
*/
function openFilter() {
    return " oi.status <> 'cancelled'
             AND (".remainingQuantityExpr()." > 0) ";
}

/* ---------- EMPLOYEES LIST (PAID ONLY) ---------- */
function getEmployees() {
    $db = getDb();
    if (is_array($db) && isset($db['status']) && $db['status']==='error') {
        return $db;
    }
    $pdo = $db;
    try {
        $sql = "
            SELECT 
                o.employee_name AS name,
                COUNT(DISTINCT o.id) AS count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.employee_name IS NOT NULL
              AND o.employee_name <> ''
              AND ".paidFilter()."
              AND DATE(COALESCE(NULLIF(oi.paid_at,'0000-00-00 00:00:00'), o.created_at)) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY o.employee_name
            ORDER BY count DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => ['employees' => $employees]];
    } catch (PDOException $e) {
        return ['status'=>'error','message'=>$e->getMessage()];
    }
}

/* ---------- MAIN SALES DATA ---------- */
function getSalesData($params = []) {
    $db = getDb();
    if (is_array($db) && isset($db['status']) && $db['status']==='error') {
        return $db;
    }
    $pdo = $db;

    error_log("=== getSalesData DEBUG ===");
    error_log("Raw params: ".json_encode($params));

    $dates = $params['dates'] ?? [date('Y-m-d')];
    if (is_string($dates)) {
        if (strpos($dates, ' to ') !== false) {
            $parts = explode(' to ', $dates);
            if (count($parts) === 2) {
                $dates = generateDateRangePHP(trim($parts[0]), trim($parts[1]));
            } else {
                $dates = [trim($dates)];
            }
        } else {
            $dates = [trim($dates)];
        }
    }
    if (is_array($dates)) {
        $dates = array_values(array_unique(array_filter(array_map('trim',$dates))));
    }
    if (empty($dates)) {
        $dates = [date('Y-m-d')];
    }

    $category       = $params['category'] ?? 'all';
    $employee_name  = $params['employee_name'] ?? '';
    $payment_method = $params['payment_method'] ?? null;
    $view           = $params['view'] ?? 'default';
    $include_open   = array_key_exists('include_open',$params) ? (bool)$params['include_open'] : true;

    if ($payment_method === '') $payment_method = null;

    $placeholders = str_repeat('?,', count($dates) - 1) . '?';

    $result = [];

    try {
        /* ---------- DEFAULT VIEW ---------- */
        if ($view === 'default') {
            // SUMMARIES (paid)
            $sql_base = "
                SELECT
                    SUM(".paidRevenueExpr().") AS total,
                    COUNT(DISTINCT o.id) AS pocet
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $daily_params = $dates;
            list($sql_base, $daily_params) = addPaymentFilter($sql_base, $daily_params, $payment_method);

            $catFilter = processCategory($category);
            if ($catFilter) {
                $sql_base .= " AND ".$catFilter['condition'];
                $daily_params = array_merge($daily_params, $catFilter['params']);
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $daily_params[] = $employee_name;
            }
            $stmt = $pdo->prepare($sql_base);
            $stmt->execute($daily_params);
            $dailyStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // PRODUCTS (paid)
            $sql_base = "
                SELECT
                    CASE
                        WHEN oi.item_name LIKE '00.%' THEN REPLACE(oi.item_name,'00. ','')
                        WHEN oi.item_name LIKE '01.%' THEN REPLACE(oi.item_name,'01. ','')
                        WHEN oi.item_name LIKE '02.%' THEN REPLACE(oi.item_name,'02. ','')
                        WHEN oi.item_name LIKE '03.%' THEN REPLACE(oi.item_name,'03. ','')
                        WHEN oi.item_name LIKE '04.%' THEN REPLACE(oi.item_name,'04. ','')
                        WHEN oi.item_name LIKE '05.%' THEN REPLACE(oi.item_name,'05. ','')
                        WHEN oi.item_name LIKE '06.%' THEN REPLACE(oi.item_name,'06. ','')
                        ELSE oi.item_name
                    END AS nazev,
                    oi.item_type AS kategorie,
                    SUM(".paidQuantityExpr().") AS pocet,
                    SUM(".paidRevenueExpr().") AS trzba
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $product_params = $dates;
            list($sql_base, $product_params) = addPaymentFilter($sql_base, $product_params, $payment_method);

            if ($catFilter) {
                $sql_base .= " AND ".$catFilter['condition'];
                $product_params = array_merge($product_params, $catFilter['params']);
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $product_params[] = $employee_name;
            }
            $sql = $sql_base . "
                GROUP BY nazev, oi.item_type
                ORDER BY pocet DESC
                LIMIT 100
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($product_params);
            $produkty = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result['dnesni_prodeje'] = [
                'total'    => $dailyStats['total'] ?? 0,
                'pocet'    => $dailyStats['pocet'] ?? 0,
                'produkty' => $produkty
            ];
        }

        /* ---------- CATEGORIES VIEW (paid) ---------- */
        if ($view === 'categories') {
            $sql_base = "
                SELECT
                    oi.item_type AS kategorie,
                    SUM(".paidRevenueExpr().") AS trzba,
                    SUM(".paidQuantityExpr().") AS pocet
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $category_params = $dates;
            list($sql_base, $category_params) = addPaymentFilter($sql_base, $category_params, $payment_method);
            $catFilter = processCategory($category);
            if ($catFilter) {
                $sql_base .= " AND ".$catFilter['condition'];
                $category_params = array_merge($category_params, $catFilter['params']);
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $category_params[] = $employee_name;
            }
            $sql = $sql_base . "
                GROUP BY oi.item_type
                ORDER BY trzba DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($category_params);
            $result['kategorie'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---------- TOP ORDERS (paid) ---------- */
        if ($view === 'top_orders') {
            $sql_base = "
                SELECT
                    oi.order_id,
                    SUM(".paidRevenueExpr().") AS amount,
                    MAX(oi.payment_method) AS payment_method,
                    MAX(oi.paid_at) AS paid_at,
                    ts.table_number,
                    o.employee_name
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN table_sessions ts ON o.table_session_id = ts.id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $top_params = $dates;
            if ($payment_method !== null) {
                $map = ['hotovost'=>'cash','karta'=>'card','cash'=>'cash','card'=>'card'];
                $pm = $map[$payment_method] ?? $payment_method;
                $sql_base .= " AND oi.payment_method = ?";
                $top_params[] = $pm;
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $top_params[] = $employee_name;
            }
            $sql = $sql_base . "
                GROUP BY oi.order_id, ts.table_number, o.employee_name
                ORDER BY amount DESC
                LIMIT 15
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($top_params);
            $result['top_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---------- TRENDS (paid) ---------- */
        if ($view === 'trends') {
            $sql_base = "
                SELECT
                    DATE(COALESCE(NULLIF(oi.paid_at,'0000-00-00 00:00:00'), o.created_at)) AS den,
                    SUM(".paidRevenueExpr().") AS trzba,
                    COUNT(DISTINCT o.id) AS pocet_objednavek
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $trends_params = $dates;
            list($sql_base, $trends_params) = addPaymentFilter($sql_base, $trends_params, $payment_method);
            $catFilter = processCategory($category);
            if ($catFilter) {
                $sql_base .= " AND ".$catFilter['condition'];
                $trends_params = array_merge($trends_params, $catFilter['params']);
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $trends_params[] = $employee_name;
            }
            $sql = $sql_base . "
                GROUP BY den
                ORDER BY den ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($trends_params);
            $result['trendy'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---------- ANALYTICS_DATA (paid) ---------- */
        if ($view === 'analytics_data') {
            // Payment methods
            $sql = "
                SELECT
                    oi.payment_method,
                    SUM(".paidRevenueExpr().") AS amount
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
                GROUP BY oi.payment_method
                ORDER BY amount DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dates);
            $result['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Employees
            $sql_base = "
                SELECT
                    o.employee_name,
                    SUM(".paidRevenueExpr().") AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
                  AND o.employee_name IS NOT NULL
                  AND o.employee_name <> ''
            ";
            $employee_params = $dates;
            list($sql_base, $employee_params) = addPaymentFilter($sql_base, $employee_params, $payment_method);
            $sql = $sql_base . "
                GROUP BY o.employee_name
                ORDER BY revenue DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($employee_params);
            $result['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Products
            $sql_base = "
                SELECT
                    CASE
                        WHEN oi.item_name LIKE '00.%' THEN REPLACE(oi.item_name,'00. ','')
                        WHEN oi.item_name LIKE '01.%' THEN REPLACE(oi.item_name,'01. ','')
                        WHEN oi.item_name LIKE '02.%' THEN REPLACE(oi.item_name,'02. ','')
                        WHEN oi.item_name LIKE '03.%' THEN REPLACE(oi.item_name,'03. ','')
                        WHEN oi.item_name LIKE '04.%' THEN REPLACE(oi.item_name,'04. ','')
                        WHEN oi.item_name LIKE '05.%' THEN REPLACE(oi.item_name,'05. ','')
                        WHEN oi.item_name LIKE '06.%' THEN REPLACE(oi.item_name,'06. ','')
                        ELSE oi.item_name
                    END AS nazev,
                    oi.item_type AS kategorie,
                    SUM(".paidQuantityExpr().") AS pocet,
                    SUM(".paidRevenueExpr().") AS trzba
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $analytics_params = $dates;
            list($sql_base, $analytics_params) = addPaymentFilter($sql_base, $analytics_params, $payment_method);
            $sql = $sql_base . "
                GROUP BY nazev, oi.item_type
                ORDER BY pocet DESC
                LIMIT 20
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($analytics_params);
            $analytics_produkty = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!isset($result['dnesni_prodeje'])) $result['dnesni_prodeje'] = [];
            $result['dnesni_prodeje']['produkty'] = $analytics_produkty;

            // Summary
            $sql_base = "
                SELECT
                    SUM(".paidRevenueExpr().") AS total,
                    COUNT(DISTINCT o.id) AS pocet
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $stats_params = $dates;
            list($sql_base, $stats_params) = addPaymentFilter($sql_base, $stats_params, $payment_method);
            $stmt = $pdo->prepare($sql_base);
            $stmt->execute($stats_params);
            $analytics_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['dnesni_prodeje']['total'] = $analytics_stats['total'] ?? 0;
            $result['dnesni_prodeje']['pocet'] = $analytics_stats['pocet'] ?? 0;
        }

        /* ---------- SHARED (categories + analytics views) ---------- */
        if (in_array($view, ['default','analytics','analytics_data'])) {
            // Categories (paid)
            $sql_base = "
                SELECT
                    oi.item_type AS kategorie,
                    SUM(".paidRevenueExpr().") AS trzba,
                    SUM(".paidQuantityExpr().") AS pocet
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $category_params = $dates;
            list($sql_base, $category_params) = addPaymentFilter($sql_base, $category_params, $payment_method);
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $category_params[] = $employee_name;
            }
            $sql = $sql_base . "
                GROUP BY oi.item_type
                ORDER BY trzba DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($category_params);
            $result['kategorie'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($view === 'analytics') {
                // Products again (limit 20)
                $sql_products = "
                    SELECT
                        CASE
                            WHEN oi.item_name LIKE '00.%' THEN REPLACE(oi.item_name,'00. ','')
                            WHEN oi.item_name LIKE '01.%' THEN REPLACE(oi.item_name,'01. ','')
                            WHEN oi.item_name LIKE '02.%' THEN REPLACE(oi.item_name,'02. ','')
                            WHEN oi.item_name LIKE '03.%' THEN REPLACE(oi.item_name,'03. ','')
                            WHEN oi.item_name LIKE '04.%' THEN REPLACE(oi.item_name,'04. ','')
                            WHEN oi.item_name LIKE '05.%' THEN REPLACE(oi.item_name,'05. ','')
                            WHEN oi.item_name LIKE '06.%' THEN REPLACE(oi.item_name,'06. ','')
                            ELSE oi.item_name
                        END AS nazev,
                        oi.item_type AS kategorie,
                        SUM(".paidQuantityExpr().") AS pocet,
                        SUM(".paidRevenueExpr().") AS trzba
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE ".dateFilterPaid($placeholders)."
                      AND ".paidFilter()."
                ";
                $product_params = $dates;
                list($sql_products, $product_params) = addPaymentFilter($sql_products, $product_params, $payment_method);
                if ($employee_name) {
                    $sql_products .= " AND o.employee_name = ?";
                    $product_params[] = $employee_name;
                }
                $sql_products .= "
                    GROUP BY nazev, oi.item_type
                    ORDER BY pocet DESC
                    LIMIT 20
                ";
                $stmt = $pdo->prepare($sql_products);
                $stmt->execute($product_params);
                $analytics_produkty = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!isset($result['dnesni_prodeje'])) $result['dnesni_prodeje'] = [];
                $result['dnesni_prodeje']['produkty'] = $analytics_produkty;

                // Summary for analytics
                $sql_stats = "
                    SELECT
                        SUM(".paidRevenueExpr().") AS total,
                        COUNT(DISTINCT o.id) AS pocet
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE ".dateFilterPaid($placeholders)."
                      AND ".paidFilter()."
                ";
                $stats_params = $dates;
                list($sql_stats, $stats_params) = addPaymentFilter($sql_stats, $stats_params, $payment_method);
                if ($employee_name) {
                    $sql_stats .= " AND o.employee_name = ?";
                    $stats_params[] = $employee_name;
                }
                $stmt = $pdo->prepare($sql_stats);
                $stmt->execute($stats_params);
                $analytics_stats = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['dnesni_prodeje']['total'] = $analytics_stats['total'] ?? 0;
                $result['dnesni_prodeje']['pocet'] = $analytics_stats['pocet'] ?? 0;
            }
        }

        /* ---------- FOOD COST & MARGINS (paid only) ---------- */
        if (in_array($view, ['default','analytics','analytics_data'])) {
            $sql_foodcost = "
                SELECT
                    SUM(CASE WHEN oi.item_type IN ('pizza','pasta','predkrm','dezert')
                        THEN ".paidRevenueExpr()." ELSE 0 END) AS food_revenue,
                    SUM(CASE WHEN oi.item_type IN ('pizza','pasta','predkrm','dezert')
                        THEN COALESCE(pt.cost_price,0) * ".paidQuantityExpr()." ELSE 0 END) AS food_costs,
                    SUM(CASE WHEN oi.item_type IN ('drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv')
                        THEN ".paidRevenueExpr()." ELSE 0 END) AS drink_revenue,
                    SUM(CASE WHEN oi.item_type IN ('drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv')
                        THEN COALESCE(dt.cost_price,0) * ".paidQuantityExpr()." ELSE 0 END) AS drink_costs,
                    SUM(".paidRevenueExpr().") AS total_revenue,
                    SUM(COALESCE(pt.cost_price, dt.cost_price,0) * ".paidQuantityExpr().") AS total_costs
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN pizza_types pt ON oi.item_type IN ('pizza','pasta','predkrm','dezert')
                  AND oi.item_name LIKE CONCAT('%', pt.name, '%')
                LEFT JOIN drink_types dt ON oi.item_type IN ('drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv')
                  AND oi.item_name LIKE CONCAT('%', dt.name, '%')
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $food_params = $dates;
            list($sql_foodcost, $food_params) = addPaymentFilter($sql_foodcost, $food_params, $payment_method);
            if ($employee_name) {
                $sql_foodcost .= " AND o.employee_name = ?";
                $food_params[] = $employee_name;
            }
            $stmt = $pdo->prepare($sql_foodcost);
            $stmt->execute($food_params);
            $fc = $stmt->fetch(PDO::FETCH_ASSOC);

            $food_revenue  = (float)($fc['food_revenue'] ?? 0);
            $food_costs    = (float)($fc['food_costs'] ?? 0);
            $drink_revenue = (float)($fc['drink_revenue'] ?? 0);
            $drink_costs   = (float)($fc['drink_costs'] ?? 0);
            $total_rev     = (float)($fc['total_revenue'] ?? 0);
            $total_costs   = (float)($fc['total_costs'] ?? 0);

            $result['food_cost_analysis'] = [
                'food' => [
                    'revenue'      => $food_revenue,
                    'costs'        => $food_costs,
                    'margin'       => $food_revenue - $food_costs,
                    'cost_percent' => $food_revenue>0 ? $food_costs / $food_revenue * 100 : 0
                ],
                'drinks' => [
                    'revenue'      => $drink_revenue,
                    'costs'        => $drink_costs,
                    'margin'       => $drink_revenue - $drink_costs,
                    'cost_percent' => $drink_revenue>0 ? $drink_costs / $drink_revenue * 100 : 0
                ],
                'total' => [
                    'revenue'      => $total_rev,
                    'costs'        => $total_costs,
                    'margin'       => $total_rev - $total_costs,
                    'cost_percent' => $total_rev>0 ? $total_costs / $total_rev * 100 : 0
                ]
            ];

            // Top margin items
            $sql_margin = "
                SELECT
                    CASE
                        WHEN oi.item_name LIKE '00.%' THEN REPLACE(oi.item_name,'00. ','')
                        WHEN oi.item_name LIKE '01.%' THEN REPLACE(oi.item_name,'01. ','')
                        WHEN oi.item_name LIKE '02.%' THEN REPLACE(oi.item_name,'02. ','')
                        WHEN oi.item_name LIKE '03.%' THEN REPLACE(oi.item_name,'03. ','')
                        WHEN oi.item_name LIKE '04.%' THEN REPLACE(oi.item_name,'04. ','')
                        WHEN oi.item_name LIKE '05.%' THEN REPLACE(oi.item_name,'05. ','')
                        WHEN oi.item_name LIKE '06.%' THEN REPLACE(oi.item_name,'06. ','')
                        ELSE oi.item_name
                    END AS nazev,
                    oi.item_type AS kategorie,
                    SUM(".paidQuantityExpr().") AS pocet,
                    SUM(".paidRevenueExpr().") AS trzba,
                    AVG(COALESCE(pt.cost_price, dt.cost_price,0)) AS avg_cost_price,
                    SUM(".paidRevenueExpr().") - SUM(COALESCE(pt.cost_price, dt.cost_price,0) * ".paidQuantityExpr().") AS total_margin,
                    CASE WHEN SUM(".paidRevenueExpr().")>0
                         THEN (SUM(COALESCE(pt.cost_price, dt.cost_price,0) * ".paidQuantityExpr().") / SUM(".paidRevenueExpr()."))*100
                         ELSE 0 END AS cost_percent
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN pizza_types pt ON oi.item_type IN ('pizza','pasta','predkrm','dezert')
                  AND oi.item_name LIKE CONCAT('%', pt.name, '%')
                LEFT JOIN drink_types dt ON oi.item_type IN ('drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv')
                  AND oi.item_name LIKE CONCAT('%', dt.name, '%')
                WHERE ".dateFilterPaid($placeholders)."
                  AND ".paidFilter()."
            ";
            $margin_params = $dates;
            list($sql_margin, $margin_params) = addPaymentFilter($sql_margin, $margin_params, $payment_method);
            if ($employee_name) {
                $sql_margin .= " AND o.employee_name = ?";
                $margin_params[] = $employee_name;
            }
            $sql_margin .= "
                GROUP BY nazev, oi.item_type
                ORDER BY total_margin DESC
                LIMIT 15
            ";
            $stmt = $pdo->prepare($sql_margin);
            $stmt->execute($margin_params);
            $result['top_margin_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---------- OPEN (UNPAID / FUTURE) ITEMS (optional) ---------- */
        if ($include_open) {
            $sql_base = "
                SELECT
                    oi.id AS item_id,
                    oi.order_id,
                    o.employee_name,
                    ts.table_number,
                    oi.item_name,
                    oi.item_type,
                    oi.status,
                    oi.quantity,
                    COALESCE(oi.paid_quantity,0) AS paid_quantity,
                    ".remainingQuantityExpr()." AS remaining_qty,
                    (oi.unit_price * ".remainingQuantityExpr().") AS remaining_revenue,
                    oi.unit_price,
                    o.created_at AS order_created_at,
                    oi.paid_at,
                    oi.payment_method
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN table_sessions ts ON o.table_session_id = ts.id
                WHERE ".dateFilterOpen($placeholders)."
                  AND ".openFilter()."
            ";
            $open_params = $dates;

            // Category filter also applies to open if requested
            $catFilter = processCategory($category);
            if ($catFilter) {
                $sql_base .= " AND ".$catFilter['condition'];
                $open_params = array_merge($open_params, $catFilter['params']);
            }
            if ($employee_name) {
                $sql_base .= " AND o.employee_name = ?";
                $open_params[] = $employee_name;
            }
            if ($payment_method !== null && $payment_method !== '') {
                list($sql_base, $open_params) = addPaymentFilter($sql_base, $open_params, $payment_method);
            }

            $sql = $sql_base . "
                ORDER BY o.created_at ASC, oi.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($open_params);
            $open_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregate potential
            $open_total_qty = 0;
            $open_total_revenue = 0.0;
            foreach ($open_items as $row) {
                $open_total_qty    += (int)$row['remaining_qty'];
                $open_total_revenue += (float)$row['remaining_revenue'];
            }
            $result['open_summary'] = [
                'remaining_qty'     => $open_total_qty,
                'remaining_revenue' => $open_total_revenue
            ];
            $result['open_items'] = $open_items;
        }

        return [
            'status' => 'success',
            'time'   => date('Y-m-d H:i:s'),
            'user'   => $_SESSION['username'] ?? 'centycz',
            'params' => $params,
            'data'   => $result
        ];
    } catch (PDOException $e) {
        error_log("SQL Error: ".$e->getMessage());
        return ['status'=>'error','message'=>$e->getMessage()];
    }
}

/* ---------- ROUTING ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['action']) && $_GET['action']==='get-employees') {
        echo json_encode(getEmployees());
        exit;
    }
    echo json_encode(['status'=>'error','message'=>'Invalid GET request']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = file_get_contents('php://input');
    $params = json_decode($input, true);
    if ($params === null) {
        echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
        exit;
    }
    $result = getSalesData($params);
    echo json_encode($result);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid request method']);
?>