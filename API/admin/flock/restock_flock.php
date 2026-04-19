<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['count'], $_POST['restock_date'])) {
    respondBadRequest("Invalid request. flock_id, count and restock_date are required.");
}

$flock_id     = (int) trim($_POST['flock_id']);
$count        = (int) trim($_POST['count']);
$restock_date = strip_tags(trim($_POST['restock_date']));
$notes        = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif ($count <= 0) {
    respondBadRequest("Count must be at least 1.");
} elseif (input_is_invalid($restock_date)) {
    respondBadRequest("Restock date is required (YYYY-MM-DD).");
}

$flockCheck = $connect->prepare("SELECT id, current_count, initial_count, status FROM flock WHERE id = ? LIMIT 1");
$flockCheck->bind_param("i", $flock_id);
$flockCheck->execute();
$flockResult = $flockCheck->get_result();
if ($flockResult->num_rows === 0) {
    $flockCheck->close();
    respondBadRequest("Flock not found.");
}
$flock = $flockResult->fetch_assoc();
$flockCheck->close();

if ($flock['status'] === 'closed') {
    respondBadRequest("Cannot restock a closed flock.");
}

$newCurrentCount = $flock['current_count'] + $count;
$newInitialCount = $flock['initial_count'] + $count;

$connect->begin_transaction();
try {

    $updateFlock = $connect->prepare("UPDATE flock SET current_count = ?, initial_count = ?, status = 'active' WHERE id = ?");
    $updateFlock->bind_param("iii", $newCurrentCount, $newInitialCount, $flock_id);
    $updateFlock->execute();

    if ($updateFlock->affected_rows > 0) {
        $connect->commit();
        $updateFlock->close();

        $get = $connect->prepare("SELECT f.id, f.farm_id, fm.name AS farm_name, f.batch_number, f.bird_type, f.initial_count, f.current_count, f.date_stocked, f.age_weeks, f.status, f.notes, f.created_at FROM flock f JOIN farm fm ON fm.id = f.farm_id WHERE f.id = ?");
        $get->bind_param("i", $flock_id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Flock restocked successfully. Added {$count} birds.", $data);
    } else {
        $connect->rollback();
        $updateFlock->close();
        respondBadRequest("Failed to restock flock.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
