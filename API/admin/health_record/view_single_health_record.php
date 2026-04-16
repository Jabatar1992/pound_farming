<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Health record ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid health record ID.");
}

$stmt = $connect->prepare("SELECT hr.id, hr.flock_id, fl.batch_number, hr.record_type, hr.description, hr.medication, hr.dosage, hr.administered_by, hr.record_date, hr.next_due_date, hr.created_at FROM health_record hr JOIN flock fl ON fl.id = hr.flock_id WHERE hr.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Health record retrieved successfully.", $result->fetch_assoc());
