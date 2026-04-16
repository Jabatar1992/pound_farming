<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$stmt = $connect->prepare("SELECT ea.id, ea.flock_id, fl.batch_number, fl.bird_type, fm.name AS farm_name, ea.available_crates, ea.price_per_crate, ea.description, ea.is_available, ea.created_at FROM egg_availability ea JOIN flock fl ON fl.id = ea.flock_id JOIN farm fm ON fm.id = fl.farm_id ORDER BY ea.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}

respondOK("Egg availability listings retrieved successfully.", $listings);
