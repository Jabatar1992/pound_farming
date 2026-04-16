<?php
require_once __DIR__ . '/config.php';

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if (!$connect) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => false, "text" => "Database connection failed.", "data" => []]);
    exit;
}
