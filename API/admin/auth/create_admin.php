<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['admin_id'], $_POST['name'], $_POST['email'], $_POST['password'])) {
    respondBadRequest("Invalid request. admin_id, name, email and password are required.");
}

$admin_id = strip_tags(trim($_POST['admin_id']));
$name     = strip_tags(trim($_POST['name']));
$email    = strip_tags(trim($_POST['email']));
$password = trim($_POST['password']);

if (input_is_invalid($admin_id)) {
    respondBadRequest("Admin ID is required.");
} elseif (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("A valid email address is required.");
} elseif (strlen($password) < 6) {
    respondBadRequest("Password must be at least 6 characters.");
} else {

    $check = $connect->prepare("SELECT id FROM admin WHERE admin_id = ? OR email = ? LIMIT 1");
    $check->bind_param("ss", $admin_id, $email);
    $check->execute();
    $isDuplicate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isDuplicate) {
        respondBadRequest("Admin ID or email already exists.");
    } else {

        $hashedPassword = Password_encrypt($password);

        $connect->begin_transaction();
        try {

            $stmt = $connect->prepare("INSERT INTO admin (admin_id, name, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $admin_id, $name, $email, $hashedPassword);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $inserted_id = $connect->insert_id;
                $connect->commit();
                $stmt->close();

                $get = $connect->prepare("SELECT id, admin_id, name, email, created_at FROM admin WHERE id = ?");
                $get->bind_param("i", $inserted_id);
                $get->execute();
                $data = $get->get_result()->fetch_assoc();
                $get->close();

                respondOK("Admin created successfully.", $data);
            } else {
                $connect->rollback();
                $stmt->close();
                respondBadRequest("Failed to create admin.");
            }

        } catch (Exception $e) {
            $connect->rollback();
            respondInternalError(get_details_from_exception($e));
        }
    }
}
