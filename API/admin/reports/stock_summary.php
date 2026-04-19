<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

// Per-farm bird counts
$farmStmt = $connect->query(
    "SELECT fm.id AS farm_id, fm.name AS farm_name, fm.location,
            COUNT(f.id) AS total_flocks,
            SUM(CASE WHEN f.status='active' THEN 1 ELSE 0 END) AS active_flocks,
            SUM(CASE WHEN f.status='active' THEN f.current_count ELSE 0 END) AS active_birds,
            SUM(f.current_count) AS total_birds
     FROM farm fm
     LEFT JOIN flock f ON f.farm_id = fm.id
     GROUP BY fm.id, fm.name, fm.location
     ORDER BY fm.name"
);

$farms = [];
while ($row = $farmStmt->fetch_assoc()) {
    $farms[] = [
        "farm_id"       => (int) $row['farm_id'],
        "farm_name"     => $row['farm_name'],
        "location"      => $row['location'],
        "total_flocks"  => (int) $row['total_flocks'],
        "active_flocks" => (int) $row['active_flocks'],
        "active_birds"  => (int) ($row['active_birds'] ?? 0),
        "total_birds"   => (int) ($row['total_birds']  ?? 0),
    ];
}

// Per-bird-type summary
$typeStmt = $connect->query(
    "SELECT bird_type,
            COUNT(*) AS total_flocks,
            SUM(current_count) AS current_birds,
            SUM(initial_count) AS initial_birds
     FROM flock
     WHERE status = 'active'
     GROUP BY bird_type
     ORDER BY bird_type"
);

$byType = [];
while ($row = $typeStmt->fetch_assoc()) {
    $byType[] = [
        "bird_type"      => $row['bird_type'],
        "total_flocks"   => (int) $row['total_flocks'],
        "current_birds"  => (int) ($row['current_birds'] ?? 0),
        "initial_birds"  => (int) ($row['initial_birds'] ?? 0),
    ];
}

// Totals
$totalsStmt = $connect->query(
    "SELECT SUM(current_count) AS total_current, SUM(initial_count) AS total_initial
     FROM flock WHERE status = 'active'"
);
$totalsRow = $totalsStmt->fetch_assoc();

respondOK("Stock summary retrieved successfully.", [
    "totals" => [
        "active_birds"  => (int) ($totalsRow['total_current'] ?? 0),
        "initial_birds" => (int) ($totalsRow['total_initial'] ?? 0),
    ],
    "by_farm"      => $farms,
    "by_bird_type" => $byType,
]);
