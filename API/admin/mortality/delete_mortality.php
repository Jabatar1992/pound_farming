<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'])) {
    respondBadRequest("Mortality ID is required.");
}

$id = (int) trim($_POST['id']);

if ($id <= 0) {
    respondBadRequest("Invalid mortality ID.");
}

$existing = $connect->prepare("SELECT id, count, flock_id FROM mortality WHERE id = ? LIMIT 1");
$existing->bind_param("i", $id);
$existing->execute();
$existingResult = $existing->get_result();
if ($existingResult->num_rows === 0) {
    $existing->close();
    respondNotFound([]);
}
$record = $existingResult->fetch_assoc();
$existing->close();

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("DELETE FROM mortality WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $restore = $connect->prepare("UPDATE flock SET current_count = current_count + ? WHERE id = ?");
        $restore->bind_param("ii", $record['count'], $record['flock_id']);
        $restore->execute();
        $restore->close();

        $connect->commit();
        $stmt->close();
        respondOK("Mortality record deleted successfully.", []);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to delete mortality record.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
