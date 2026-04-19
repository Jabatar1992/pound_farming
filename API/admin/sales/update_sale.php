<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['sale_type'], $_POST['quantity'], $_POST['unit_price'], $_POST['buyer_name'], $_POST['sale_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. id, sale_type, quantity, unit_price, buyer_name, sale_date and recorded_by are required.");
}

$id          = (int) trim($_POST['id']);
$sale_type   = strip_tags(trim($_POST['sale_type']));
$quantity    = (int) trim($_POST['quantity']);
$unit_price  = (float) trim($_POST['unit_price']);
$buyer_name  = strip_tags(trim($_POST['buyer_name']));
$buyer_phone = isset($_POST['buyer_phone']) ? strip_tags(trim($_POST['buyer_phone'])) : null;
$sale_date   = strip_tags(trim($_POST['sale_date']));
$recorded_by = (int) trim($_POST['recorded_by']);

$validSaleTypes = ['live_birds', 'eggs', 'dressed_birds'];

if ($id <= 0) {
    respondBadRequest("Invalid sale ID.");
} elseif (!in_array($sale_type, $validSaleTypes)) {
    respondBadRequest("sale_type must be one of: " . implode(', ', $validSaleTypes) . ".");
} elseif ($quantity <= 0) {
    respondBadRequest("Quantity must be a positive number.");
} elseif ($unit_price <= 0) {
    respondBadRequest("Unit price must be a positive number.");
} elseif (input_is_invalid($buyer_name)) {
    respondBadRequest("Buyer name is required.");
} elseif (input_is_invalid($sale_date)) {
    respondBadRequest("Sale date is required (YYYY-MM-DD).");
} elseif ($recorded_by <= 0) {
    respondBadRequest("Invalid worker ID for recorded_by.");
}

$existing = $connect->prepare("SELECT s.id, s.sale_type, s.quantity, s.flock_id, f.current_count FROM sale s JOIN flock f ON f.id = s.flock_id WHERE s.id = ? LIMIT 1");
$existing->bind_param("i", $id);
$existing->execute();
$existingResult = $existing->get_result();
if ($existingResult->num_rows === 0) {
    $existing->close();
    respondNotFound([]);
}
$record = $existingResult->fetch_assoc();
$existing->close();

// Adjust current_count for bird sales: restore old qty then deduct new qty
$newFlockCount = $record['current_count'];
if (in_array($record['sale_type'], ['live_birds', 'dressed_birds'])) {
    $newFlockCount += $record['quantity']; // restore old
}
if (in_array($sale_type, ['live_birds', 'dressed_birds'])) {
    if ($quantity > $newFlockCount) {
        respondBadRequest("Sale quantity exceeds current flock size (" . $newFlockCount . ").");
    }
    $newFlockCount -= $quantity;
}

$workerCheck = $connect->prepare("SELECT id FROM worker WHERE id = ? LIMIT 1");
$workerCheck->bind_param("i", $recorded_by);
$workerCheck->execute();
if ($workerCheck->get_result()->num_rows === 0) {
    $workerCheck->close();
    respondBadRequest("Worker not found.");
}
$workerCheck->close();

$total_amount = $quantity * $unit_price;

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE sale SET sale_type = ?, quantity = ?, unit_price = ?, total_amount = ?, buyer_name = ?, buyer_phone = ?, sale_date = ?, recorded_by = ? WHERE id = ?");
    $stmt->bind_param("siiddssii", $sale_type, $quantity, $unit_price, $total_amount, $buyer_name, $buyer_phone, $sale_date, $recorded_by, $id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $updateFlock = $connect->prepare("UPDATE flock SET current_count = ? WHERE id = ?");
        $updateFlock->bind_param("ii", $newFlockCount, $record['flock_id']);
        $updateFlock->execute();
        $updateFlock->close();

        $connect->commit();
        $stmt->close();

        $get = $connect->prepare("SELECT s.id, s.flock_id, fl.batch_number, s.sale_type, s.quantity, s.unit_price, s.total_amount, s.buyer_name, s.buyer_phone, s.sale_date, s.recorded_by, w.name AS recorded_by_name, s.created_at FROM sale s JOIN flock fl ON fl.id = s.flock_id JOIN worker w ON w.id = s.recorded_by WHERE s.id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Sale updated successfully.", $data);
    } else {
        $connect->rollback();
        $stmt->close();
        respondBadRequest("Failed to update sale.");
    }

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
