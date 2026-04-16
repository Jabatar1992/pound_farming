<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$stmt = $connect->prepare("SELECT f.id, f.farm_id, fm.name AS farm_name, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, f.notes, f.created_at FROM flock f JOIN farm fm ON fm.id = f.farm_id ORDER BY f.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$flocks = [];
while ($row = $result->fetch_assoc()) {
    $flocks[] = $row;
}

respondOK("Flocks retrieved successfully.", $flocks);
