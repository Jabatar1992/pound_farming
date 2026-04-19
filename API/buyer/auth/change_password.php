<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['current_password'], $_POST['new_password'])) {
    respondBadRequest("Invalid request. current_password and new_password are required.");
}

$current_password = trim($_POST['current_password']);
$new_password     = trim($_POST['new_password']);

if ($current_password === '') {
    respondBadRequest("Current password is required.");
} elseif (strlen($new_password) < 6) {
    respondBadRequest("New password must be at least 6 characters.");
} elseif ($current_password === $new_password) {
    respondBadRequest("New password must be different from current password.");
}

$stmt = $connect->prepare("SELECT password FROM buyer WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    respondNotFound([]);
}
$row = $result->fetch_assoc();
$stmt->close();

if (!check_pass($current_password, $row['password'])) {
    respondBadRequest("Current password is incorrect.");
}

$hashed = Password_encrypt($new_password);

$connect->begin_transaction();
try {

    $update = $connect->prepare("UPDATE buyer SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed, $buyer_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        $connect->commit();
        $update->close();
        respondOK("Password changed successfully.", []);
    } else {
        $connect->rollback();
        $update->close();
        respondBadRequest("Failed to change password.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
