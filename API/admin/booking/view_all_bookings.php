<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$status = isset($_GET['status']) ? strip_tags(trim($_GET['status'])) : '';

if (!empty($status)) {
    $stmt = $connect->prepare("SELECT eb.id, eb.buyer_id, b.name AS buyer_name, b.phone AS buyer_phone, eb.availability_id, ea.price_per_crate, fl.batch_number, eb.quantity_crates, eb.unit_price, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.payment_reference, eb.notes, eb.created_at FROM egg_booking eb JOIN buyer b ON b.id = eb.buyer_id JOIN egg_availability ea ON ea.id = eb.availability_id JOIN flock fl ON fl.id = ea.flock_id WHERE eb.order_status = ? ORDER BY eb.created_at DESC");
    $stmt->bind_param("s", $status);
} else {
    $stmt = $connect->prepare("SELECT eb.id, eb.buyer_id, b.name AS buyer_name, b.phone AS buyer_phone, eb.availability_id, ea.price_per_crate, fl.batch_number, eb.quantity_crates, eb.unit_price, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.payment_reference, eb.notes, eb.created_at FROM egg_booking eb JOIN buyer b ON b.id = eb.buyer_id JOIN egg_availability ea ON ea.id = eb.availability_id JOIN flock fl ON fl.id = ea.flock_id ORDER BY eb.created_at DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

respondOK("Bookings retrieved successfully.", $bookings);
