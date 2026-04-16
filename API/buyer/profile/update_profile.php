<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['name'], $_POST['phone'])) {
    respondBadRequest("Invalid request. name and phone are required.");
}

$name     = strip_tags(trim($_POST['name']));
$phone    = strip_tags(trim($_POST['phone']));
$address  = isset($_POST['address']) ? strip_tags(trim($_POST['address'])) : null;
$password = isset($_POST['password']) ? trim($_POST['password']) : null;

if (input_is_invalid($name)) {
    respondBadRequest("Name is required.");
} elseif (input_is_invalid($phone) || strlen($phone) < 10) {
    respondBadRequest("A valid phone number is required.");
} elseif ($password !== null && strlen($password) < 6) {
    respondBadRequest("Password must be at least 6 characters.");
} else {

    // Check phone uniqueness — allow current buyer's own phone
    $check = $connect->prepare("SELECT id FROM buyer WHERE phone = ? AND id != ? LIMIT 1");
    $check->bind_param("si", $phone, $buyer_id);
    $check->execute();
    $isDuplicate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isDuplicate) {
        respondBadRequest("Phone number is already used by another account.");
    }

    $connect->begin_transaction();
    try {

        if ($password !== null) {
            $hashedPassword = Password_encrypt($password);
            $stmt = $connect->prepare("UPDATE buyer SET name = ?, phone = ?, address = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $phone, $address, $hashedPassword, $buyer_id);
        } else {
            $stmt = $connect->prepare("UPDATE buyer SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $phone, $address, $buyer_id);
        }

        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, phone, email, address, status, created_at FROM buyer WHERE id = ?");
            $get->bind_param("i", $buyer_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Profile updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update profile.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
