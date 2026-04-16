<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!isset($_POST['phone'], $_POST['password'])) {
    respondBadRequest("Invalid request. Phone and password are required.");
}

$phone    = strip_tags(trim($_POST['phone']));
$password = trim($_POST['password']);

if (input_is_invalid($phone)) {
    respondBadRequest("Phone number is required.");
} elseif ($password === '') {
    respondBadRequest("Password is required.");
} else {

    $stmt = $connect->prepare("SELECT id, name, phone, email, role, status, password FROM worker WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respondBadRequest("Invalid phone or password.");
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['status'] !== 'active') {
        respondBadRequest("Your account is inactive. Contact admin.");
    }

    if (!check_pass($password, $row['password'])) {
        respondBadRequest("Invalid phone or password.");
    }

    $token = getTokenToSendAPI($row['id'], 'user');

    respondOK("Login successful.", [
        "id"           => $row['id'],
        "name"         => $row['name'],
        "phone"        => $row['phone'],
        "email"        => $row['email'],
        "role"         => $row['role'],
        "access_token" => $token,
        "token_type"   => "Bearer",
    ]);
}
