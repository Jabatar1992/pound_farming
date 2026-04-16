<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$stmt = $connect->prepare("SELECT id, name, phone, email, role, status, created_at FROM worker ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$workers = [];
while ($row = $result->fetch_assoc()) {
    $workers[] = $row;
}

respondOK("Workers retrieved successfully.", $workers);
