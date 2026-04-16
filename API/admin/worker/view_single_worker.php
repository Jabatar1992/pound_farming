<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Worker ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid worker ID.");
}

$stmt = $connect->prepare("SELECT id, name, phone, email, role, status, created_at FROM worker WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Worker retrieved successfully.", $result->fetch_assoc());
