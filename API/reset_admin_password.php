<?php
/**
 * ONE-TIME admin password reset utility.
 * Upload to server, open in browser once, then DELETE immediately.
 *
 * Usage: open this file in your browser with:
 *   ?admin_id=YOUR_ADMIN_ID&new_password=YOUR_NEW_PASSWORD
 *
 * Example:
 *   https://yourdomain.com/API/reset_admin_password.php?admin_id=admin&new_password=Admin@123
 */

// ── Basic security: only runs with both params present ──────────
if (empty($_GET['admin_id']) || empty($_GET['new_password'])) {
    die(json_encode([
        "status" => false,
        "text"   => "Provide both ?admin_id=... and &new_password=... in the URL."
    ]));
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$admin_id    = trim($_GET['admin_id']);
$new_password = trim($_GET['new_password']);

if (strlen($new_password) < 6) {
    die(json_encode(["status" => false, "text" => "Password must be at least 6 characters."]));
}

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$connect) {
    die(json_encode(["status" => false, "text" => "DB connection failed."]));
}

// Check admin exists
$check = $connect->prepare("SELECT id FROM admin WHERE admin_id = ? LIMIT 1");
$check->bind_param("s", $admin_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $check->close();
    die(json_encode(["status" => false, "text" => "Admin ID not found."]));
}
$check->close();

// Hash and update
$hashed = Password_encrypt($new_password);

$stmt = $connect->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
$stmt->bind_param("ss", $hashed, $admin_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode([
        "status" => true,
        "text"   => "Password updated successfully. DELETE this file from your server now!"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "text"   => "Update failed or password was already the same hash."
    ]);
}
