<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['flock_id'], $_POST['available_crates'], $_POST['price_per_crate'])) {
    respondBadRequest("Invalid request. flock_id, available_crates and price_per_crate are required.");
}

$flock_id         = (int) trim($_POST['flock_id']);
$available_crates = (int) trim($_POST['available_crates']);
$price_per_crate  = (float) trim($_POST['price_per_crate']);
$description      = isset($_POST['description']) ? strip_tags(trim($_POST['description'])) : null;
$is_available     = isset($_POST['is_available']) ? (int) trim($_POST['is_available']) : 1;

if ($flock_id <= 0) {
    respondBadRequest("Invalid flock ID.");
} elseif ($available_crates <= 0) {
    respondBadRequest("Available crates must be a positive number.");
} elseif ($price_per_crate <= 0) {
    respondBadRequest("Price per crate must be a positive number.");
} else {

    $flockCheck = $connect->prepare("SELECT id FROM flock WHERE id = ? LIMIT 1");
    $flockCheck->bind_param("i", $flock_id);
    $flockCheck->execute();
    if ($flockCheck->get_result()->num_rows === 0) {
        $flockCheck->close();
        respondBadRequest("Flock not found.");
    }
    $flockCheck->close();

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("INSERT INTO egg_availability (flock_id, available_crates, price_per_crate, description, is_available) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsi", $flock_id, $available_crates, $price_per_crate, $description, $is_available);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $inserted_id = $connect->insert_id;
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT ea.id, ea.flock_id, fl.batch_number, fl.bird_type, ea.available_crates, ea.price_per_crate, ea.description, ea.is_available, ea.created_at FROM egg_availability ea JOIN flock fl ON fl.id = ea.flock_id WHERE ea.id = ?");
            $get->bind_param("i", $inserted_id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Egg availability added successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to add egg availability.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
