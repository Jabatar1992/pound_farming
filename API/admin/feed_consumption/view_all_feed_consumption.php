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
    $stmt = $connect->prepare("SELECT fc.id, fc.flock_id, fl.batch_number, fc.feed_id, fd.name AS feed_name, fc.quantity_kg, fc.consumption_date, fc.recorded_by, w.name AS recorded_by_name, fc.notes, fc.created_at FROM feed_consumption fc JOIN flock fl ON fl.id = fc.flock_id JOIN feed fd ON fd.id = fc.feed_id JOIN worker w ON w.id = fc.recorded_by WHERE fc.flock_id = ? ORDER BY fc.consumption_date DESC");
    $stmt->bind_param("i", $flock_id);
} else {
    $stmt = $connect->prepare("SELECT fc.id, fc.flock_id, fl.batch_number, fc.feed_id, fd.name AS feed_name, fc.quantity_kg, fc.consumption_date, fc.recorded_by, w.name AS recorded_by_name, fc.notes, fc.created_at FROM feed_consumption fc JOIN flock fl ON fl.id = fc.flock_id JOIN feed fd ON fd.id = fc.feed_id JOIN worker w ON w.id = fc.recorded_by ORDER BY fc.consumption_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

respondOK("Feed consumption records retrieved successfully.", $records);
