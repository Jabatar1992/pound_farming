<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['record_type'], $_POST['description'], $_POST['record_date'])) {
    respondBadRequest("Invalid request. flock_id, record_type, description and record_date are required.");
}

$flock_id        = (int) trim($_POST['flock_id']);
$record_type     = strip_tags(trim($_POST['record_type']));
$description     = strip_tags(trim($_POST['description']));
$record_date     = strip_tags(trim($_POST['record_date']));
$medication      = isset($_POST['medication'])      ? strip_tags(trim($_POST['medication']))      : null;
$dosage          = isset($_POST['dosage'])          ? strip_tags(trim($_POST['dosage']))          : null;
$administered_by = isset($_POST['administered_by']) ? strip_tags(trim($_POST['administered_by'])) : null;
$next_due_date   = isset($_POST['next_due_date'])   ? strip_tags(trim($_POST['next_due_date']))   : null;

$validTypes = ['vaccination', 'treatment', 'checkup', 'deworming', 'other'];

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif (!in_array($record_type, $validTypes)) {
    respondBadRequest("record_type must be one of: " . implode(', ', $validTypes) . ".");
} elseif (input_is_invalid($description)) {
    respondBadRequest("Description is required.");
} elseif (input_is_invalid($record_date)) {
    respondBadRequest("Record date is required (YYYY-MM-DD).");
} else {

    $flockCheck = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
    $flockCheck->bind_param("i", $flock_id);
    $flockCheck->execute();
    if ($flockCheck->get_result()->num_rows === 0) {
        $flockCheck->close();
        respondBadRequest("Flock not found.");
    }
    $flockCheck->close();

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO health_record (flock_id, record_type, description, medication, dosage, administered_by, record_date, next_due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $flock_id, $record_type, $description, $medication, $dosage, $administered_by, $record_date, $next_due_date);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT hr.id, hr.flock_id, fl.batch_number, hr.record_type, hr.description, hr.medication, hr.dosage, hr.administered_by, hr.record_date, hr.next_due_date, hr.created_at FROM health_record hr JOIN flock fl ON fl.id = hr.flock_id WHERE hr.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Health record added successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to add health record.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
