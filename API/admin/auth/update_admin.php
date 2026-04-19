<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['name'], $_POST['email'])) {
    respondBadRequest("Invalid request. id, name and email are required.");
}

$id    = (int) trim($_POST['id']);
$name  = strip_tags(trim($_POST['name']));
$email = strip_tags(trim($_POST['email']));

if ($id <= 0) {
    respondBadRequest("Invalid admin ID.");
} elseif (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("A valid email address is required.");
}

$exists = $connect->prepare("SELECT id FROM admin WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
if ($exists->get_result()->num_rows === 0) {
    $exists->close();
    respondNotFound([]);
}
$exists->close();

$emailCheck = $connect->prepare("SELECT id FROM admin WHERE email = ? AND id != ? LIMIT 1");
$emailCheck->bind_param("si", $email, $id);
$emailCheck->execute();
if ($emailCheck->get_result()->num_rows > 0) {
    $emailCheck->close();
    respondBadRequest("Email is already used by another admin.");
}
$emailCheck->close();

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE admin SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT id, admin_id, name, email, created_at FROM admin WHERE id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Admin updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update admin.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
