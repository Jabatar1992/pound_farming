<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$token   = ValidateAPITokenSentIN('admin');
$self_id = (int) $token->usertoken;

$minStmt = $connect->query("SELECT MIN(id) AS min_id FROM admin");
$minId   = (int) $minStmt->fetch_assoc()['min_id'];

// Block non-super admins
if ($self_id !== $minId) {
    respondForbiddenAuthorized([]);
}

$stmt = $connect->query("SELECT id, admin_id, name, email, created_at FROM admin ORDER BY created_at DESC");
$admins = [];
while ($row = $stmt->fetch_assoc()) {
    $admins[] = $row;
}

respondOK("Admins retrieved successfully.", $admins);
