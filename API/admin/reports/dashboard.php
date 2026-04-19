<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

// Farms
$farmStmt = $connect->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active FROM farm");
$farmRow  = $farmStmt->fetch_assoc();

// Flocks
$flockStmt = $connect->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active, SUM(current_count) AS total_birds FROM flock");
$flockRow  = $flockStmt->fetch_assoc();

// Workers
$workerStmt = $connect->query("SELECT COUNT(*) AS total FROM worker");
$workerRow  = $workerStmt->fetch_assoc();

// Revenue (all time)
$revenueStmt = $connect->query("SELECT SUM(total_amount) AS total FROM sale");
$revenueRow  = $revenueStmt->fetch_assoc();

// Expenses (all time)
$expenseStmt = $connect->query("SELECT SUM(amount) AS total FROM expense");
$expenseRow  = $expenseStmt->fetch_assoc();

// Mortality (all time)
$mortStmt = $connect->query("SELECT SUM(count) AS total FROM mortality");
$mortRow  = $mortStmt->fetch_assoc();

// This month stats
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$monthSalesStmt = $connect->prepare("SELECT SUM(total_amount) AS total FROM sale WHERE sale_date BETWEEN ? AND ?");
$monthSalesStmt->bind_param("ss", $monthStart, $monthEnd);
$monthSalesStmt->execute();
$monthSalesRow = $monthSalesStmt->get_result()->fetch_assoc();
$monthSalesStmt->close();

$monthExpStmt = $connect->prepare("SELECT SUM(amount) AS total FROM expense WHERE expense_date BETWEEN ? AND ?");
$monthExpStmt->bind_param("ss", $monthStart, $monthEnd);
$monthExpStmt->execute();
$monthExpRow = $monthExpStmt->get_result()->fetch_assoc();
$monthExpStmt->close();

$monthMortStmt = $connect->prepare("SELECT SUM(count) AS total FROM mortality WHERE mortality_date BETWEEN ? AND ?");
$monthMortStmt->bind_param("ss", $monthStart, $monthEnd);
$monthMortStmt->execute();
$monthMortRow = $monthMortStmt->get_result()->fetch_assoc();
$monthMortStmt->close();

// Pending bookings
$bookingStmt = $connect->query("SELECT COUNT(*) AS total FROM booking WHERE status = 'pending'");
$bookingRow  = $bookingStmt->fetch_assoc();

$totalRevenue  = (float) ($revenueRow['total']  ?? 0);
$totalExpenses = (float) ($expenseRow['total']  ?? 0);

respondOK("Dashboard summary retrieved successfully.", [
    "farms" => [
        "total"  => (int) $farmRow['total'],
        "active" => (int) $farmRow['active'],
    ],
    "flocks" => [
        "total"        => (int) $flockRow['total'],
        "active"       => (int) $flockRow['active'],
        "total_birds"  => (int) ($flockRow['total_birds'] ?? 0),
    ],
    "workers" => [
        "total" => (int) $workerRow['total'],
    ],
    "bookings" => [
        "pending" => (int) $bookingRow['total'],
    ],
    "all_time" => [
        "total_revenue"  => round($totalRevenue, 2),
        "total_expenses" => round($totalExpenses, 2),
        "net_profit"     => round($totalRevenue - $totalExpenses, 2),
        "total_mortality"=> (int) ($mortRow['total'] ?? 0),
    ],
    "this_month" => [
        "revenue"   => round((float) ($monthSalesRow['total'] ?? 0), 2),
        "expenses"  => round((float) ($monthExpRow['total']   ?? 0), 2),
        "mortality" => (int) ($monthMortRow['total'] ?? 0),
        "period"    => $monthStart . " to " . $monthEnd,
    ],
]);
