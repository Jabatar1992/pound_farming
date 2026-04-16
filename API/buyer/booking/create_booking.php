<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

$token    = ValidateAPITokenSentIN('buyer');
$buyer_id = (int) $token->usertoken;

if (!isset($_POST['availability_id'], $_POST['quantity_crates'], $_POST['delivery_address'])) {
    respondBadRequest("Invalid request. availability_id, quantity_crates and delivery_address are required.");
}

$availability_id  = (int) trim($_POST['availability_id']);
$quantity_crates  = (int) trim($_POST['quantity_crates']);
$delivery_address = strip_tags(trim($_POST['delivery_address']));
$delivery_date    = isset($_POST['delivery_date']) ? strip_tags(trim($_POST['delivery_date'])) : null;
$notes            = isset($_POST['notes']) ? strip_tags(trim($_POST['notes'])) : null;

if ($availability_id <= 0) {
    respondBadRequest("Invalid availability ID.");
} elseif ($quantity_crates <= 0) {
    respondBadRequest("Quantity must be at least 1 crate.");
} elseif (input_is_invalid($delivery_address)) {
    respondBadRequest("Delivery address is required.");
} else {

    $avail = $connect->prepare("SELECT id, available_crates, price_per_crate, is_available FROM egg_availability WHERE id = ? LIMIT 1");
    $avail->bind_param("i", $availability_id);
    $avail->execute();
    $availResult = $avail->get_result();

    if ($availResult->num_rows === 0) {
        $avail->close();
        respondBadRequest("Egg listing not found.");
    }

    $listing = $availResult->fetch_assoc();
    $avail->close();

    if (!$listing['is_available']) {
        respondBadRequest("This egg listing is currently unavailable.");
    }

    if ($quantity_crates > $listing['available_crates']) {
        respondBadRequest("Only " . $listing['available_crates'] . " crate(s) available. Reduce your quantity.");
    }

    $unit_price    = (float) $listing['price_per_crate'];
    $total_amount  = $unit_price * $quantity_crates;

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO egg_booking (buyer_id, availability_id, quantity_crates, unit_price, total_amount, delivery_address, delivery_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiddsss", $buyer_id, $availability_id, $quantity_crates, $unit_price, $total_amount, $delivery_address, $delivery_date, $notes);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $booking_id = $connect->insert_id;

            $newAvail = $listing['available_crates'] - $quantity_crates;
            $updateAvail = $connect->prepare("UPDATE egg_availability SET available_crates = ?, is_available = ? WHERE id = ?");
            $isStillAvail = $newAvail > 0 ? 1 : 0;
            $updateAvail->bind_param("iii", $newAvail, $isStillAvail, $availability_id);
            $updateAvail->execute();
            $updateAvail->close();

            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT eb.id, eb.buyer_id, b.name AS buyer_name, eb.availability_id, fl.bird_type, ea.price_per_crate, eb.quantity_crates, eb.unit_price, eb.total_amount, eb.delivery_address, eb.delivery_date, eb.order_status, eb.payment_status, eb.notes, eb.created_at FROM egg_booking eb JOIN buyer b ON b.id = eb.buyer_id JOIN egg_availability ea ON ea.id = eb.availability_id JOIN flock fl ON fl.id = ea.flock_id WHERE eb.id = ?");
            $get->bind_param("i", $booking_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Booking created successfully. Proceed to payment.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to create booking.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
