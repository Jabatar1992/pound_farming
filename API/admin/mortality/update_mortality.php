<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['count'], $_POST['cause'], $_POST['mortality_date'])) {
    respondBadRequest("Invalid request. id, count, cause and mortality_date are required.");
}

$id             = (int) trim($_POST['id']);
$count          = (int) trim($_POST['count']);
$cause          = strip_tags(trim($_POST['cause']));
$mortality_date = strip_tags(trim($_POST['mortality_date']));
$notes          = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

$validCauses = ['disease', 'predator', 'injury', 'unknown', 'other'];

if ($id <= 0) {
    respondBadRequest("Invalid mortality ID.");
} elseif ($count <= 0) {
    respondBadRequest("Count must be at least 1.");
} elseif (!in_array($cause, $validCauses)) {
    respondBadRequest("cause must be one of: " . implode(', ', $validCauses) . ".");
} elseif (input_is_invalid($mortality_date)) {
    respondBadRequest("Mortality date is required (YYYY-MM-DD).");
}

$existing = $connect->prepare("SELECT m.id, m.count, m.flock_id, f.current_count FROM mortality m JOIN flock f ON f.id = m.flock_id WHERE m.id = ? LIMIT 1");
$existing->bind_param("i", $id);
$existing->execute();
$existingResult = $existing->get_result();
if ($existingResult->num_rows === 0) {
    $existing->close();
    respondNotFound([]);
}
$record = $existingResult->fetch_assoc();
$existing->close();

$countDiff    = $count - $record['count'];
$newFlockCount = $record['current_count'] - $countDiff;

if ($newFlockCount < 0) {
    respondBadRequest("Updated count exceeds current flock size (" . $record['current_count'] . ").");
}

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE mortality SET count = ?, cause = ?, mortality_date = ?, notes = ? WHERE id = ?");
    $stmt->bind_param("isssi", $count, $cause, $mortality_date, $notes, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $updateFlock = $connect->prepare("UPDATE flock SET current_count = ? WHERE id = ?");
        $updateFlock->bind_param("ii", $newFlockCount, $record['flock_id']);
        $updateFlock->execute();
        $updateFlock->close();

        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT m.id, m.flock_id, fl.batch_number, m.count, m.cause, m.mortality_date, m.recorded_by, w.name AS recorded_by_name, m.notes, m.created_at FROM mortality m JOIN flock fl ON fl.id = m.flock_id JOIN worker w ON w.id = m.recorded_by WHERE m.id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Mortality record updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update mortality record.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
