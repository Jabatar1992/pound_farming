<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Sale ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid sale ID.");
}

$stmt = $connect->prepare("SELECT s.id, s.flock_id, fl.batch_number, s.sale_type, s.quantity, s.unit_price, s.total_amount, s.buyer_name, s.buyer_phone, s.sale_date, s.recorded_by, w.name AS recorded_by_name, s.created_at FROM sale s JOIN flock fl ON fl.id = s.flock_id JOIN worker w ON w.id = s.recorded_by WHERE s.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Sale retrieved successfully.", $result->fetch_assoc());
