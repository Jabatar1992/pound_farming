<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'])) {
    respondBadRequest("Sale ID is required.");
}

$id = (int) trim($_POST['id']);

if ($id <= 0) {
    respondBadRequest("Invalid sale ID.");
}

$existing = $connect->prepare("SELECT id, sale_type, quantity, flock_id FROM sale WHERE id = ? LIMIT 1");
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

    $stmt = $connect->prepare("DELETE FROM sale WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        if (in_array($record['sale_type'], ['live_birds', 'dressed_birds'])) {
            $restore = $connect->prepare("UPDATE flock SET current_count = current_count + ? WHERE id = ?");
            $restore->bind_param("ii", $record['quantity'], $record['flock_id']);
            $restore->execute();
            $restore->close();
        }

        $connect->commit();
        $stmt->close();
        respondOK("Sale deleted successfully.", []);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to delete sale.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
