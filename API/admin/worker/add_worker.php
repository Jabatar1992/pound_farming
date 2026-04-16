<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['name'], $_POST['phone'], $_POST['password'])) {
    respondBadRequest("Invalid request. name, phone and password are required.");
}

$name     = strip_tags(trim($_POST['name']));
$phone    = strip_tags(trim($_POST['phone']));
$password = trim($_POST['password']);
$email    = isset($_POST['email']) ? strip_tags(trim($_POST['email'])) : null;
$role     = isset($_POST['role'])  ? strip_tags(trim($_POST['role']))  : 'worker';

$validRoles = ['worker', 'supervisor'];

if (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($phone) || strlen($phone) < 10) {
    respondBadRequest("A valid phone number is required (minimum 10 digits).");
} elseif (strlen($password) < 6) {
    respondBadRequest("Password must be at least 6 characters.");
} elseif (!in_array($role, $validRoles)) {
    respondBadRequest("role must be worker or supervisor.");
} elseif ($email !== null && !empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("Invalid email address.");
} else {

    $check = $connect->prepare("SELECT id FROM worker WHERE phone = ? LIMIT 1");
    $check->bind_param("s", $phone);
    $check->execute();
    $isDuplicate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isDuplicate) {
        respondBadRequest("Phone number already registered.");
    }

    $hashedPassword = Password_encrypt($password);

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO worker (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $phone, $email, $hashedPassword, $role);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, phone, email, role, status, created_at FROM worker WHERE id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Worker added successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to add worker.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
