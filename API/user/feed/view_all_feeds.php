<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('user');

$stmt = $connect->prepare("SELECT id, name, feed_type, quantity_kg, unit_price, supplier FROM feed ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$feeds = [];
while ($row = $result->fetch_assoc()) {
    $feeds[] = $row;
}

respondOK("Feeds retrieved successfully.", $feeds);
