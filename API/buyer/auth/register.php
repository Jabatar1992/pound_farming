<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!isset($_POST['name'], $_POST['phone'], $_POST['email'], $_POST['password'])) {
    respondBadRequest("Invalid request. name, phone, email and password are required.");
}

$name     = strip_tags(trim($_POST['name']));
$phone    = strip_tags(trim($_POST['phone']));
$email    = strip_tags(trim($_POST['email']));
$password = trim($_POST['password']);
$address  = isset($_POST['address']) ? strip_tags(trim($_POST['address'])) : null;

if (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($phone) || strlen($phone) < 10) {
    respondBadRequest("A valid phone number is required.");
} elseif (input_is_invalid($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("A valid email address is required.");
} elseif (strlen($password) < 6) {
    respondBadRequest("Password must be at least 6 characters.");
} else {

    $check = $connect->prepare("SELECT id FROM buyer WHERE phone = ? OR email = ? LIMIT 1");
    $check->bind_param("ss", $phone, $email);
    $check->execute();
    $isDuplicate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isDuplicate) {
        respondBadRequest("Phone or email already registered.");
    }

    $hashedPassword = Password_encrypt($password);

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO buyer (name, phone, email, address, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $phone, $email, $address, $hashedPassword);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, phone, email, address, status, created_at FROM buyer WHERE id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            $token = getTokenToSendAPI($inserted_id, 'buyer');

            respondOK("Registration successful.", array_merge($data, [
                "access_token" => $token,
                "token_type"   => "Bearer",
            ]));
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Registration failed.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
