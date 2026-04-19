<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['farm_id'], $_POST['batch_number'], $_POST['bird_type'], $_POST['initial_count'], $_POST['date_stocked'])) {
    respondBadRequest("Invalid request. farm_id, batch_number, bird_type, initial_count and date_stocked are required.");
}

$farm_id       = (int) trim($_POST['farm_id']);
$batch_number  = strip_tags(trim($_POST['batch_number']));
$bird_type     = strip_tags(trim($_POST['bird_type']));
$initial_count = (int) trim($_POST['initial_count']);
$date_stocked  = strip_tags(trim($_POST['date_stocked']));
$age_weeks     = isset($_POST['age_weeks']) ? (int) trim($_POST['age_weeks']) : 0;
$notes         = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

$validBirdTypes = ['broiler', 'layer', 'turkey', 'duck', 'cockerel', 'other'];

if ($farm_id <= 0) {
    respondBadRequest("Invalid farm ID.");
} elseif (input_is_invalid($batch_number)) {
    respondBadRequest("Batch number is required.");
} elseif (!in_array($bird_type, $validBirdTypes)) {
    respondBadRequest("bird_type must be one of: " . implode(', ', $validBirdTypes) . ".");
} elseif ($initial_count <= 0) {
    respondBadRequest("Initial count must be a positive number.");
} elseif (input_is_invalid($date_stocked)) {
    respondBadRequest("Date stocked is required (YYYY-MM-DD).");
} else {

    $farmCheck = $connect->prepare("SELECT id FROM farm WHERE id = ? LIMIT 1");
    $farmCheck->bind_param("i", $farm_id);
    $farmCheck->execute();
    $farmExists = $farmCheck->get_result()->num_rows > 0;
    $farmCheck->close();

    if (!$farmExists) {
        respondBadRequest("Farm not found.");
    }

    $batchCheck = $connect->prepare("SELECT id FROM flock WHERE batch_number = ? LIMIT 1");
    $batchCheck->bind_param("s", $batch_number);
    $batchCheck->execute();
    $batchExists = $batchCheck->get_result()->num_rows > 0;
    $batchCheck->close();

    if ($batchExists) {
        respondBadRequest("Batch number already exists.");
    }

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO flock (farm_id, batch_number, bird_type, initial_count, current_count, date_stocked, age_weeks, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiisis", $farm_id, $batch_number, $bird_type, $initial_count, $initial_count, $date_stocked, $age_weeks, $notes);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT f.id, f.farm_id, fm.name AS farm_name, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, f.notes, f.created_at FROM flock f JOIN farm fm ON fm.id = f.farm_id WHERE f.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Flock added successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to add flock.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
