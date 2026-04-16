<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Flock ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid flock ID.");
}

$stmt = $connect->prepare("SELECT f.id, f.farm_id, fm.name AS farm_name, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, f.notes, f.created_at FROM flock f JOIN farm fm ON fm.id = f.farm_id WHERE f.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Flock retrieved successfully.", $result->fetch_assoc());
