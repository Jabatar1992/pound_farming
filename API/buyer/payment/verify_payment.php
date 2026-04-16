<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

// Detect gateway from params
$gateway        = isset($_POST['gateway']) ? strtolower(trim($_POST['gateway'])) : '';
$reference      = isset($_POST['reference'])      ? trim($_POST['reference'])      : '';
$transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
$tx_ref         = isset($_POST['tx_ref'])         ? trim($_POST['tx_ref'])         : '';

// Auto-detect if not explicit
if (!$gateway) {
    $gateway = $transaction_id ? 'flutterwave' : 'paystack';
}

if (!in_array($gateway, ['paystack', 'flutterwave'])) {
    respondBadRequest("Invalid gateway.");
}

// ── PAYSTACK VERIFICATION ────────────────────────────────────────────────────
if ($gateway === 'paystack') {
    if (!$reference) { respondBadRequest("Payment reference is required."); }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            PAYSTACK_BASE_URL . '/transaction/verify/' . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) { respondInternalError("Paystack gateway unreachable."); }

    $gwRes = json_decode($response, true);
    if (!$gwRes['status']) {
        respondBadRequest("Paystack verification failed: " . $gwRes['message']);
    }

    $txData = $gwRes['data'];
    if ($txData['status'] !== 'success') {
        respondBadRequest("Payment not successful. Status: " . $txData['status']);
    }

    $paidAmount = $txData['amount'] / 100;
    $lookup_ref = $reference;
    $lookup_col = 'payment_reference';
    $gateway_label = 'Paystack';
}

// ── FLUTTERWAVE VERIFICATION ─────────────────────────────────────────────────
if ($gateway === 'flutterwave') {
    if (!$transaction_id) { respondBadRequest("Transaction ID is required."); }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            FLW_BASE_URL . '/transactions/' . rawurlencode($transaction_id) . '/verify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Authorization: Bearer ' . FLW_SECRET_KEY]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) { respondInternalError("Flutterwave gateway unreachable."); }

    $gwRes = json_decode($response, true);
    if ($gwRes['status'] !== 'success') {
        respondBadRequest("Flutterwave verification failed: " . ($gwRes['message'] ?? 'Unknown error'));
    }

    $txData = $gwRes['data'];
    if ($txData['status'] !== 'successful') {
        respondBadRequest("Payment not successful. Status: " . $txData['status']);
    }
    if (strtoupper($txData['currency']) !== 'NGN') {
        respondBadRequest("Invalid currency.");
    }

    $paidAmount = (float) $txData['amount'];
    $lookup_ref = $tx_ref ?: $txData['tx_ref'];
    $lookup_col = 'payment_reference';
    $gateway_label = 'Flutterwave';
}

// ── Find booking ─────────────────────────────────────────────────────────────
$stmt = $connect->prepare(
    "SELECT id, buyer_id, total_amount, payment_status, order_status
     FROM egg_booking WHERE payment_reference = ? LIMIT 1"
);
$stmt->bind_param("s", $lookup_ref);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respondBadRequest("No booking found for this payment reference.");
}

$booking = $result->fetch_assoc();

if ($booking['payment_status'] === 'paid') {
    respondOK("Payment already recorded.", ["booking_id" => $booking['id'], "payment_status" => "paid"]);
}

if ((float) $paidAmount < (float) $booking['total_amount']) {
    respondBadRequest("Amount paid (" . $paidAmount . ") is less than booking total (" . $booking['total_amount'] . ").");
}

// ── Update booking ────────────────────────────────────────────────────────────
$receipt_number = 'PF-' . date('Ymd') . '-' . $booking['id'] . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
$payment_date   = date('Y-m-d H:i:s');

$connect->begin_transaction();
try {
    $upd = $connect->prepare(
        "UPDATE egg_booking
         SET payment_status  = 'paid',
             order_status    = 'confirmed',
             payment_method  = ?,
             payment_date    = ?,
             receipt_number  = ?
         WHERE id = ?"
    );
    $pm = strtolower($gateway_label);
    $upd->bind_param("sssi", $pm, $payment_date, $receipt_number, $booking['id']);
    $upd->execute();
    $upd->close();

    $adminId = 1;
    $note    = "Payment confirmed via " . $gateway_label . ". Reference: " . $lookup_ref;
    $track   = $connect->prepare(
        "INSERT INTO order_tracking (booking_id, status, note, updated_by) VALUES (?, 'confirmed', ?, ?)"
    );
    $track->bind_param("isi", $booking['id'], $note, $adminId);
    $track->execute();
    $track->close();

    $connect->commit();

    $get = $connect->prepare(
        "SELECT eb.id AS booking_id, eb.receipt_number,
                b.name AS buyer_name, b.email AS buyer_email, b.phone AS buyer_phone,
                eb.quantity_crates, eb.unit_price, eb.total_amount,
                eb.delivery_address, eb.delivery_date,
                eb.order_status, eb.payment_status, eb.payment_method,
                eb.payment_date, eb.payment_reference, eb.created_at AS booking_date
         FROM egg_booking eb
         JOIN buyer b ON b.id = eb.buyer_id
         WHERE eb.id = ?"
    );
    $get->bind_param("i", $booking['id']);
    $get->execute();
    $data = $get->get_result()->fetch_assoc();
    $get->close();

    respondOK("Payment verified successfully. Order confirmed.", $data);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
