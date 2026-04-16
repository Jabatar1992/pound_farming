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
    $stmt = $connect->prepare("SELECT hr.id, hr.flock_id, fl.batch_number, hr.record_type, hr.description, hr.medication, hr.dosage, hr.administered_by, hr.record_date, hr.next_due_date, hr.created_at FROM health_record hr JOIN flock fl ON fl.id = hr.flock_id WHERE hr.flock_id = ? ORDER BY hr.record_date DESC");
    $stmt->bind_param("i", $flock_id);
} else {
    $stmt = $connect->prepare("SELECT hr.id, hr.flock_id, fl.batch_number, hr.record_type, hr.description, hr.medication, hr.dosage, hr.administered_by, hr.record_date, hr.next_due_date, hr.created_at FROM health_record hr JOIN flock fl ON fl.id = hr.flock_id ORDER BY hr.record_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

respondOK("Health records retrieved successfully.", $records);
