<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['available_crates'], $_POST['price_per_crate'], $_POST['is_available'])) {
    respondBadRequest("Invalid request. id, available_crates, price_per_crate and is_available are required.");
}

$id               = (int) trim($_POST['id']);
$available_crates = (int) trim($_POST['available_crates']);
$price_per_crate  = (float) trim($_POST['price_per_crate']);
$is_available     = (int) trim($_POST['is_available']);
$description      = isset($_POST['description']) ? strip_tags(trim($_POST['description'])) : null;

if ($id <= 0) {
    respondBadRequest("Invalid availability ID.");
} elseif ($available_crates < 0) {
    respondBadRequest("Available crates cannot be negative.");
} elseif ($price_per_crate <= 0) {
    respondBadRequest("Price per crate must be a positive number.");
} elseif (!in_array($is_available, [0, 1])) {
    respondBadRequest("is_available must be 0 or 1.");
} else {

    $exists = $connect->prepare("SELECT id FROM egg_availability WHERE id = ? LIMIT 1");
    $exists->bind_param("i", $id);
    $exists->execute();
    if ($exists->get_result()->num_rows === 0) {
        $exists->close();
        respondNotFound([]);
    }
    $exists->close();

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("UPDATE egg_availability SET available_crates = ?, price_per_crate = ?, description = ?, is_available = ? WHERE id = ?");
        $stmt->bind_param("idsii", $available_crates, $price_per_crate, $description, $is_available, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT ea.id, ea.flock_id, fl.batch_number, fl.bird_type, fm.name AS farm_name, ea.available_crates, ea.price_per_crate, ea.description, ea.is_available, ea.created_at FROM egg_availability ea JOIN flock fl ON fl.id = ea.flock_id JOIN farm fm ON fm.id = fl.farm_id WHERE ea.id = ?");
            $get->bind_param("i", $id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Egg availability updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update egg availability.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
