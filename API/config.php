<?php
// ======================
// JWT SETTINGS
// ======================
define('JWT_SECRET_KEY',     'f3b9a8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9'); // Change this in production
define('JWT_SERVER_NAME',    'POUND_FARMING_API');
define('JWT_EXPIRY_MINUTES', 480); // 8 hours - full work-day session

// ======================
// DATABASE SETTINGS
// ======================
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'pound_farming');

// online database = expeycwp_pound_farming
// online password = Jabatarnew123@
// ======================
// CORS SETTINGS
// ======================
// In production, replace '*' with your specific frontend domain e.g. 'https://yourapp.com'
define('CORS_ALLOWED_ORIGIN', 'https://jabman.online');

// ======================
// PAYSTACK SETTINGS
// ======================
define('PAYSTACK_SECRET_KEY',   'sk_test_REPLACE_WITH_YOUR_PAYSTACK_SECRET_KEY');
define('PAYSTACK_PUBLIC_KEY',   'pk_test_REPLACE_WITH_YOUR_PAYSTACK_PUBLIC_KEY');
define('PAYSTACK_CALLBACK_URL', 'https://jabman.online/pound_farming/frontend/payment-success.html');
define('PAYSTACK_BASE_URL',     'https://api.paystack.co');

// ======================
// FLUTTERWAVE SETTINGS
// ======================
define('FLW_SECRET_KEY',   'FLWSECK_TEST_REPLACE_WITH_YOUR_FLW_SECRET_KEY');
define('FLW_PUBLIC_KEY',   'FLWPUBK_TEST_REPLACE_WITH_YOUR_FLW_PUBLIC_KEY');
define('FLW_CALLBACK_URL', 'https://jabman.online/pound_farming/frontend/payment-success.html');
define('FLW_BASE_URL',     'https://api.flutterwave.com/v3');

// ======================
// BANK TRANSFER DETAILS
// ======================
define('BANK_NAME',          'First Bank Nigeria');
define('BANK_ACCOUNT_NUMBER','1234567890');
define('BANK_ACCOUNT_NAME',  'Oloyin Fresh Eggs');

// ======================
// CASH ON DELIVERY
// ======================
define('COD_ENABLED', true);