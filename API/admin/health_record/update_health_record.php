<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['record_type'], $_POST['description'], $_POST['record_date'])) {
    respondBadRequest("Invalid request. id, record_type, description and record_date are required.");
}

$id              = (int) trim($_POST['id']);
$record_type     = strip_tags(trim($_POST['record_type']));
$description     = strip_tags(trim($_POST['description']));
$record_date     = strip_tags(trim($_POST['record_date']));
$medication      = isset($_POST['medication'])      ? strip_tags(trim($_POST['medication']))      : null;
$dosage          = isset($_POST['dosage'])          ? strip_tags(trim($_POST['dosage']))          : null;
$administered_by = isset($_POST['administered_by']) ? strip_tags(trim($_POST['administered_by'])) : null;
$next_due_date   = isset($_POST['next_due_date'])   ? strip_tags(trim($_POST['next_due_date']))   : null;

$validTypes = ['vaccination', 'treatment', 'checkup', 'deworming', 'other'];

if ($id <= 0) {
    respondBadRequest("Invalid health record ID.");
} elseif (!in_array($record_type, $validTypes)) {
    respondBadRequest("record_type must be one of: " . implode(', ', $validTypes) . ".");
} elseif (input_is_invalid($description)) {
    respondBadRequest("Description is required.");
} elseif (input_is_invalid($record_date)) {
    respondBadRequest("Record date is required (YYYY-MM-DD).");
}

$exists = $connect->prepare("SELECT id FROM health_record WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
if ($exists->get_result()->num_rows === 0) {
    $exists->close();
    respondNotFound([]);
}
$exists->close();

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE health_record SET record_type = ?, description = ?, medication = ?, dosage = ?, administered_by = ?, record_date = ?, next_due_date = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $record_type, $description, $medication, $dosage, $administered_by, $record_date, $next_due_date, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT hr.id, hr.flock_id, fl.batch_number, hr.record_type, hr.description, hr.medication, hr.dosage, hr.administered_by, hr.record_date, hr.next_due_date, hr.created_at FROM health_record hr JOIN flock fl ON fl.id = hr.flock_id WHERE hr.id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Health record updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update health record.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
