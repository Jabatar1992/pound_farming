<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Feed consumption ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid feed consumption ID.");
}

$stmt = $connect->prepare("SELECT fc.id, fc.flock_id, fl.batch_number, fc.feed_id, fd.name AS feed_name, fc.quantity_kg, fc.consumption_date, fc.recorded_by, w.name AS recorded_by_name, fc.notes, fc.created_at FROM feed_consumption fc JOIN flock fl ON fl.id = fc.flock_id JOIN feed fd ON fd.id = fc.feed_id JOIN worker w ON w.id = fc.recorded_by WHERE fc.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    respondNotFound([]);
}

$data = $result->fetch_assoc();
$stmt->close();

respondOK("Feed consumption record retrieved successfully.", $data);
