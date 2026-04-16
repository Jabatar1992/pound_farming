<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_GET['id'])) {
    respondBadRequest("Expense ID is required.");
}

$id = (int) trim($_GET['id']);

if ($id <= 0) {
    respondBadRequest("Invalid expense ID.");
}

$stmt = $connect->prepare("SELECT e.id, e.category, e.description, e.amount, e.expense_date, e.recorded_by, w.name AS recorded_by_name, e.created_at FROM expense e JOIN worker w ON w.id = e.recorded_by WHERE e.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

respondOK("Expense retrieved successfully.", $result->fetch_assoc());
