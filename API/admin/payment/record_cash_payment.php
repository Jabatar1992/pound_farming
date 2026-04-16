<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token      = ValidateAPITokenSentIN('admin');
$admin_id   = (int) $token->usertoken;

if (!isset($_POST['booking_id'])) {
    respondBadRequest("booking_id is required.");
}

$booking_id = (int) trim($_POST['booking_id']);
$note       = isset($_POST['note']) ? strip_tags(trim($_POST['note'])) : 'Cash payment recorded by admin.';

if ($booking_id <= 0) {
    respondBadRequest("Invalid booking ID.");
}

// Fetch booking
$stmt = $connect->prepare(
    "SELECT eb.id, eb.buyer_id, eb.total_amount, eb.payment_status, eb.order_status,
            b.name AS buyer_name, b.email AS buyer_email, b.phone AS buyer_phone
     FROM egg_booking eb
     JOIN buyer b ON b.id = eb.buyer_id
     WHERE eb.id = ? LIMIT 1"
);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result  = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

$booking = $result->fetch_assoc();

if ($booking['payment_status'] === 'paid') {
    respondBadRequest("This booking has already been paid.");
}

if ($booking['order_status'] === 'cancelled') {
    respondBadRequest("Cannot pay for a cancelled booking.");
}

// Verify admin exists
$adminCheck = $connect->prepare("SELECT id, name FROM admin WHERE id = ? LIMIT 1");
$adminCheck->bind_param("i", $admin_id);
$adminCheck->execute();
$adminRow = $adminCheck->get_result()->fetch_assoc();
$adminCheck->close();

if (!$adminRow) {
    respondUnauthorized();
}

// Generate unique receipt number
$receipt_number = 'PF-' . date('Ymd') . '-' . $booking_id . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
$payment_date   = date('Y-m-d H:i:s');

$connect->begin_transaction();
try {

    $update = $connect->prepare(
        "UPDATE egg_booking
         SET payment_status  = 'paid',
             order_status    = 'confirmed',
             payment_method  = 'cash',
             payment_date    = ?,
             processed_by    = ?,
             receipt_number  = ?
         WHERE id = ?"
    );
    $update->bind_param("sisi", $payment_date, $admin_id, $receipt_number, $booking_id);
    $update->execute();
    $update->close();

    $track = $connect->prepare(
        "INSERT INTO order_tracking (booking_id, status, note, updated_by)
         VALUES (?, 'confirmed', ?, ?)"
    );
    $track->bind_param("isi", $booking_id, $note, $admin_id);
    $track->execute();
    $track->close();

    $connect->commit();

    // Return full receipt data
    $get = $connect->prepare(
        "SELECT eb.id AS booking_id,
                eb.receipt_number,
                b.name  AS buyer_name,
                b.email AS buyer_email,
                b.phone AS buyer_phone,
                b.address AS buyer_address,
                eb.quantity_crates,
                eb.unit_price,
                eb.total_amount,
                eb.delivery_address,
                eb.delivery_date,
                eb.order_status,
                eb.payment_status,
                eb.payment_method,
                eb.payment_date,
                eb.payment_reference,
                eb.created_at  AS booking_date,
                a.name         AS processed_by_name,
                a.email        AS processed_by_email
         FROM egg_booking eb
         JOIN buyer  b ON b.id = eb.buyer_id
         JOIN admin  a ON a.id = eb.processed_by
         WHERE eb.id = ?"
    );
    $get->bind_param("i", $booking_id);
    $get->execute();
    $receipt = $get->get_result()->fetch_assoc();
    $get->close();

    respondOK("Cash payment recorded successfully. Receipt generated.", $receipt);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
