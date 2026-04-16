<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['booking_id'])) {
    respondBadRequest("Booking ID is required.");
}

$booking_id = (int) trim($_POST['booking_id']);

if ($booking_id <= 0) {
    respondBadRequest("Invalid booking ID.");
}

$exists = $connect->prepare("SELECT id, order_status, availability_id, quantity_crates, payment_status FROM egg_booking WHERE id = ? AND buyer_id = ? LIMIT 1");
$exists->bind_param("ii", $booking_id, $buyer_id);
$exists->execute();
$bookingResult = $exists->get_result();

if ($bookingResult->num_rows === 0) {
    $exists->close();
    respondNotFound([]);
}

$booking = $bookingResult->fetch_assoc();
$exists->close();

$nonCancellable = ['dispatched', 'delivered', 'cancelled'];
if (in_array($booking['order_status'], $nonCancellable)) {
    respondBadRequest("This booking cannot be cancelled. Current status: " . $booking['order_status'] . ".");
}

$connect->begin_transaction();
try {

    $stmt = $connect->prepare("UPDATE egg_booking SET order_status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    $restoreAvail = $connect->prepare("UPDATE egg_availability SET available_crates = available_crates + ?, is_available = 1 WHERE id = ?");
    $restoreAvail->bind_param("ii", $booking['quantity_crates'], $booking['availability_id']);
    $restoreAvail->execute();
    $restoreAvail->close();

    $connect->commit();

    respondOK("Booking cancelled successfully.", ["booking_id" => $booking_id, "order_status" => "cancelled"]);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
