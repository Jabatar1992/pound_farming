<?php
// Public endpoint — no authentication required
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$stmt = $connect->prepare("SELECT ea.id, fl.bird_type, fm.name AS farm_name, ea.available_crates, ea.price_per_crate, ea.description, ea.created_at FROM egg_availability ea JOIN flock fl ON fl.id = ea.flock_id JOIN farm fm ON fm.id = fl.farm_id WHERE ea.is_available = 1 AND ea.available_crates > 0 ORDER BY ea.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $row['eggs_per_crate'] = 30;
    $row['total_eggs']     = (int)$row['available_crates'] * 30;
    $listings[] = $row;
}

respondOK("Available egg listings retrieved.", $listings);
