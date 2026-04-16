<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

$category = isset($_GET['category']) ? strip_tags(trim($_GET['category'])) : '';

if (!empty($category)) {
    $stmt = $connect->prepare("SELECT e.id, e.category, e.description, e.amount, e.expense_date, e.recorded_by, w.name AS recorded_by_name, e.created_at FROM expense e JOIN worker w ON w.id = e.recorded_by WHERE e.category = ? ORDER BY e.expense_date DESC");
    $stmt->bind_param("s", $category);
} else {
    $stmt = $connect->prepare("SELECT e.id, e.category, e.description, e.amount, e.expense_date, e.recorded_by, w.name AS recorded_by_name, e.created_at FROM expense e JOIN worker w ON w.id = e.recorded_by ORDER BY e.expense_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$expenses = [];
$total    = 0.0;
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total += (float) $row['amount'];
}

respondOK("Expenses retrieved successfully.", [
    "total_amount" => $total,
    "expenses"     => $expenses,
]);
