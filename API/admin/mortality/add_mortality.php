<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['count'], $_POST['cause'], $_POST['mortality_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. flock_id, count, cause, mortality_date and recorded_by are required.");
}

$flock_id      = (int) trim($_POST['flock_id']);
$count         = (int) trim($_POST['count']);
$cause         = strip_tags(trim($_POST['cause']));
$mortality_date = strip_tags(trim($_POST['mortality_date']));
$recorded_by   = (int) trim($_POST['recorded_by']);
$notes         = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

$validCauses = ['disease', 'predator', 'injury', 'unknown', 'other'];

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif ($count <= 0) {
    respondBadRequest("Count must be at least 1.");
} elseif (!in_array($cause, $validCauses)) {
    respondBadRequest("cause must be one of: " . implode(', ', $validCauses) . ".");
} elseif (input_is_invalid($mortality_date)) {
    respondBadRequest("Mortality date is required (YYYY-MM-DD).");
} elseif ($recorded_by <= 0) {
    respondBadRequest("Invalid worker ID for recorded_by.");
} else {

    $flockCheck = $connect->prepare("SELECT id, current_count FROM flock WHERE id = ? LIMIT 1");
    $flockCheck->bind_param("i", $flock_id);
    $flockCheck->execute();
    $flockResult = $flockCheck->get_result();
    if ($flockResult->num_rows === 0) {
        $flockCheck->close();
        respondBadRequest("Flock not found.");
    }
    $flock = $flockResult->fetch_assoc();
    $flockCheck->close();

    if ($count > $flock['current_count']) {
        respondBadRequest("Mortality count exceeds current flock size (" . $flock['current_count'] . ").");
    }

    $workerCheck = $connect->prepare("SELECT id FROM worker WHERE id = ? LIMIT 1");
    $workerCheck->bind_param("i", $recorded_by);
    $workerCheck->execute();
    if ($workerCheck->get_result()->num_rows === 0) {
        $workerCheck->close();
        respondBadRequest("Worker not found.");
    }
    $workerCheck->close();

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO mortality (flock_id, count, cause, mortality_date, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissis", $flock_id, $count, $cause, $mortality_date, $recorded_by, $notes);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;

            $newCount = $flock['current_count'] - $count;
            $updateFlock = $connect->prepare("UPDATE flock SET current_count = ? WHERE id = ?");
            $updateFlock->bind_param("ii", $newCount, $flock_id);
            $updateFlock->execute();
            $updateFlock->close();

            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT m.id, m.flock_id, fl.batch_number, m.count, m.cause, m.mortality_date, m.recorded_by, w.name AS recorded_by_name, m.notes, m.created_at FROM mortality m JOIN flock fl ON fl.id = m.flock_id JOIN worker w ON w.id = m.recorded_by WHERE m.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Mortality recorded successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to record mortality.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
