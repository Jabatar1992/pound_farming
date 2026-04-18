<?php
/**
 * Run this LOCALLY on WAMP only — never upload to live server.
 * Open: http://localhost/my_project/pound_farming/generate_hash.php?p=YourPassword
 */
require_once __DIR__ . '/API/functions.php';

$pass = $_GET['p'] ?? '';
if (empty($pass)) {
    die('Add your password in the URL: ?p=YourPassword');
}

$hash = Password_encrypt($pass);

echo "<p><strong>Password:</strong> " . htmlspecialchars($pass) . "</p>";
echo "<p><strong>Hash:</strong> <code>" . $hash . "</code></p>";
echo "<hr>";
echo "<p>Run this SQL in phpMyAdmin (replace YOUR_ADMIN_ID):</p>";
echo "<pre>UPDATE admin SET password = '" . $hash . "' WHERE admin_id = 'YOUR_ADMIN_ID';</pre>";
