<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['farm_id'], $_POST['bird_type'], $_POST['current_count'], $_POST['age_weeks'], $_POST['status'])) {
    respondBadRequest("Invalid request. id, farm_id, bird_type, current_count, age_weeks and status are required.");
}

$id            = (int) trim($_POST['id']);
$farm_id       = (int) trim($_POST['farm_id']);
$bird_type     = strip_tags(trim($_POST['bird_type']));
$current_count = (int) trim($_POST['current_count']);
$age_weeks     = (int) trim($_POST['age_weeks']);
$status        = strip_tags(trim($_POST['status']));
$notes         = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

$validBirdTypes = ['broiler', 'layer', 'turkey', 'duck', 'cockerel', 'other'];
$validStatuses  = ['active', 'sold', 'closed'];

if ($id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif ($farm_id <= 0) {
    respondBadRequest("Invalid farm ID.");
} elseif (!in_array($bird_type, $validBirdTypes)) {
    respondBadRequest("bird_type must be one of: " . implode(', ', $validBirdTypes) . ".");
} elseif ($current_count < 0) {
    respondBadRequest("Current count cannot be negative.");
} elseif (!in_array($status, $validStatuses)) {
    respondBadRequest("status must be one of: " . implode(', ', $validStatuses) . ".");
} else {

    $exists = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
    $exists->bind_param("i", $id);
    $exists->execute();
    $found = $exists->get_result()->num_rows > 0;
    $exists->close();

    if (!$found) {
        respondNotFound([]);
    }

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("UPDATE flock SET farm_id = ?, bird_type = ?, current_count = ?, age_weeks = ?, status = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("issiisi", $farm_id, $bird_type, $current_count, $age_weeks, $status, $notes, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT f.id, f.farm_id, fm.name AS farm_name, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, f.notes, f.created_at FROM flock f JOIN farm fm ON fm.id = f.farm_id WHERE f.id = ?");
            $get->bind_param("i", $id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Flock updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update flock.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
