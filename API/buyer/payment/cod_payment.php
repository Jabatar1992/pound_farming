<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!COD_ENABLED) {
    respondBadRequest("Cash on delivery is not available at this time.");
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['booking_id'])) { respondBadRequest("Booking ID is required."); }

$booking_id = (int) trim($_POST['booking_id']);
if ($booking_id <= 0) { respondBadRequest("Invalid booking ID."); }

$stmt = $connect->prepare(
    "SELECT id, buyer_id, total_amount, payment_status, order_status
     FROM egg_booking WHERE id = ? AND buyer_id = ? LIMIT 1"
);
$stmt->bind_param("ii", $booking_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) { respondNotFound([]); }

$booking = $result->fetch_assoc();

if ($booking['payment_status'] === 'paid')      { respondBadRequest("This booking is already paid."); }
if ($booking['order_status']   === 'cancelled') { respondBadRequest("Cannot update a cancelled booking."); }

$connect->begin_transaction();
try {
    $ref = 'COD_' . $booking_id . '_' . time();

    $upd = $connect->prepare(
        "UPDATE egg_booking
         SET payment_method    = 'cod',
             payment_reference = ?,
             payment_status    = 'unpaid'
         WHERE id = ?"
    );
    $upd->bind_param("si", $ref, $booking_id);
    $upd->execute();
    $upd->close();

    $adminId = 1;
    $note    = "Buyer selected Cash on Delivery. Payment due on delivery.";
    $track   = $connect->prepare(
        "INSERT INTO order_tracking (booking_id, status, note, updated_by) VALUES (?, 'pending', ?, ?)"
    );
    $track->bind_param("isi", $booking_id, $note, $adminId);
    $track->execute();
    $track->close();

    $connect->commit();

    respondOK("Cash on delivery selected. Please have exact cash ready on delivery.", [
        "booking_id"     => $booking_id,
        "payment_method" => "cod",
        "payment_status" => "unpaid",
        "total_amount"   => $booking['total_amount'],
        "message"        => "Pay ₦" . number_format($booking['total_amount'], 2) . " cash when your order arrives.",
    ]);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
