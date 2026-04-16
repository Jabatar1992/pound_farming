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
    $stmt = $connect->prepare("SELECT m.id, m.flock_id, fl.batch_number, m.count, m.cause, m.mortality_date, m.recorded_by, w.name AS recorded_by_name, m.notes, m.created_at FROM mortality m JOIN flock fl ON fl.id = m.flock_id JOIN worker w ON w.id = m.recorded_by WHERE m.flock_id = ? ORDER BY m.mortality_date DESC");
    $stmt->bind_param("i", $flock_id);
} else {
    $stmt = $connect->prepare("SELECT m.id, m.flock_id, fl.batch_number, m.count, m.cause, m.mortality_date, m.recorded_by, w.name AS recorded_by_name, m.notes, m.created_at FROM mortality m JOIN flock fl ON fl.id = m.flock_id JOIN worker w ON w.id = m.recorded_by ORDER BY m.mortality_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

respondOK("Mortality records retrieved successfully.", $records);
