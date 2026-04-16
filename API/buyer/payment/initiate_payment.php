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
if (!isset($_POST['payment_method'])) {
    respondBadRequest("Payment method is required.");
}

$booking_id     = (int) trim($_POST['booking_id']);
$payment_method = strtolower(trim($_POST['payment_method']));

if ($booking_id <= 0) {
    respondBadRequest("Invalid booking ID.");
}
if (!in_array($payment_method, ['paystack', 'flutterwave'])) {
    respondBadRequest("Invalid payment method. Use: paystack or flutterwave.");
}

$stmt = $connect->prepare(
    "SELECT eb.id, eb.total_amount, eb.payment_status, eb.order_status,
            b.email, b.name AS buyer_name, b.phone
     FROM egg_booking eb
     JOIN buyer b ON b.id = eb.buyer_id
     WHERE eb.id = ? AND eb.buyer_id = ? LIMIT 1"
);
$stmt->bind_param("ii", $booking_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
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

$reference = 'PF_' . $booking_id . '_' . time();

// ── PAYSTACK ────────────────────────────────────────────────────────────────
if ($payment_method === 'paystack') {
    $amountInKobo = (int) ($booking['total_amount'] * 100);
    $postData = json_encode([
        "email"        => $booking['email'],
        "amount"       => $amountInKobo,
        "reference"    => $reference,
        "callback_url" => PAYSTACK_CALLBACK_URL . '?gateway=paystack&booking_id=' . $booking_id . '&ref=' . $reference,
        "metadata"     => ["booking_id" => $booking_id, "buyer_id" => $buyer_id],
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            PAYSTACK_BASE_URL . '/transaction/initialize');
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) { respondInternalError("Paystack gateway unreachable."); }

    $gwRes = json_decode($response, true);
    if (!$gwRes['status']) {
        respondBadRequest("Paystack initialization failed: " . $gwRes['message']);
    }

    $payment_url = $gwRes['data']['authorization_url'];
    $access_code = $gwRes['data']['access_code'];
}

// ── FLUTTERWAVE ─────────────────────────────────────────────────────────────
if ($payment_method === 'flutterwave') {
    $postData = json_encode([
        "tx_ref"       => $reference,
        "amount"       => (float) $booking['total_amount'],
        "currency"     => "NGN",
        "redirect_url" => FLW_CALLBACK_URL . '?gateway=flutterwave&booking_id=' . $booking_id,
        "customer"     => [
            "email" => $booking['email'],
            "name"  => $booking['buyer_name'],
            "phone" => $booking['phone'],
        ],
        "meta"         => ["booking_id" => $booking_id, "buyer_id" => $buyer_id],
        "customizations" => [
            "title"       => "Oloyin-Fresh Eggs",
            "description" => "Payment for Egg Order #" . $booking_id,
        ],
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            FLW_BASE_URL . '/payments');
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'Authorization: Bearer ' . FLW_SECRET_KEY,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) { respondInternalError("Flutterwave gateway unreachable."); }

    $gwRes = json_decode($response, true);
    if ($gwRes['status'] !== 'success') {
        respondBadRequest("Flutterwave initialization failed: " . ($gwRes['message'] ?? 'Unknown error'));
    }

    $payment_url = $gwRes['data']['link'];
    $access_code = null;
}

// ── Save reference ───────────────────────────────────────────────────────────
$connect->begin_transaction();
try {
    $upd = $connect->prepare("UPDATE egg_booking SET payment_reference = ? WHERE id = ?");
    $upd->bind_param("si", $reference, $booking_id);
    $upd->execute();
    $upd->close();
    $connect->commit();

    $resp = [
        "booking_id"     => $booking_id,
        "reference"      => $reference,
        "amount"         => $booking['total_amount'],
        "payment_method" => $payment_method,
        "payment_url"    => $payment_url,
    ];
    if ($access_code) $resp['access_code'] = $access_code;

    respondOK("Payment initialized. Redirect buyer to payment_url.", $resp);

} catch (Exception $e) {
    $connect->rollback();
    respondInternalError(get_details_from_exception($e));
}
