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
    $stmt = $connect->prepare("SELECT s.id, s.flock_id, fl.batch_number, s.sale_type, s.quantity, s.unit_price, s.total_amount, s.buyer_name, s.buyer_phone, s.sale_date, s.recorded_by, w.name AS recorded_by_name, s.created_at FROM sale s JOIN flock fl ON fl.id = s.flock_id JOIN worker w ON w.id = s.recorded_by WHERE s.flock_id = ? ORDER BY s.sale_date DESC");
    $stmt->bind_param("i", $flock_id);
} else {
    $stmt = $connect->prepare("SELECT s.id, s.flock_id, fl.batch_number, s.sale_type, s.quantity, s.unit_price, s.total_amount, s.buyer_name, s.buyer_phone, s.sale_date, s.recorded_by, w.name AS recorded_by_name, s.created_at FROM sale s JOIN flock fl ON fl.id = s.flock_id JOIN worker w ON w.id = s.recorded_by ORDER BY s.sale_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

respondOK("Sales retrieved successfully.", $sales);
