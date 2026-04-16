<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$token       = ValidateAPITokenSentIN('user');
$recorded_by = (int) $token->usertoken;

$flock_id = isset($_GET['flock_id']) ? (int) trim($_GET['flock_id']) : 0;

if ($flock_id > 0) {
    $stmt = $connect->prepare("SELECT ep.id, ep.flock_id, fl.batch_number, ep.eggs_collected, ep.broken_eggs, ep.collection_date, ep.recorded_by, w.name AS recorded_by_name, ep.notes, ep.created_at FROM egg_production ep JOIN flock fl ON fl.id = ep.flock_id JOIN worker w ON w.id = ep.recorded_by WHERE ep.recorded_by = ? AND ep.flock_id = ? ORDER BY ep.collection_date DESC");
    $stmt->bind_param("ii", $recorded_by, $flock_id);
} else {
    $stmt = $connect->prepare("SELECT ep.id, ep.flock_id, fl.batch_number, ep.eggs_collected, ep.broken_eggs, ep.collection_date, ep.recorded_by, w.name AS recorded_by_name, ep.notes, ep.created_at FROM egg_production ep JOIN flock fl ON fl.id = ep.flock_id JOIN worker w ON w.id = ep.recorded_by WHERE ep.recorded_by = ? ORDER BY ep.collection_date DESC");
    $stmt->bind_param("i", $recorded_by);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

respondOK("Egg production records retrieved successfully.", $records);
