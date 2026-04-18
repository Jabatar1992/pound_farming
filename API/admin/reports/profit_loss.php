<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

// ── Input params ──────────────────────────────────────────────────
$period   = isset($_GET['period'])   ? strtolower(trim($_GET['period']))   : '';
$flock_id = isset($_GET['flock_id']) ? (int) trim($_GET['flock_id'])       : 0;
$year     = isset($_GET['year'])     ? (int) trim($_GET['year'])           : (int) date('Y');
$month    = isset($_GET['month'])    ? (int) trim($_GET['month'])          : (int) date('n');
$date     = isset($_GET['date'])     ? trim($_GET['date'])                 : date('Y-m-d');

$validPeriods = ['weekly', 'monthly', 'yearly'];

if (!in_array($period, $validPeriods)) {
    respondBadRequest("period is required and must be one of: " . implode(', ', $validPeriods) . ".");
}

// ── Resolve date range from period ───────────────────────────────
switch ($period) {
    case 'weekly':
        // Resolve the week that contains $date (Mon–Sun)
        $ts        = strtotime($date);
        if ($ts === false) {
            respondBadRequest("Invalid date format. Use YYYY-MM-DD.");
        }
        $dayOfWeek  = (int) date('N', $ts); // 1 = Mon … 7 = Sun
        $start_date = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days', $ts));
        $end_date   = date('Y-m-d', strtotime('+' . (7  - $dayOfWeek) . ' days', $ts));
        break;

    case 'monthly':
        if ($month < 1 || $month > 12) {
            respondBadRequest("month must be between 1 and 12.");
        }
        $start_date = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $end_date   = date('Y-m-t',  mktime(0, 0, 0, $month, 1, $year));
        break;

    case 'yearly':
        $start_date = "{$year}-01-01";
        $end_date   = "{$year}-12-31";
        break;
}

// ── Revenue: sales ────────────────────────────────────────────────
if ($flock_id > 0) {
    $salesStmt = $connect->prepare(
        "SELECT sale_type,
                SUM(total_amount) AS total_amount,
                SUM(quantity)     AS total_qty
         FROM   sale
         WHERE  sale_date BETWEEN ? AND ?
           AND  flock_id = ?
         GROUP BY sale_type"
    );
    $salesStmt->bind_param("ssi", $start_date, $end_date, $flock_id);
} else {
    $salesStmt = $connect->prepare(
        "SELECT sale_type,
                SUM(total_amount) AS total_amount,
                SUM(quantity)     AS total_qty
         FROM   sale
         WHERE  sale_date BETWEEN ? AND ?
         GROUP BY sale_type"
    );
    $salesStmt->bind_param("ss", $start_date, $end_date);
}
$salesStmt->execute();
$salesResult = $salesStmt->get_result();
$salesStmt->close();

$revenue_breakdown  = [];
$total_revenue      = 0.0;
$live_birds_sold    = 0;
$live_birds_revenue = 0.0;

while ($row = $salesResult->fetch_assoc()) {
    $revenue_breakdown[$row['sale_type']] = [
        'total_amount' => round((float) $row['total_amount'], 2),
        'quantity'     => (int) $row['total_qty'],
    ];
    $total_revenue += (float) $row['total_amount'];

    if ($row['sale_type'] === 'live_birds') {
        $live_birds_sold    = (int) $row['total_qty'];
        $live_birds_revenue = (float) $row['total_amount'];
    }
}

// Average price per live bird (used to estimate mortality monetary loss)
$avg_bird_price = ($live_birds_sold > 0)
    ? round($live_birds_revenue / $live_birds_sold, 2)
    : 0.0;

// ── Expenses: by category ─────────────────────────────────────────
$expStmt = $connect->prepare(
    "SELECT category, SUM(amount) AS total
     FROM   expense
     WHERE  expense_date BETWEEN ? AND ?
     GROUP BY category"
);
$expStmt->bind_param("ss", $start_date, $end_date);
$expStmt->execute();
$expResult = $expStmt->get_result();
$expStmt->close();

$expense_breakdown = [];
$total_expenses    = 0.0;
while ($row = $expResult->fetch_assoc()) {
    $expense_breakdown[$row['category']] = round((float) $row['total'], 2);
    $total_expenses += (float) $row['total'];
}

// Named expense lines the user cares about
$vaccine_cost = $expense_breakdown['medication'] ?? 0.0;   // vaccine / medication
$feeding_cost = $expense_breakdown['feed']       ?? 0.0;   // feed / feeding

// ── Mortality: birds lost + estimated monetary impact ─────────────
if ($flock_id > 0) {
    $mortStmt = $connect->prepare(
        "SELECT cause, SUM(count) AS total_lost
         FROM   mortality
         WHERE  mortality_date BETWEEN ? AND ?
           AND  flock_id = ?
         GROUP BY cause"
    );
    $mortStmt->bind_param("ssi", $start_date, $end_date, $flock_id);
} else {
    $mortStmt = $connect->prepare(
        "SELECT cause, SUM(count) AS total_lost
         FROM   mortality
         WHERE  mortality_date BETWEEN ? AND ?
         GROUP BY cause"
    );
    $mortStmt->bind_param("ss", $start_date, $end_date);
}
$mortStmt->execute();
$mortResult = $mortStmt->get_result();
$mortStmt->close();

$mortality_by_cause = [];
$total_birds_lost   = 0;
while ($row = $mortResult->fetch_assoc()) {
    $mortality_by_cause[$row['cause']] = (int) $row['total_lost'];
    $total_birds_lost += (int) $row['total_lost'];
}

// Estimated loss from mortality = birds lost × average live-bird sale price
$mortality_estimated_loss = round($total_birds_lost * $avg_bird_price, 2);

// ── Profit / Loss summary ─────────────────────────────────────────
// Net = Total Revenue − Total Expenses
// Mortality estimated loss is shown separately as an informational figure
$net_amount = round($total_revenue - $total_expenses, 2);

respondOK("Profit/loss report generated successfully.", [
    "period"     => $period,
    "start_date" => $start_date,
    "end_date"   => $end_date,
    "flock_id"   => $flock_id > 0 ? $flock_id : null,

    "revenue" => [
        "total"     => round($total_revenue, 2),
        "breakdown" => $revenue_breakdown,
    ],

    "expenses" => [
        "total"      => round($total_expenses, 2),
        "vaccine"    => $vaccine_cost,
        "feeding"    => $feeding_cost,
        "equipment"  => $expense_breakdown['equipment']  ?? 0.0,
        "labor"      => $expense_breakdown['labor']      ?? 0.0,
        "utilities"  => $expense_breakdown['utilities']  ?? 0.0,
        "transport"  => $expense_breakdown['transport']  ?? 0.0,
        "other"      => $expense_breakdown['other']      ?? 0.0,
    ],

    "mortality" => [
        "total_birds_lost"        => $total_birds_lost,
        "by_cause"                => $mortality_by_cause,
        "avg_bird_price_used"     => $avg_bird_price,
        "estimated_monetary_loss" => $mortality_estimated_loss,
        "note"                    => "Estimated loss = birds lost × average live-bird sale price in the period.",
    ],

    "summary" => [
        "total_revenue"            => round($total_revenue, 2),
        "total_expenses"           => round($total_expenses, 2),
        "net_amount"               => $net_amount,
        "mortality_estimated_loss" => $mortality_estimated_loss,
        "is_profit"                => $net_amount >= 0,
        "status"                   => $net_amount >= 0 ? "profit" : "loss",
    ],
]);
