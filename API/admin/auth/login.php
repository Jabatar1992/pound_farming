<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

// ======================
// METHOD CHECK
// ======================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

// ======================
// INPUT CHECK
// ======================
if (isset($_POST['admin_id'], $_POST['password'])) {

    // ======================
    // SANITIZE INPUT
    // ======================
    $admin_id = cleanme(trim($_POST['admin_id']));
    $password = cleanme(trim($_POST['password']));

    // ======================
    // VALIDATION
    // ======================
    if ($admin_id === '') {
        respondBadRequest("Admin ID is required.");
    } elseif ($password === '') {
        respondBadRequest("Password is required.");
    } else {

        // ======================
        // CHECK ADMIN
        // ======================
        $stmt = $connect->prepare("SELECT id, admin_id, name, email, password FROM admin WHERE admin_id = ? LIMIT 1");
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            respondBadRequest("Invalid Admin ID or password.");
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        // ======================
        // PASSWORD CHECK
        // Supports both bcrypt hashes (created via API) and
        // plain-text passwords (inserted directly into the DB).
        // ======================
        $storedPassword = $row['password'];
        $isHashed       = substr($storedPassword, 0, 4) === '$2y$';
        $passwordValid  = $isHashed
            ? check_pass($password, $storedPassword)
            : ($password === $storedPassword);

        if (!$passwordValid) {
            respondBadRequest("Invalid Admin ID or password.");
        }

        // ======================
        // GENERATE TOKEN
        // ======================
        $token = getTokenToSendAPI($row['id'], 'admin');

        // ======================
        // SUCCESS RESPONSE
        // ======================
        respondOK("Login successful.", [
            "id"           => $row['id'],
            "admin_id"     => $row['admin_id'],
            "name"         => $row['name'],
            "email"        => $row['email'],
            "access_token" => $token,
            "token_type"   => "Bearer",
        ]);
    }

} else {
    respondBadRequest("Invalid request. Admin ID and password are required.");
}