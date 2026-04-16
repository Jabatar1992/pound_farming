<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['feed_id'], $_POST['quantity_kg'], $_POST['consumption_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. flock_id, feed_id, quantity_kg, consumption_date and recorded_by are required.");
}

$flock_id         = (int) trim($_POST['flock_id']);
$feed_id          = (int) trim($_POST['feed_id']);
$quantity_kg      = (float) trim($_POST['quantity_kg']);
$consumption_date = strip_tags(trim($_POST['consumption_date']));
$recorded_by      = (int) trim($_POST['recorded_by']);
$notes            = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif ($feed_id <= 0) {
    respondBadRequest("Invalid feed ID.");
} elseif ($quantity_kg <= 0) {
    respondBadRequest("Quantity must be a positive number.");
} elseif (input_is_invalid($consumption_date)) {
    respondBadRequest("Consumption date is required (YYYY-MM-DD).");
} elseif ($recorded_by <= 0) {
    respondBadRequest("Invalid worker ID for recorded_by.");
} else {

    $flockCheck = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
    $flockCheck->bind_param("i", $flock_id);
    $flockCheck->execute();
    if ($flockCheck->get_result()->num_rows === 0) {
        $flockCheck->close();
        respondBadRequest("Flock not found.");
    }
    $flockCheck->close();

    $feedCheck = $connect->prepare("SELECT id FROM feed WHERE id = ? LIMIT 1");
    $feedCheck->bind_param("i", $feed_id);
    $feedCheck->execute();
    if ($feedCheck->get_result()->num_rows === 0) {
        $feedCheck->close();
        respondBadRequest("Feed not found.");
    }
    $feedCheck->close();

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

        $stmt = $connect->prepare("INSERT INTO feed_consumption (flock_id, feed_id, quantity_kg, consumption_date, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsis", $flock_id, $feed_id, $quantity_kg, $consumption_date, $recorded_by, $notes);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT fc.id, fc.flock_id, fl.batch_number, fc.feed_id, fd.name AS feed_name, fc.quantity_kg, fc.consumption_date, fc.recorded_by, w.name AS recorded_by_name, fc.notes, fc.created_at FROM feed_consumption fc JOIN flock fl ON fl.id = fc.flock_id JOIN feed fd ON fd.id = fc.feed_id JOIN worker w ON w.id = fc.recorded_by WHERE fc.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Feed consumption recorded successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to record feed consumption.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
