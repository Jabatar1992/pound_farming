<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$stmt = $connect->prepare("SELECT id, name, location, capacity, pen_type, status, created_at FROM farm ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$farms = [];
while ($row = $result->fetch_assoc()) {
    $farms[] = $row;
}

respondOK("Farms retrieved successfully.", $farms);
