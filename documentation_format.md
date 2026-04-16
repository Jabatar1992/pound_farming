# API Structure Documentation Format
> Reusable pattern for building standard, secure PHP REST APIs.
> Based on a production-grade structure. Copy this into any new project.

---

## Table of Contents
1. [Project Folder Structure](#1-project-folder-structure)
2. [Core Files Overview](#2-core-files-overview)
3. [Configuration Setup](#3-configuration-setup)
4. [Database Connection](#4-database-connection)
5. [Standard Response Format](#5-standard-response-format)
6. [Authentication Flow (JWT)](#6-authentication-flow-jwt)
   - [Login Endpoint Pattern](#login-endpoint-pattern)
7. [Endpoint Code Pattern](#7-endpoint-code-pattern)
8. [File Upload Pattern](#8-file-upload-pattern)
9. [How to Create a New Endpoint](#9-how-to-create-a-new-endpoint)
10. [Security Checklist](#10-security-checklist)

---

## 1. Project Folder Structure

```
project-root/
├── vendor/                        # Composer dependencies (firebase/php-jwt etc.)
├── composer.json
├── composer.lock
├── documentation_format.md        # This file
│
└── api/
    ├── config.php                 # ALL app settings (JWT, DB, CORS) — single source of truth
    ├── connectdb.php              # DB connection using config constants
    ├── head.php                   # CORS headers + autoload + shared includes
    ├── functions.php              # Utility/helper functions (cleanme, input_is_invalid, etc.)
    ├── apifunctions.php           # HTTP response functions + JWT sign/verify
    │
    ├── admin/
    │   ├── auth/
    │   │   ├── login.php          # Admin login — issues JWT with role:'admin'
    │   │   └── logout.php
    │   ├── [resource]/
    │   │   ├── add_[resource].php
    │   │   ├── view_all_[resource].php
    │   │   ├── view_single_[resource].php
    │   │   ├── update_[resource].php
    │   │   └── delete_[resource].php
    │
    ├── user/
    │   ├── auth/
    │   │   ├── login.php          # User login — issues JWT with role:'user'
    │   │   └── logout.php
    │   └── [resource]/
    │       └── ...
    │
    └── uploads/                   # File upload destination
```

---

## 2. Core Files Overview

| File | Purpose |
|---|---|
| `config.php` | Defines all constants: JWT secret, DB credentials, CORS origin |
| `connectdb.php` | Connects to DB using config constants. Fails gracefully with JSON error |
| `head.php` | Sets HTTP headers (CORS, Content-Type, Cache). Loads autoload, functions, apifunctions |
| `functions.php` | `input_is_invalid()`, `cleanme()`, `Password_encrypt()`, `check_pass()`, etc. |
| `apifunctions.php` | `respondOK()`, `respondBadRequest()`, `respondUnauthorized()`, `getTokenToSendAPI()`, `ValidateAPITokenSentIN()` |

---

## 3. Configuration Setup

**`api/config.php`** — edit only this file when moving to a new project or environment.

```php
<?php
// ======================
// JWT SETTINGS
// ======================
define('JWT_SECRET_KEY',     'REPLACE_WITH_A_LONG_RANDOM_SECRET_STRING');
define('JWT_SERVER_NAME',    'YOUR_APP_API_SERVER');
define('JWT_EXPIRY_MINUTES', 60);

// ======================
// DATABASE SETTINGS
// ======================
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME',     'your_database_name');

// ======================
// CORS SETTINGS
// ======================
// Development:  '*'
// Production:   'https://yourfrontend.com'
define('CORS_ALLOWED_ORIGIN', '*');
```

> **Security note:** In production, never commit real credentials to git.
> Use environment variables and read them with `getenv('JWT_SECRET_KEY')`.

---

## 4. Database Connection

**`api/connectdb.php`**

```php
<?php
require_once __DIR__ . '/config.php';

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if (!$connect) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["status" => false, "text" => "Database connection failed.", "data" => []]);
    exit;
}
```

---

## 5. Standard Response Format

All endpoints return the same JSON structure regardless of success or failure.

### Success — HTTP 200
```json
{
    "status": true,
    "text": "Collector added successfully",
    "data": { ... },
    "time": "08-04-26 02:30:00PM",
    "method": "POST",
    "endpoint": "http://localhost/api/admin/collector/add_collector.php",
    "error": []
}
```

### Error — HTTP 400 / 401 / 403 / 404 / 500
```json
{
    "status": false,
    "text": "Phone must be 11 digits.",
    "data": [],
    "time": "08-04-26 02:30:00PM",
    "method": "POST",
    "endpoint": "http://localhost/api/admin/collector/add_collector.php",
    "error": {
        "code": "",
        "text": "The body request is not valid...",
        "link": "https://",
        "hint": [ "..." ]
    }
}
```

### Response Functions (from `apifunctions.php`)

| Function | HTTP Code | When to Use |
|---|---|---|
| `respondOK($data, $message)` | 200 | Successful operation |
| `respondBadRequest($message)` | 400 | Invalid input, failed validation |
| `respondUnauthorized()` | 401 | Missing or invalid token |
| `respondForbiddenAuthorized([])` | 403 | Valid token but wrong role |
| `respondNotFound($data)` | 404 | Resource not found |
| `respondMethodNotAlowed()` | 405 | Wrong HTTP method used |
| `respondInternalError($errText)` | 500 | Unexpected server/DB error |

---

## 6. Authentication Flow (JWT)

### How It Works

```
Client                          Server
  │                               │
  │── POST /admin/auth/login ────►│  Verify admin_id + password
  │                               │  getTokenToSendAPI($id, 'admin')
  │◄── { access_token: "eyJ..." } │  Token payload: { usertoken, role, exp, iss }
  │                               │
  │── POST /admin/[endpoint] ────►│  ValidateAPITokenSentIN('admin')
  │   Authorization: Bearer eyJ..│    → Decode token
  │                               │    → Check expiry, issuer
  │                               │    → Check role === 'admin'
  │◄── { status: true, data: {} } │  Proceed with business logic
```

### Issuing a Token (login endpoint)
```php
$accessToken = getTokenToSendAPI($userId, 'admin'); // or 'user'
respondOK(["access_token" => $accessToken], "Login successful.");
```

### Protecting an Endpoint (admin only)
```php
ValidateAPITokenSentIN('admin'); // exits with 401 or 403 automatically if invalid
```

### Protecting an Endpoint (any logged-in user)
```php
ValidateAPITokenSentIN('user');
```

### Token Payload Structure
```json
{
    "iat": 1712500000,
    "iss": "YOUR_APP_API_SERVER",
    "nbf": 1712500000,
    "exp": 1712503600,
    "usertoken": "1",
    "role": "admin"
}
```

### Login Endpoint Pattern

Login endpoints are **public** (no `ValidateAPITokenSentIN` call). They verify credentials, then issue a signed JWT.

#### Admin Login — `api/admin/auth/login.php`

```php
<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!isset($_POST['admin_id'], $_POST['password'])) {
    respondBadRequest("Invalid request. Admin ID and password are required.");
}

// Sanitize — do NOT sanitize password itself; password_verify() handles it
$admin_id = cleanme(trim($_POST['admin_id']));
$password = trim($_POST['password']);

if ($admin_id === '') {
    respondBadRequest("Admin ID is required.");
} elseif ($password === '') {
    respondBadRequest("Password is required.");
} else {

    $stmt = $connect->prepare("SELECT id, admin_id, name, password FROM admin WHERE admin_id = ? LIMIT 1");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        respondBadRequest("Invalid Admin ID or password.");
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $row['password'])) {
        respondBadRequest("Invalid Admin ID or password.");
    }

    $token = getTokenToSendAPI($row['id'], 'admin');

    respondOK("Login successful.", [
        "id"           => $row['id'],
        "admin_id"     => $row['admin_id'],
        "name"         => $row['name'],
        "access_token" => $token,
        "token_type"   => "Bearer",
    ]);
}
```

#### User Login — `api/user/auth/login.php`

```php
<?php
$method = "POST";
$cache  = "no-cache";
include "../../head.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

if (!isset($_POST['user_id'], $_POST['password'])) {
    respondBadRequest("Invalid request. User ID and password are required.");
}

$user_id  = cleanme(trim($_POST['user_id']));
$password = cleanme(trim($_POST['password']));

if (input_is_invalid($user_id) || input_is_invalid($password)) {
    respondBadRequest("User ID and password are required.");
} elseif (!is_numeric($user_id)) {
    respondBadRequest("User ID must be numeric.");
} else {

    $stmt = $connect->prepare("SELECT * FROM user WHERE id = ? AND password = ? LIMIT 1");
    $stmt->bind_param("is", $user_id, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        respondBadRequest("User not found.");
    }

    $token = getTokenToSendAPI($user_id, 'user');

    respondOK("Login successful.", [
        "access_token" => $token,
        "token_type"   => "Bearer",
    ]);
}
```

> **Key differences between admin and user login:**
>
> | | Admin | User |
> |---|---|---|
> | Role issued | `'admin'` | `'user'` |
> | Password check | `password_verify()` (bcrypt) | Plain comparison (migrate to bcrypt) |
> | Lookup field | `admin_id` (string) | `id` (integer) |
> | Returns | id, name, admin_id, token | token only |

> **Security note:** User login above uses a plain password comparison.
> Migrate to `password_hash()` / `password_verify()` following the same admin pattern.

---

## 7. Endpoint Code Pattern

Every endpoint follows this exact order. Do not change the order.

```php
<?php
// ── STEP 1: Declare method and cache before including head.php ──────────────
$method = "POST"; // GET | POST | PUT | DELETE
$cache  = "no-cache";
include "../../head.php"; // loads config, DB, helpers, JWT functions

// ── STEP 2: Enforce HTTP method ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondMethodNotAlowed();
}

// ── STEP 3: Authentication (remove if public endpoint) ──────────────────────
// For admin-only:  ValidateAPITokenSentIN('admin');
// For users only:  ValidateAPITokenSentIN('user');
// For any role:    ValidateAPITokenSentIN();
ValidateAPITokenSentIN('admin');

// ── STEP 4: Check required fields are present ────────────────────────────────
if (!isset($_POST['field_one'], $_POST['field_two'])) {
    respondBadRequest("Invalid request. Required fields missing.");
}

// ── STEP 5: Sanitize ─────────────────────────────────────────────────────────
$field_one = strip_tags(trim($_POST['field_one']));
$field_two = strip_tags(trim($_POST['field_two']));
$age       = (int) trim($_POST['age']); // cast numerics explicitly

// ── STEP 6: Validate ─────────────────────────────────────────────────────────
if (input_is_invalid($field_one) || input_is_invalid($field_two)) {
    respondBadRequest("All fields are required.");
} elseif ($age < 18) {
    respondBadRequest("Age must be at least 18.");
} else {

    // ── STEP 7: Duplicate / existence check ──────────────────────────────────
    $check = $connect->prepare("SELECT id FROM table_name WHERE column = ?");
    $check->bind_param("s", $field_one);
    $check->execute();
    $isDuplicate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isDuplicate) {
        respondBadRequest("Record already exists.");
    } else {

        // ── STEP 8: DB Transaction ────────────────────────────────────────────
        $connect->begin_transaction();
        try {

            $stmt = $connect->prepare("INSERT INTO table_name (col1, col2) VALUES (?, ?)");
            $stmt->bind_param("ss", $field_one, $field_two);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $inserted_id = $connect->insert_id;
                $connect->commit();
                $stmt->close();

                // ── STEP 9: Fetch and return the created record ───────────────
                $get = $connect->prepare("SELECT id, col1, col2 FROM table_name WHERE id = ?");
                $get->bind_param("i", $inserted_id);
                $get->execute();
                $data = $get->get_result()->fetch_assoc();
                $get->close();

                respondOK($data, "Record added successfully.");
            } else {
                $connect->rollback();
                $stmt->close();
                respondBadRequest("Failed to add record.");
            }

        } catch (Exception $e) {
            $connect->rollback();
            respondInternalError(get_details_from_exception($e));
        }
    }
}
?>
```

### bind_param Type Reference

| Character | Type | Example Field |
|---|---|---|
| `s` | string | name, phone, email |
| `i` | integer | id, age, count |
| `d` | double | price, balance |
| `b` | blob | binary data |

---

## 8. File Upload Pattern

Use this block whenever an endpoint accepts image/file uploads.

```php
// ── FILE VALIDATION (use finfo — never trust $_FILES['type']) ────────────────
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$maxSize      = 2 * 1024 * 1024; // 2MB
$finfo        = new finfo(FILEINFO_MIME_TYPE);

$realMime = $finfo->file($_FILES['photo']['tmp_name']);

if (!in_array($realMime, $allowedTypes)) {
    respondBadRequest("Only JPG and PNG images are allowed.");
} elseif ($_FILES['photo']['size'] > $maxSize) {
    respondBadRequest("File must not exceed 2MB.");
}

// ── UPLOAD ────────────────────────────────────────────────────────────────────
$uploadDir = "../../uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = time() . "_" . basename($_FILES['photo']['name']);

if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
    respondBadRequest("File upload failed.");
}

// ── ROLLBACK: if DB insert fails after upload ────────────────────────────────
// Inside the catch block:
// @unlink($uploadDir . $fileName);
```

> **Rule:** Always check `move_uploaded_file()` return value.
> Always `@unlink()` uploaded files inside the catch block if the DB insert fails.

---

## 9. How to Create a New Endpoint

Follow these steps every time you add a new endpoint.

**Step 1 — Create the file** in the correct folder:
```
api/admin/[resource]/add_[resource].php     ← admin endpoints
api/user/[resource]/view_[resource].php     ← user endpoints
```

**Step 2 — Use the endpoint template** from Section 7 as your starting point.

**Step 3 — Choose authentication level:**

| Who can access | Code |
|---|---|
| Admin only | `ValidateAPITokenSentIN('admin');` |
| Logged-in users | `ValidateAPITokenSentIN('user');` |
| Public (no token) | _(omit the line)_ |

**Step 4 — Checklist before marking endpoint as done:**

- [ ] Method check at the top
- [ ] Auth check (correct role)
- [ ] All fields sanitized with `strip_tags(trim())`
- [ ] Numeric fields cast to `(int)` or `(float)`
- [ ] `input_is_invalid()` called on all string inputs
- [ ] Prepared statements used for ALL queries
- [ ] Statements closed with `->close()` after use
- [ ] File MIME validated with `finfo`, not `$_FILES['type']`
- [ ] `move_uploaded_file()` return value checked
- [ ] DB writes wrapped in `begin_transaction()` / `commit()` / `rollback()`
- [ ] `@unlink()` called on uploaded files inside catch block
- [ ] Success response uses explicit column `SELECT`, not `SELECT *`
- [ ] All responses go through `respondOK()` or `respondBadRequest()` etc.

---

## 10. Security Checklist

Apply these rules across the entire project.

### JWT
- [ ] `JWT_SECRET_KEY` is long, random, and never committed to git
- [ ] `getTokenToSendAPI()` always receives the correct `$role` ('admin' or 'user')
- [ ] `ValidateAPITokenSentIN()` is called on every protected endpoint
- [ ] Token expiry (`JWT_EXPIRY_MINUTES`) is set to a reasonable value (60 min recommended)

### Database
- [ ] Only prepared statements used — no raw string interpolation in queries
- [ ] `bind_param` types match the actual data types
- [ ] Writes that depend on reads are wrapped in transactions

### Passwords
- [ ] Passwords stored as bcrypt hashes using `Password_encrypt()`
- [ ] Passwords verified using `check_pass()`
- [ ] Auto-migration: plaintext passwords are re-hashed on first successful login

### File Uploads
- [ ] MIME type verified with `finfo`, not `$_FILES['type']`
- [ ] File size capped (2MB default)
- [ ] Upload directory permissions set to `0755` (never `0777`)
- [ ] Uploaded files removed if the DB transaction fails

### HTTP
- [ ] Every endpoint checks `$_SERVER['REQUEST_METHOD']`
- [ ] CORS `CORS_ALLOWED_ORIGIN` set to specific domain in production (not `*`)
- [ ] No sensitive data returned in error messages to the client

### Code Hygiene
- [ ] No hardcoded credentials anywhere in code
- [ ] No `SELECT *` — always list explicit columns
- [ ] No commented-out dead code left in production files
- [ ] No `session_start()` in REST API endpoints (use JWT, not sessions)

---

*Documentation format based on OIS project API — April 2026.*
