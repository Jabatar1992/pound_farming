<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

$stmt = $connect->prepare("SELECT eb.id, eb.availability_id, fl.bird_type, fm.name AS farm_name, eb.quantity_crates, eb.unit_price, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.payment_reference, eb.notes, eb.created_at FROM egg_booking eb JOIN egg_availability ea ON ea.id = eb.availability_id JOIN flock fl ON fl.id = ea.flock_id JOIN farm fm ON fm.id = fl.farm_id WHERE eb.buyer_id = ? ORDER BY eb.created_at DESC");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

respondOK("Your bookings retrieved successfully.", $bookings);
