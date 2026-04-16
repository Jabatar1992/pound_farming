<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

ValidateAPITokenSentIN('admin');

if (!isset($_POST['id'], $_POST['name'], $_POST['location'], $_POST['capacity'], $_POST['pen_type'])) {
    respondBadRequest("Invalid request. id, name, location, capacity and pen_type are required.");
}

$id       = (int) trim($_POST['id']);
$name     = strip_tags(trim($_POST['name']));
$location = strip_tags(trim($_POST['location']));
$capacity = (int) trim($_POST['capacity']);
$pen_type = strip_tags(trim($_POST['pen_type']));
$status   = isset($_POST['status']) ? strip_tags(trim($_POST['status'])) : 'active';

$validPenTypes = ['broiler', 'layer', 'turkey', 'duck', 'mixed'];
$validStatuses = ['active', 'inactive'];

if ($id <= 0) {
    respondBadRequest("Invalid farm ID.");
} elseif (input_is_invalid($name)) {
    respondBadRequest("Farm name is required.");
} elseif (input_is_invalid($location)) {
    respondBadRequest("Location is required.");
} elseif ($capacity <= 0) {
    respondBadRequest("Capacity must be a positive number.");
} elseif (!in_array($pen_type, $validPenTypes)) {
    respondBadRequest("pen_type must be one of: " . implode(', ', $validPenTypes) . ".");
} elseif (!in_array($status, $validStatuses)) {
    respondBadRequest("status must be active or inactive.");
} else {

    $exists = $connect->prepare("SELECT id FROM farm WHERE id = ? LIMIT 1");
    $exists->bind_param("i", $id);
    $exists->execute();
    $found = $exists->get_result()->num_rows > 0;
    $exists->close();

    if (!$found) {
        respondNotFound([]);
    }

    $connect->begin_transaction();
    try {

        $stmt = $connect->prepare("UPDATE farm SET name = ?, location = ?, capacity = ?, pen_type = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssissi", $name, $location, $capacity, $pen_type, $status, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $connect->commit();
            $stmt->close();

            $get = $connect->prepare("SELECT id, name, location, capacity, pen_type, status, created_at FROM farm WHERE id = ?");
            $get->bind_param("i", $id);
            $get->execute();
            $data = $get->get_result()->fetch_assoc();
            $get->close();

            respondOK("Farm updated successfully.", $data);
        } else {
            $connect->rollback();
            $stmt->close();
            respondBadRequest("Failed to update farm.");
        }

    } catch (Exception $e) {
        $connect->rollback();
        respondInternalError(get_details_from_exception($e));
    }
}
