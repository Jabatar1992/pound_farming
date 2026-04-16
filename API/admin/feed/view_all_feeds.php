<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$stmt = $connect->prepare("SELECT id, name, feed_type, quantity_kg, unit_price, supplier, purchase_date, created_at FROM feed ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$feeds = [];
while ($row = $result->fetch_assoc()) {
    $feeds[] = $row;
}

respondOK("Feeds retrieved successfully.", $feeds);
