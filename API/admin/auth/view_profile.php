<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('admin');
$admin_id = (int) $token->usertoken;

$stmt = $connect->prepare("SELECT id, admin_id, name, email, created_at FROM admin WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    respondNotFound([]);
}

$data = $result->fetch_assoc();
$stmt->close();

respondOK("Profile retrieved successfully.", $data);
