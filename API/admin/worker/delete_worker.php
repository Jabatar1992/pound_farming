<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'])) {
    respondBadRequest("Worker ID is required.");
}

$id = (int) trim($_POST['id']);

if ($id <= 0) {
    respondBadRequest("Invalid worker ID.");
}

$exists = $connect->prepare("SELECT id FROM worker WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
$found = $exists->get_result()->num_rows > 0;
$exists->close();

if (!$found) {
    respondNotFound([]);
}

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("DELETE FROM worker WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $connect->commit();
        $stmt->close();
        respondOK("Worker deleted successfully.", []);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to delete worker.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
