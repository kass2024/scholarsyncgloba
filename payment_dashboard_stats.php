<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

if (!isset($_SESSION['id']) || !pcvc_current_user_is_superadmin($conn)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

/* =====================================================
   HARD GUARD
===================================================== */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Database connection not available'
    ]);
    exit;
}

/* =====================================================
   SAFE QUERY HELPER
===================================================== */
function run(mysqli $conn, string $sql, string $stage): mysqli_result {
    try {
        $res = $conn->query($sql);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'stage' => $stage,
            'mysqli_error' => $e->getMessage(),
        ], JSON_PRETTY_PRINT);
        exit;
    }
    if (!$res) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'stage' => $stage,
            'mysqli_error' => $conn->error
        ], JSON_PRETTY_PRINT);
        exit;
    }
    return $res;
}

/**
 * SQL LEFT JOINs to resolve applicant name by application_packages / payment_receipts source_table.
 */
function pcvc_dashboard_student_joins_sql(string $applicationIdCol, string $sourceTableCol): string
{
    return "
        LEFT JOIN credit_transfer_applications pcvc_cta
            ON {$sourceTableCol} = 'credit_transfer_applications'
           AND pcvc_cta.id = {$applicationIdCol}
        LEFT JOIN upafa_registrations pcvc_ur
            ON {$sourceTableCol} = 'upafa_registrations'
           AND pcvc_ur.id = {$applicationIdCol}
        LEFT JOIN student_applications pcvc_sta
            ON ({$sourceTableCol} = 'student_applications'
                OR {$sourceTableCol} IS NULL
                OR {$sourceTableCol} = '')
           AND pcvc_sta.id = {$applicationIdCol}
        LEFT JOIN malta_applications pcvc_ma
            ON {$sourceTableCol} = 'malta_applications'
           AND pcvc_ma.id = {$applicationIdCol}
        LEFT JOIN turkey_applications pcvc_ta
            ON {$sourceTableCol} = 'turkey_applications'
           AND pcvc_ta.id = {$applicationIdCol}
    ";
}

/**
 * SQL expression for display name matched to source_table (avoids id collisions across tables).
 */
function pcvc_dashboard_student_name_sql(string $sourceTableCol): string
{
    return "
        NULLIF(TRIM(CASE {$sourceTableCol}
            WHEN 'credit_transfer_applications' THEN CONCAT_WS(' ', pcvc_cta.first_name, pcvc_cta.middle_name, pcvc_cta.last_name)
            WHEN 'upafa_registrations'            THEN CONCAT_WS(' ', pcvc_ur.first_name, pcvc_ur.last_name)
            WHEN 'malta_applications'             THEN CONCAT_WS(' ', pcvc_ma.name, pcvc_ma.surname)
            WHEN 'turkey_applications'            THEN CONCAT_WS(' ', pcvc_ta.first_name, pcvc_ta.last_name)
            ELSE CONCAT_WS(' ', pcvc_sta.first_name, pcvc_sta.last_name)
        END), '')
    ";
}

function pcvc_dashboard_student_email_sql(string $sourceTableCol): string
{
    return "
        NULLIF(TRIM(CASE {$sourceTableCol}
            WHEN 'credit_transfer_applications' THEN pcvc_cta.email
            WHEN 'upafa_registrations'            THEN pcvc_ur.email
            WHEN 'malta_applications'             THEN pcvc_ma.email
            WHEN 'turkey_applications'            THEN pcvc_ta.email
            ELSE pcvc_sta.email
        END), '')
    ";
}

/* =====================================================
   KPI LIST MODE (MODAL)
   ?status=fully_paid | partial_paid | unpaid | outstanding
===================================================== */
$statusFilter = $_GET['status'] ?? null;

if ($statusFilter !== null) {

    $allowed = ['fully_paid', 'partial_paid', 'unpaid', 'outstanding'];
    if (!in_array($statusFilter, $allowed, true)) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT
            ap.application_id,
            ap.source_table,
            " . pcvc_dashboard_student_email_sql('ap.source_table') . " AS email,
            COALESCE(" . pcvc_dashboard_student_name_sql('ap.source_table') . ", 'Unknown Student') AS student_name,

            COUNT(fi.id) AS total_items,

            SUM(
                CASE
                    WHEN COALESCE(pi.paid_amount,0) >= fi.amount
                    THEN 1 ELSE 0
                END
            ) AS fully_paid_items,

            SUM(
                CASE
                    WHEN COALESCE(pi.paid_amount,0) > 0
                    THEN 1 ELSE 0
                END
            ) AS paid_items,

            SUM(fi.amount) AS expected,
            COALESCE(SUM(pi.paid_amount),0) AS paid,
            MAX(pi.last_payment) AS last_payment

        FROM application_packages ap
        JOIN fee_items fi
            ON fi.package_id = ap.package_id

        LEFT JOIN (
            SELECT
                application_id,
                source_table,
                fee_item_id,
                SUM(amount_paid) AS paid_amount,
                MAX(paid_at) AS last_payment
            FROM application_payments
            WHERE status = 'PAID'
            GROUP BY application_id, source_table, fee_item_id
        ) pi
            ON pi.application_id = ap.application_id
           AND pi.source_table = ap.source_table
           AND pi.fee_item_id = fi.id

        " . pcvc_dashboard_student_joins_sql('ap.application_id', 'ap.source_table') . "

        GROUP BY ap.application_id, ap.source_table
    ";

    $res = run($conn, $sql, 'kpi_list');
    $rows = [];

    while ($r = $res->fetch_assoc()) {

        if ($r['fully_paid_items'] == $r['total_items']) {
            $status = 'fully_paid';
        } elseif ($r['paid_items'] > 0) {
            $status = 'partial_paid';
        } else {
            $status = 'unpaid';
        }

        $match =
            ($statusFilter === 'fully_paid'   && $status === 'fully_paid') ||
            ($statusFilter === 'partial_paid' && $status === 'partial_paid') ||
            ($statusFilter === 'unpaid'       && $status === 'unpaid') ||
            ($statusFilter === 'outstanding'  && $r['paid'] < $r['expected']);

        if ($match) {
            $rows[] = [
                'application_id' => (int)$r['application_id'],
                'student_name'   => $r['student_name'] ?: 'Unknown Student',
                'email'          => $r['email'],
                'expected'       => (float)$r['expected'],
                'total_paid'     => (float)$r['paid'],
                'balance'        => max(0, (float)$r['expected'] - (float)$r['paid']),
                'status'         => $status,
                'last_payment'   => $r['last_payment']
            ];
        }
    }

    echo json_encode($rows, JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   1. EXPECTED / COLLECTED BY CURRENCY (active receipts only)
===================================================== */
$expectedByCurrencySql = "
    SELECT
        fp.currency,
        COALESCE(SUM(fi.amount), 0) AS expected
    FROM application_packages ap
    JOIN fee_items fi ON fi.package_id = ap.package_id
    JOIN fee_packages fp ON fp.id = fi.package_id
    WHERE fp.currency IS NOT NULL AND fp.currency <> ''
    GROUP BY fp.currency
";
$expectedByCurrency = [];
$res = run($conn, $expectedByCurrencySql, 'expected_by_currency');
while ($row = $res->fetch_assoc()) {
    $expectedByCurrency[$row['currency']] = (float) $row['expected'];
}

$collectedByCurrencySql = "
    SELECT
        COALESCE(fp.currency, '—') AS currency,
        COALESCE(SUM(pr.total_amount), 0) AS collected,
        COUNT(*) AS receipt_count
    FROM payment_receipts pr
    LEFT JOIN fee_packages fp ON fp.id = pr.package_id
    WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
    GROUP BY COALESCE(fp.currency, '—')
";
$collectedByCurrency = [];
$activeReceipts = 0;
$res = run($conn, $collectedByCurrencySql, 'collected_by_currency');
while ($row = $res->fetch_assoc()) {
    $cur = (string) $row['currency'];
    $collectedByCurrency[$cur] = (float) $row['collected'];
    $activeReceipts += (int) $row['receipt_count'];
}

$cancelledSql = "
    SELECT COUNT(*) AS c
    FROM payment_receipts
    WHERE status = 'CANCELED'
";
$cancelledReceipts = (int) (run($conn, $cancelledSql, 'cancelled')->fetch_assoc()['c'] ?? 0);

$allCurrencies = array_unique(array_merge(
    array_keys($expectedByCurrency),
    array_keys($collectedByCurrency)
));
sort($allCurrencies);

$byCurrency = [];
$expected = 0.0;
$collected = 0.0;

foreach ($allCurrencies as $currency) {
    if ($currency === '—' || $currency === '') {
        continue;
    }
    $exp = (float) ($expectedByCurrency[$currency] ?? 0);
    $col = (float) ($collectedByCurrency[$currency] ?? 0);
    $expected += $exp;
    $collected += $col;
    $byCurrency[] = [
        'currency'    => $currency,
        'expected'    => $exp,
        'collected'   => $col,
        'outstanding' => max(0, $exp - $col),
    ];
}

/* =====================================================
   3. STATUS COUNTS (ITEM-AWARE)
===================================================== */
$statusSql = "
    SELECT
        SUM(is_fully_paid)   AS fully_paid,
        SUM(is_partial_paid) AS partial_paid,
        SUM(is_unpaid)       AS unpaid
    FROM (
        SELECT
            ap.application_id,

            COUNT(fi.id) AS total_items,

            SUM(
                CASE
                    WHEN COALESCE(pi.paid_amount,0) >= fi.amount
                    THEN 1 ELSE 0
                END
            ) AS fully_paid_items,

            SUM(
                CASE
                    WHEN COALESCE(pi.paid_amount,0) > 0
                    THEN 1 ELSE 0
                END
            ) AS paid_items,

            CASE
                WHEN
                    SUM(CASE WHEN COALESCE(pi.paid_amount,0) >= fi.amount THEN 1 ELSE 0 END)
                    = COUNT(fi.id)
                THEN 1 ELSE 0
            END AS is_fully_paid,

            CASE
                WHEN
                    SUM(CASE WHEN COALESCE(pi.paid_amount,0) > 0 THEN 1 ELSE 0 END) > 0
                AND
                    SUM(CASE WHEN COALESCE(pi.paid_amount,0) >= fi.amount THEN 1 ELSE 0 END)
                    < COUNT(fi.id)
                THEN 1 ELSE 0
            END AS is_partial_paid,

            CASE
                WHEN SUM(CASE WHEN COALESCE(pi.paid_amount,0) > 0 THEN 1 ELSE 0 END) = 0
                THEN 1 ELSE 0
            END AS is_unpaid

        FROM application_packages ap
        JOIN fee_items fi
            ON fi.package_id = ap.package_id
        LEFT JOIN (
            SELECT
                application_id,
                source_table,
                fee_item_id,
                SUM(amount_paid) AS paid_amount
            FROM application_payments
            WHERE status = 'PAID'
            GROUP BY application_id, source_table, fee_item_id
        ) pi
            ON pi.application_id = ap.application_id
           AND pi.source_table = ap.source_table
           AND pi.fee_item_id = fi.id
        GROUP BY ap.application_id, ap.source_table
    ) x
";
$status = run($conn, $statusSql, 'status')->fetch_assoc();

/* =====================================================
   4. PAYMENT METHODS
===================================================== */
$methodsSql = "
    SELECT
        pr.payment_method,
        COALESCE(fp.currency, '—') AS currency,
        SUM(pr.total_amount) AS total
    FROM payment_receipts pr
    LEFT JOIN fee_packages fp ON fp.id = pr.package_id
    WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
    GROUP BY pr.payment_method, COALESCE(fp.currency, '—')
    ORDER BY total DESC
";
$methods = [];
$methodsChart = [];
$res = run($conn, $methodsSql, 'methods');
while ($row = $res->fetch_assoc()) {
    $method = (string) $row['payment_method'];
    $currency = (string) $row['currency'];
    $total = (float) $row['total'];
    $methods[$method] = ($methods[$method] ?? 0) + $total;
    $label = $currency !== '—' ? "{$method} ({$currency})" : $method;
    $methodsChart[] = ['label' => $label, 'total' => $total, 'method' => $method, 'currency' => $currency];
}

/* =====================================================
   5. RECENT PAYMENTS
===================================================== */
$recentSql = "
    SELECT
        COALESCE(" . pcvc_dashboard_student_name_sql('pr.source_table') . ", 'Unknown Student') AS student,
        pr.total_amount AS amount_paid,
        pr.payment_method,
        pr.created_at AS paid_at,
        COALESCE(fp.currency, '') AS currency
    FROM payment_receipts pr
    LEFT JOIN fee_packages fp ON fp.id = pr.package_id
    " . pcvc_dashboard_student_joins_sql('pr.application_id', 'pr.source_table') . "
    WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
    ORDER BY pr.created_at DESC
    LIMIT 10
";

$recent = run($conn, $recentSql, 'recent')->fetch_all(MYSQLI_ASSOC);

/* =====================================================
   FINAL RESPONSE
===================================================== */
echo json_encode([
    'error'              => false,
    'expected'           => $expected,
    'collected'          => $collected,
    'outstanding'        => max(0, $expected - $collected),
    'by_currency'        => $byCurrency,
    'active_receipts'    => $activeReceipts,
    'cancelled_receipts' => $cancelledReceipts,
    'status' => [
        'fully_paid'   => (int) $status['fully_paid'],
        'partial_paid' => (int) $status['partial_paid'],
        'unpaid'       => (int) $status['unpaid'],
    ],
    'methods'       => $methods,
    'methods_chart' => $methodsChart,
    'recent'        => $recent,
], JSON_UNESCAPED_UNICODE);
