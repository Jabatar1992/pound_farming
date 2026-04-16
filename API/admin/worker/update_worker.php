<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['name'], $_POST['phone'], $_POST['role'], $_POST['status'])) {
    respondBadRequest("Invalid request. id, name, phone, role and status are required.");
}

$id     = (int) trim($_POST['id']);
$name   = strip_tags(trim($_POST['name']));
$phone  = strip_tags(trim($_POST['phone']));
$email  = isset($_POST['email']) ? strip_tags(trim($_POST['email'])) : null;
$role   = strip_tags(trim($_POST['role']));
$status = strip_tags(trim($_POST['status']));

$validRoles    = ['worker', 'supervisor'];
$validStatuses = ['active', 'inactive'];

if ($id <= 0) {
    respondBadRequest("Invalid worker ID.");
} elseif (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($phone) || strlen($phone) < 10) {
    respondBadRequest("A valid phone number is required.");
} elseif (!in_array($role, $validRoles)) {
    respondBadRequest("role must be worker or supervisor.");
} elseif (!in_array($status, $validStatuses)) {
    respondBadRequest("status must be active or inactive.");
} elseif ($email !== null && !empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("Invalid email address.");
} else {

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

        $stmt = $connect->prepare("UPDATE worker SET name = ?, phone = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $phone, $email, $role, $status, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, phone, email, role, status, created_at FROM worker WHERE id = ?");
            $get->bind_param("i", $id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Worker updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update worker.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
