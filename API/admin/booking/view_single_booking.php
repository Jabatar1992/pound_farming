<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Booking ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid booking ID.");
}

$stmt = $connect->prepare("SELECT eb.id, eb.buyer_id, b.name AS buyer_name, b.phone AS buyer_phone, b.email AS buyer_email, b.address AS buyer_address, eb.availability_id, fl.batch_number, fl.bird_type, fm.name AS farm_name, eb.quantity_crates, eb.unit_price, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.payment_reference, eb.notes, eb.created_at FROM egg_booking eb JOIN buyer b ON b.id = eb.buyer_id JOIN egg_availability ea ON ea.id = eb.availability_id JOIN flock fl ON fl.id = ea.flock_id JOIN farm fm ON fm.id = fl.farm_id WHERE eb.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

$booking = $result->fetch_assoc();

$trackStmt = $connect->prepare("SELECT ot.id, ot.status, ot.note, ot.updated_by, a.name AS updated_by_name, ot.created_at FROM order_tracking ot JOIN admin a ON a.id = ot.updated_by WHERE ot.booking_id = ? ORDER BY ot.created_at ASC");
$trackStmt->bind_param("i", $id);
$trackStmt->execute();
$trackResult = $trackStmt->get_result();
$trackStmt->close();

$timeline = [];
while ($row = $trackResult->fetch_assoc()) {
    $timeline[] = $row;
}

$booking['tracking_timeline'] = $timeline;

respondOK("Booking retrieved successfully.", $booking);
