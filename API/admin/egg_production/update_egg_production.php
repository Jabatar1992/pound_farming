<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['eggs_collected'], $_POST['collection_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. id, eggs_collected, collection_date and recorded_by are required.");
}

$id              = (int) trim($_POST['id']);
$eggs_collected  = (int) trim($_POST['eggs_collected']);
$broken_eggs     = isset($_POST['broken_eggs']) ? (int) trim($_POST['broken_eggs']) : 0;
$collection_date = strip_tags(trim($_POST['collection_date']));
$recorded_by     = (int) trim($_POST['recorded_by']);
$notes           = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

if ($id <= 0) {
    respondBadRequest("Invalid egg production ID.");
} elseif ($eggs_collected < 0) {
    respondBadRequest("Eggs collected cannot be negative.");
} elseif ($broken_eggs < 0) {
    respondBadRequest("Broken eggs cannot be negative.");
} elseif (input_is_invalid($collection_date)) {
    respondBadRequest("Collection date is required (YYYY-MM-DD).");
} elseif ($recorded_by <= 0) {
    respondBadRequest("Invalid worker ID for recorded_by.");
}

$exists = $connect->prepare("SELECT id FROM egg_production WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
if ($exists->get_result()->num_rows === 0) {
    $exists->close();
    respondNotFound([]);
}
$exists->close();

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

    $stmt = $connect->prepare("UPDATE egg_production SET eggs_collected = ?, broken_eggs = ?, collection_date = ?, recorded_by = ?, notes = ? WHERE id = ?");
    $stmt->bind_param("iisisi", $eggs_collected, $broken_eggs, $collection_date, $recorded_by, $notes, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT ep.id, ep.flock_id, fl.batch_number, ep.eggs_collected, ep.broken_eggs, ep.collection_date, ep.recorded_by, w.name AS recorded_by_name, ep.notes, ep.created_at FROM egg_production ep JOIN flock fl ON fl.id = ep.flock_id JOIN worker w ON w.id = ep.recorded_by WHERE ep.id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Egg production updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update egg production.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
