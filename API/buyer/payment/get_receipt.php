<?php
$method = "GET";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_GET['booking_id'])) {
    respondBadRequest("booking_id is required.");
}

$booking_id = (int) trim($_GET['booking_id']);

if ($booking_id <= 0) {
    respondBadRequest("Invalid booking ID.");
}

$stmt = $connect->prepare(
    "SELECT eb.id AS booking_id,
            eb.receipt_number,
            b.name    AS buyer_name,
            b.email   AS buyer_email,
            b.phone   AS buyer_phone,
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
            eb.created_at AS booking_date,
            CASE
                WHEN eb.payment_method = 'cash'   THEN a.name
                WHEN eb.payment_method = 'online' THEN 'Paystack Gateway'
                ELSE NULL
            END AS processed_by_name
     FROM egg_booking eb
     JOIN buyer b ON b.id = eb.buyer_id
     LEFT JOIN admin a ON a.id = eb.processed_by
     WHERE eb.id = ? AND eb.buyer_id = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $booking_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondNotFound([]);
}

$receipt = $result->fetch_assoc();

if ($receipt['payment_status'] !== 'paid') {
    respondBadRequest("No receipt available. This booking has not been paid.");
}

respondOK("Receipt retrieved successfully.", $receipt);
