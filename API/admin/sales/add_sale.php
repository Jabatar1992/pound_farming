<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['sale_type'], $_POST['quantity'], $_POST['unit_price'], $_POST['buyer_name'], $_POST['sale_date'], $_POST['recorded_by'])) {
    respondBadRequest("Invalid request. flock_id, sale_type, quantity, unit_price, buyer_name, sale_date and recorded_by are required.");
}

$flock_id    = (int) trim($_POST['flock_id']);
$sale_type   = strip_tags(trim($_POST['sale_type']));
$quantity    = (int) trim($_POST['quantity']);
$unit_price  = (float) trim($_POST['unit_price']);
$buyer_name  = strip_tags(trim($_POST['buyer_name']));
$buyer_phone = isset($_POST['buyer_phone']) ? strip_tags(trim($_POST['buyer_phone'])) : null;
$sale_date   = strip_tags(trim($_POST['sale_date']));
$recorded_by = (int) trim($_POST['recorded_by']);

$validSaleTypes = ['live_birds', 'eggs', 'dressed_birds'];

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
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
} else {

    $flockCheck = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
    $flockCheck->bind_param("i", $flock_id);
    $flockCheck->execute();
    if ($flockCheck->get_result()->num_rows === 0) {
        $flockCheck->close();
        respondBadRequest("Flock not found.");
    }
    $flockCheck->close();

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

        $stmt = $connect->prepare("INSERT INTO sale (flock_id, sale_type, quantity, unit_price, total_amount, buyer_name, buyer_phone, sale_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiddsssi", $flock_id, $sale_type, $quantity, $unit_price, $total_amount, $buyer_name, $buyer_phone, $sale_date, $recorded_by);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT s.id, s.flock_id, fl.batch_number, s.sale_type, s.quantity, s.unit_price, s.total_amount, s.buyer_name, s.buyer_phone, s.sale_date, s.recorded_by, w.name AS recorded_by_name, s.created_at FROM sale s JOIN flock fl ON fl.id = s.flock_id JOIN worker w ON w.id = s.recorded_by WHERE s.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Sale recorded successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to record sale.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
