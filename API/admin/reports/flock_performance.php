<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$flock_id = isset($_GET['flock_id']) ? (int) trim($_GET['flock_id']) : 0;

if ($flock_id > 0) {
    $flockStmt = $connect->prepare("SELECT f.id, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, fm.name AS farm_name FROM flock f JOIN farm fm ON fm.id = f.farm_id WHERE f.id = ? LIMIT 1");
    $flockStmt->bind_param("i", $flock_id);
    $flockStmt->execute();
    $flockResult = $flockStmt->get_result();
    if ($flockResult->num_rows === 0) {
        $flockStmt->close();
        respondNotFound([]);
    }
    $flocks = [$flockResult->fetch_assoc()];
    $flockStmt->close();
} else {
    $flockStmt = $connect->query("SELECT f.id, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, fm.name AS farm_name FROM flock f JOIN farm fm ON fm.id = f.farm_id ORDER BY f.created_at DESC");
    $flocks = [];
    while ($row = $flockStmt->fetch_assoc()) {
        $flocks[] = $row;
    }
}

$performance = [];

foreach ($flocks as $flock) {
    $fid = (int) $flock['id'];

    // Total mortality
    $mortStmt = $connect->prepare("SELECT SUM(count) AS total FROM mortality WHERE flock_id = ?");
    $mortStmt->bind_param("i", $fid);
    $mortStmt->execute();
    $totalMortality = (int) ($mortStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $mortStmt->close();

    // Total feed consumed (kg)
    $feedStmt = $connect->prepare("SELECT SUM(quantity_kg) AS total FROM feed_consumption WHERE flock_id = ?");
    $feedStmt->bind_param("i", $fid);
    $feedStmt->execute();
    $totalFeedKg = (float) ($feedStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $feedStmt->close();

    // Total eggs collected
    $eggStmt = $connect->prepare("SELECT SUM(eggs_collected) AS total, SUM(broken_eggs) AS broken FROM egg_production WHERE flock_id = ?");
    $eggStmt->bind_param("i", $fid);
    $eggStmt->execute();
    $eggRow        = $eggStmt->get_result()->fetch_assoc();
    $totalEggs     = (int) ($eggRow['total']  ?? 0);
    $totalBroken   = (int) ($eggRow['broken'] ?? 0);
    $eggStmt->close();

    // Total revenue from sales
    $salesStmt = $connect->prepare("SELECT SUM(total_amount) AS revenue, SUM(quantity) AS qty_sold FROM sale WHERE flock_id = ?");
    $salesStmt->bind_param("i", $fid);
    $salesStmt->execute();
    $salesRow  = $salesStmt->get_result()->fetch_assoc();
    $revenue   = (float) ($salesRow['revenue']  ?? 0);
    $qtySold   = (int)   ($salesRow['qty_sold'] ?? 0);
    $salesStmt->close();

    $initialCount  = (int) $flock['initial_count'];
    $mortalityRate = $initialCount > 0 ? round(($totalMortality / $initialCount) * 100, 2) : 0;
    // FCR = total feed kg / birds sold (lower is better; only meaningful for meat birds)
    $fcr           = $qtySold > 0 ? round($totalFeedKg / $qtySold, 2) : null;

    $performance[] = [
        "flock_id"        => $fid,
        "batch_number"    => $flock['batch_number'],
        "farm_name"       => $flock['farm_name'],
        "bird_type"       => $flock['bird_type'],
        "status"          => $flock['status'],
        "age_weeks"       => (int) $flock['age_weeks'],
        "initial_count"   => $initialCount,
        "current_count"   => (int) $flock['current_count'],
        "total_mortality" => $totalMortality,
        "mortality_rate"  => $mortalityRate,
        "total_feed_kg"   => round($totalFeedKg, 2),
        "fcr"             => $fcr,
        "total_eggs"      => $totalEggs,
        "broken_eggs"     => $totalBroken,
        "birds_sold"      => $qtySold,
        "total_revenue"   => round($revenue, 2),
    ];
}

respondOK("Flock performance report generated successfully.", $performance);
