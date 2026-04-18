<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'])) {
    respondBadRequest("Flock ID is required.");
}

$id = (int) trim($_POST['id']);

if ($id <= 0) {
    respondBadRequest("Invalid flock ID.");
}

$exists = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
$found = $exists->get_result()->num_rows > 0;
$exists->close();

if (!$found) {
    respondNotFound([]);
}

// Check for related records before attempting delete (use only string literals in SQL)
$related = [];

$relChecks = [
    [$connect->prepare("SELECT COUNT(*) AS cnt FROM sale             WHERE flock_id = ?"), 'sales'],
    [$connect->prepare("SELECT COUNT(*) AS cnt FROM mortality        WHERE flock_id = ?"), 'mortality records'],
    [$connect->prepare("SELECT COUNT(*) AS cnt FROM feed_consumption WHERE flock_id = ?"), 'feed consumption records'],
    [$connect->prepare("SELECT COUNT(*) AS cnt FROM egg_production   WHERE flock_id = ?"), 'egg production records'],
    [$connect->prepare("SELECT COUNT(*) AS cnt FROM health_record    WHERE flock_id = ?"), 'health records'],
];

foreach ($relChecks as [$chk, $label]) {
    $chk->bind_param("i", $id);
    $chk->execute();
    $cnt = (int) $chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();
    if ($cnt > 0) {
        $related[] = "$cnt $label";
    }
}

if (!empty($related)) {
    respondBadRequest(
        "Cannot delete flock. It has linked records: " . implode(', ', $related) . ". "
        . "Delete those records first or archive the flock by changing its status to 'closed'."
    );
}

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("DELETE FROM flock WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $connect->commit();
        $stmt->close();
        respondOK("Flock deleted successfully.", []);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to delete flock.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
