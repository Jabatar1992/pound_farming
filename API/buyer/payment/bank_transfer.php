<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['booking_id']))          { respondBadRequest("Booking ID is required."); }
if (!isset($_POST['transfer_reference']))  { respondBadRequest("Transfer reference is required."); }
if (!isset($_POST['transfer_bank']))       { respondBadRequest("Sender bank name is required."); }

$booking_id         = (int) trim($_POST['booking_id']);
$transfer_reference = strip_tags(trim($_POST['transfer_reference']));
$transfer_bank      = strip_tags(trim($_POST['transfer_bank']));
$transfer_date      = isset($_POST['transfer_date']) ? strip_tags(trim($_POST['transfer_date'])) : date('Y-m-d');

if ($booking_id <= 0)              { respondBadRequest("Invalid booking ID."); }
if (input_is_invalid($transfer_reference)) { respondBadRequest("Transfer reference is required."); }

// Fetch booking
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

if ($booking['payment_status'] === 'paid')        { respondBadRequest("This booking is already paid."); }
if ($booking['order_status']   === 'cancelled')   { respondBadRequest("Cannot pay for a cancelled booking."); }

// Save transfer details — mark as pending_verification, admin confirms
$connect->begin_transaction();
try {
    $ref = 'BT_' . $booking_id . '_' . time();

    $upd = $connect->prepare(
        "UPDATE egg_booking
         SET payment_method    = 'bank_transfer',
             payment_reference = ?,
             payment_status    = 'pending_verification'
         WHERE id = ?"
    );
    $upd->bind_param("si", $ref, $booking_id);
    $upd->execute();
    $upd->close();

    $note  = "Bank transfer submitted. Ref: " . $transfer_reference
           . " | Bank: " . $transfer_bank
           . " | Date: " . $transfer_date
           . " | System ref: " . $ref;
    $adminId = 1;
    $track = $connect->prepare(
        "INSERT INTO order_tracking (booking_id, status, note, updated_by) VALUES (?, 'pending', ?, ?)"
    );
    $track->bind_param("isi", $booking_id, $note, $adminId);
    $track->execute();
    $track->close();

    $connect->commit();

    respondOK("Transfer details received. Your order will be confirmed once payment is verified by admin.", [
        "booking_id"         => $booking_id,
        "payment_method"     => "bank_transfer",
        "payment_status"     => "pending_verification",
        "transfer_reference" => $transfer_reference,
        "transfer_bank"      => $transfer_bank,
        "bank_name"          => BANK_NAME,
        "bank_account"       => BANK_ACCOUNT_NUMBER,
        "bank_account_name"  => BANK_ACCOUNT_NAME,
        "message"            => "Please allow 1-2 hours for confirmation.",
    ]);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
