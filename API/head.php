<?php
require_once __DIR__ . '/config.php';

// Use values set by the endpoint, or fall back to safe defaults
$allowedMethod = isset($method) ? strtoupper($method) : 'GET';
$cacheControl  = isset($cache)  ? $cache  : 'no-cache';

header("Access-Control-Allow-Origin: "  . CORS_ALLOWED_ORIGIN);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: " . $allowedMethod);
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization");
header("Cache-Control: "                . $cacheControl);
header("Access-Control-Max-Age: 3600");

require_once __DIR__ . '/../vendor/autoload.php';

include 'functions.php';
include 'apifunctions.php';


