<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['name'], $_POST['feed_type'], $_POST['quantity_kg'], $_POST['unit_price'], $_POST['purchase_date'])) {
    respondBadRequest("Invalid request. id, name, feed_type, quantity_kg, unit_price and purchase_date are required.");
}

$id            = (int) trim($_POST['id']);
$name          = strip_tags(trim($_POST['name']));
$feed_type     = strip_tags(trim($_POST['feed_type']));
$quantity_kg   = (float) trim($_POST['quantity_kg']);
$unit_price    = (float) trim($_POST['unit_price']);
$purchase_date = strip_tags(trim($_POST['purchase_date']));
$supplier      = isset($_POST['supplier']) ? strip_tags(trim($_POST['supplier'])) : null;

$validFeedTypes = ['starter', 'grower', 'finisher', 'layer', 'broiler', 'supplement', 'other'];

if ($id <= 0) {
    respondBadRequest("Invalid feed ID.");
} elseif (input_is_invalid($name)) {
    respondBadRequest("Feed name is required.");
} elseif (!in_array($feed_type, $validFeedTypes)) {
    respondBadRequest("feed_type must be one of: " . implode(', ', $validFeedTypes) . ".");
} elseif ($quantity_kg <= 0) {
    respondBadRequest("Quantity must be a positive number.");
} elseif ($unit_price <= 0) {
    respondBadRequest("Unit price must be a positive number.");
} elseif (input_is_invalid($purchase_date)) {
    respondBadRequest("Purchase date is required (YYYY-MM-DD).");
} else {

    $exists = $connect->prepare("SELECT id FROM feed WHERE id = ? LIMIT 1");
    $exists->bind_param("i", $id);
    $exists->execute();
    $found = $exists->get_result()->num_rows > 0;
    $exists->close();

    if (!$found) {
        respondNotFound([]);
    }

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("UPDATE feed SET name = ?, feed_type = ?, quantity_kg = ?, unit_price = ?, supplier = ?, purchase_date = ? WHERE id = ?");
        $stmt->bind_param("ssddssi", $name, $feed_type, $quantity_kg, $unit_price, $supplier, $purchase_date, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, feed_type, quantity_kg, unit_price, supplier, purchase_date, created_at FROM feed WHERE id = ?");
            $get->bind_param("i", $id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Feed updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update feed.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
