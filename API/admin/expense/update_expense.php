<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['category'], $_POST['description'], $_POST['amount'], $_POST['expense_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. id, category, description, amount, expense_date and recorded_by are required.");
}

$id           = (int) trim($_POST['id']);
$category     = strip_tags(trim($_POST['category']));
$description  = strip_tags(trim($_POST['description']));
$amount       = (float) trim($_POST['amount']);
$expense_date = strip_tags(trim($_POST['expense_date']));
$recorded_by  = (int) trim($_POST['recorded_by']);

$validCategories = ['feed', 'medication', 'equipment', 'labor', 'utilities', 'transport', 'other'];

if ($id <= 0) {
    respondBadRequest("Invalid expense ID.");
} elseif (!in_array($category, $validCategories)) {
    respondBadRequest("category must be one of: " . implode(', ', $validCategories) . ".");
} elseif (input_is_invalid($description)) {
    respondBadRequest("Description is required.");
} elseif ($amount <= 0) {
    respondBadRequest("Amount must be a positive number.");
} elseif (input_is_invalid($expense_date)) {
    respondBadRequest("Expense date is required (YYYY-MM-DD).");
} elseif ($recorded_by <= 0) {
    respondBadRequest("Invalid worker ID for recorded_by.");
}

$exists = $connect->prepare("SELECT id FROM expense WHERE id = ? LIMIT 1");
$exists->bind_param("i", $id);
$exists->execute();
if ($exists->get_result()->num_rows === 0) {
    $exists->close();
    respondNotFound([]);
}
$exists->close();

$workerCheck = $connect->prepare("SELECT id FROM worker WHERE id = ? LIMIT 1");
$workerCheck->bind_param("i", $recorded_by);
$workerCheck->execute();
if ($workerCheck->get_result()->num_rows === 0) {
    $workerCheck->close();
    respondBadRequest("Worker not found.");
}
$workerCheck->close();

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE expense SET category = ?, description = ?, amount = ?, expense_date = ?, recorded_by = ? WHERE id = ?");
    $stmt->bind_param("ssdsii", $category, $description, $amount, $expense_date, $recorded_by, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT e.id, e.category, e.description, e.amount, e.expense_date, e.recorded_by, w.name AS recorded_by_name, e.created_at FROM expense e JOIN worker w ON w.id = e.recorded_by WHERE e.id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Expense updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update expense.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
