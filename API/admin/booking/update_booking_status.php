<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token      = ValidateAPITokenSentIN('admin');
$updated_by = (int) $token->usertoken;

if (!isset($_POST['booking_id'], $_POST['order_status'])) {
    respondBadRequest("Invalid request. booking_id and order_status are required.");
}

$booking_id   = (int) trim($_POST['booking_id']);
$order_status = strip_tags(trim($_POST['order_status']));
$note         = isset($_POST['note']) ? strip_tags(trim($_POST['note'])) : null;

$validStatuses = ['pending', 'confirmed', 'paid', 'dispatched', 'delivered', 'cancelled'];

if ($booking_id <= 0) {
    respondBadRequest("Invalid booking ID.");
} elseif (!in_array($order_status, $validStatuses)) {
    respondBadRequest("order_status must be one of: " . implode(', ', $validStatuses) . ".");
} else {

    $exists = $connect->prepare("SELECT id, order_status FROM egg_booking WHERE id = ? LIMIT 1");
    $exists->bind_param("i", $booking_id);
    $exists->execute();
    $bookingResult = $exists->get_result();
    if ($bookingResult->num_rows === 0) {
        $exists->close();
        respondNotFound([]);
    }
    $exists->close();

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("UPDATE egg_booking SET order_status = ? WHERE id = ?");
        $stmt->bind_param("si", $order_status, $booking_id);
        $stmt->execute();
        $stmt->close();

        $track = $connect->prepare("INSERT INTO order_tracking (booking_id, status, note, updated_by) VALUES (?, ?, ?, ?)");
        $track->bind_param("issi", $booking_id, $order_status, $note, $updated_by);
        $track->execute();
        $track->close();

        $connect->commit();

        $get = $connect->prepare("SELECT eb.id, eb.buyer_id, b.name AS buyer_name, b.phone AS buyer_phone, eb.quantity_crates, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.created_at FROM egg_booking eb JOIN buyer b ON b.id = eb.buyer_id WHERE eb.id = ?");
        $get->bind_param("i", $booking_id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();
        $get->close();

        respondOK("Booking status updated successfully.", $data);

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
