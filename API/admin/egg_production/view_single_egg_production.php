<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Record ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid record ID.");
}

$stmt = $connect->prepare("SELECT ep.id, ep.flock_id, fl.batch_number, ep.eggs_collected, ep.broken_eggs, ep.collection_date, ep.recorded_by, w.name AS recorded_by_name, ep.notes, ep.created_at FROM egg_production ep JOIN flock fl ON fl.id = ep.flock_id JOIN worker w ON w.id = ep.recorded_by WHERE ep.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Egg production record retrieved successfully.", $result->fetch_assoc());
