<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!isset($_POST['email'], $_POST['password'])) {
    respondBadRequest("Invalid request. Email and password are required.");
}

$email    = strip_tags(trim($_POST['email']));
$password = trim($_POST['password']);

if (input_is_invalid($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondBadRequest("A valid email address is required.");
} elseif ($password === '') {
    respondBadRequest("Password is required.");
} else {

    $stmt = $connect->prepare("SELECT id, name, phone, email, address, status, password FROM buyer WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respondBadRequest("Invalid email or password.");
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['status'] !== 'active') {
        respondBadRequest("Your account is inactive. Contact support.");
    }

    if (!check_pass($password, $row['password'])) {
        respondBadRequest("Invalid email or password.");
    }

    $token = getTokenToSendAPI($row['id'], 'buyer');

    respondOK("Login successful.", [
        "id"           => $row['id'],
        "name"         => $row['name'],
        "phone"        => $row['phone'],
        "email"        => $row['email'],
        "address"      => $row['address'],
        "access_token" => $token,
        "token_type"   => "Bearer",
    ]);
}
