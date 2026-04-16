# Poultry Farming System — Project Description
> Full API reference and system documentation.
> Database: `pound_farming` | Backend: PHP + MySQL | Auth: JWT (firebase/php-jwt) | Payment: Paystack

---

## Table of Contents
1. [System Overview](#1-system-overview)
2. [Folder Structure](#2-folder-structure)
3. [Setup & Configuration](#3-setup--configuration)
4. [Database Schema](#4-database-schema)
5. [Authentication Guide](#5-authentication-guide)
6. [Admin APIs](#6-admin-apis)
7. [Worker (User) APIs](#7-worker-user-apis)
8. [Buyer APIs](#8-buyer-apis)
9. [Egg Booking Flow](#9-egg-booking-flow)
10. [Payment Integration (Paystack)](#10-payment-integration-paystack)

---

## 1. System Overview

The Poultry Farming System manages the full lifecycle of a poultry farm — from bird housing and feed management to egg production, mortality tracking, and direct-to-buyer egg sales with online payment.

### Roles

| Role    | Token Role | Description |
|---------|-----------|-------------|
| Admin   | `admin`   | Farm owner / manager — full CRUD on all resources |
| Worker  | `user`    | Farm worker — records daily operations (feed, eggs, mortality) |
| Buyer   | `buyer`   | End customer — books eggs online and pays via Paystack |

---

## 2. Folder Structure

```
pound_farming/
├── vendor/                         # Composer deps (firebase/php-jwt)
├── composer.json / composer.lock
├── database.sql                    # Full MySQL schema
├── documentation_format.md         # PHP API coding standard
├── frontend/                       # Buyer-facing HTML/CSS/JS app
│   ├── index.html                  # Main SPA (all buyer views)
│   └── payment-success.html        # Paystack payment callback page
│
└── API/
    ├── config.php                  # JWT, DB, CORS, Paystack constants
    ├── connectdb.php               # MySQLi connection
    ├── head.php                    # CORS headers + autoload + helpers
    ├── functions.php               # Utilities: cleanme, input_is_invalid, Password_encrypt …
    ├── apifunctions.php            # HTTP helpers: respondOK, respondBadRequest, JWT sign/verify
    │
    ├── admin/
    │   ├── auth/           login.php, create_admin.php
    │   ├── farm/           add, view_all, view_single, update, delete
    │   ├── flock/          add, view_all, view_single, update, delete
    │   ├── feed/           add, view_all, view_single, update, delete
    │   ├── feed_consumption/  add, view_all
    │   ├── health_record/  add, view_all, view_single
    │   ├── egg_production/ add, view_all, view_single
    │   ├── mortality/      add, view_all
    │   ├── expense/        add, view_all, view_single
    │   ├── sales/          add, view_all, view_single
    │   ├── worker/         add, view_all, view_single, update, delete
    │   ├── availability/   add, view_all, update, delete
    │   └── booking/        view_all_bookings, view_single_booking, update_booking_status
    │
    ├── user/  (worker role)
    │   ├── auth/           login.php
    │   ├── flock/          view_all, view_single
    │   ├── feed_consumption/  add, view_all
    │   ├── egg_production/ add, view_all
    │   └── mortality/      add, view_all
    │
    └── buyer/
        ├── auth/           register.php, login.php
        ├── availability/   view_availability.php  (public)
        ├── booking/        create_booking, view_my_bookings, view_single_booking, cancel_booking
        └── payment/        initiate_payment.php, verify_payment.php
```

---

## 3. Setup & Configuration

**Edit `API/config.php` only — single source of truth.**

```php
// JWT
define('JWT_SECRET_KEY',     'CHANGE_THIS_TO_A_LONG_RANDOM_STRING');
define('JWT_SERVER_NAME',    'POUND_FARMING_API');
define('JWT_EXPIRY_MINUTES', 60);

// Database
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'pound_farming');

// CORS — set to your frontend domain in production
define('CORS_ALLOWED_ORIGIN', '*');

// Paystack — from https://dashboard.paystack.com/#/settings/developers
define('PAYSTACK_SECRET_KEY',   'sk_test_YOUR_SECRET_KEY');
define('PAYSTACK_PUBLIC_KEY',   'pk_test_YOUR_PUBLIC_KEY');
define('PAYSTACK_CALLBACK_URL', 'http://localhost/my_project/pound_farming/frontend/payment-success.html');
define('PAYSTACK_BASE_URL',     'https://api.paystack.co');
```

**Database setup:** Import `database.sql` into MySQL to create all 15 tables.

---

## 4. Database Schema

| # | Table              | Description |
|---|--------------------|-------------|
| 1 | `admin`            | Farm administrators |
| 2 | `worker`           | Farm workers (user role) |
| 3 | `farm`             | Pen / housing units |
| 4 | `flock`            | Batch of birds per farm |
| 5 | `feed`             | Feed inventory |
| 6 | `feed_consumption` | Daily feeding log |
| 7 | `health_record`    | Vaccinations / treatments |
| 8 | `egg_production`   | Daily egg collection |
| 9 | `mortality`        | Bird death records |
| 10 | `sale`            | Bird / egg sales (offline) |
| 11 | `expense`         | Farm expenses |
| 12 | `buyer`           | Registered egg buyers |
| 13 | `egg_availability` | Admin-published egg listings |
| 14 | `egg_booking`     | Buyer orders |
| 15 | `order_tracking`  | Admin status update history |

---

## 5. Authentication Guide

All protected endpoints require a Bearer JWT in the `Authorization` header.

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Issuing tokens (login endpoints)
```php
$token = getTokenToSendAPI($userId, 'admin');  // or 'user' or 'buyer'
```

### Protecting endpoints
```php
ValidateAPITokenSentIN('admin');   // admin only
ValidateAPITokenSentIN('user');    // workers only
ValidateAPITokenSentIN('buyer');   // buyers only
```

### Token lifetime
60 minutes (configurable via `JWT_EXPIRY_MINUTES`).

---

## 6. Admin APIs

Base path: `API/admin/`

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/auth/login.php` | Public | Login, receive JWT |
| POST | `admin/auth/create_admin.php` | Public | Create admin account |

**Login request:** `admin_id`, `password`
**Login response:** `id`, `admin_id`, `name`, `access_token`, `token_type`

---

### Farm

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/farm/add_farm.php` | Admin | Create a farm/pen |
| GET  | `admin/farm/view_all_farms.php` | Admin | List all farms |
| GET  | `admin/farm/view_single_farm.php?id=` | Admin | Single farm |
| POST | `admin/farm/update_farm.php` | Admin | Update farm |
| POST | `admin/farm/delete_farm.php` | Admin | Delete farm |

**Add/Update fields:** `name`, `location`, `capacity`, `pen_type` (`broiler|layer|turkey|duck|mixed`), `status`

---

### Flock

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/flock/add_flock.php` | Admin | Add bird batch |
| GET  | `admin/flock/view_all_flocks.php` | Admin | List all flocks |
| GET  | `admin/flock/view_single_flock.php?id=` | Admin | Single flock |
| POST | `admin/flock/update_flock.php` | Admin | Update flock |
| POST | `admin/flock/delete_flock.php` | Admin | Delete flock |

**Add fields:** `farm_id`, `batch_number`, `bird_type`, `initial_count`, `date_stocked`, `age_weeks`, `notes`

---

### Feed

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/feed/add_feed.php` | Admin | Add feed to inventory |
| GET  | `admin/feed/view_all_feeds.php` | Admin | List all feed |
| GET  | `admin/feed/view_single_feed.php?id=` | Admin | Single feed entry |
| POST | `admin/feed/update_feed.php` | Admin | Update feed |
| POST | `admin/feed/delete_feed.php` | Admin | Delete feed |

**Add fields:** `name`, `feed_type`, `quantity_kg`, `unit_price`, `supplier`, `purchase_date`

---

### Feed Consumption

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/feed_consumption/add_feed_consumption.php` | Admin | Log feed usage |
| GET  | `admin/feed_consumption/view_all_feed_consumption.php` | Admin | All consumption logs |

**Add fields:** `flock_id`, `feed_id`, `quantity_kg`, `consumption_date`, `notes`

---

### Egg Production

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/egg_production/add_egg_production.php` | Admin | Log egg collection |
| GET  | `admin/egg_production/view_all_egg_production.php` | Admin | All production logs |
| GET  | `admin/egg_production/view_single_egg_production.php?id=` | Admin | Single log |

**Add fields:** `flock_id`, `eggs_collected`, `broken_eggs`, `collection_date`, `notes`

---

### Health Record

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/health_record/add_health_record.php` | Admin | Add health event |
| GET  | `admin/health_record/view_all_health_records.php` | Admin | All health records |
| GET  | `admin/health_record/view_single_health_record.php?id=` | Admin | Single record |

**Add fields:** `flock_id`, `record_type`, `description`, `medication`, `dosage`, `administered_by`, `record_date`, `next_due_date`

---

### Mortality

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/mortality/add_mortality.php` | Admin | Log bird deaths |
| GET  | `admin/mortality/view_all_mortality.php` | Admin | All mortality records |

**Add fields:** `flock_id`, `count`, `cause`, `mortality_date`, `notes`

---

### Sales (Offline)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/sales/add_sale.php` | Admin | Record offline sale |
| GET  | `admin/sales/view_all_sales.php` | Admin | All sales |
| GET  | `admin/sales/view_single_sale.php?id=` | Admin | Single sale |

**Add fields:** `flock_id`, `sale_type`, `quantity`, `unit_price`, `buyer_name`, `buyer_phone`, `sale_date`

---

### Expense

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/expense/add_expense.php` | Admin | Record expense |
| GET  | `admin/expense/view_all_expenses.php` | Admin | All expenses |
| GET  | `admin/expense/view_single_expense.php?id=` | Admin | Single expense |

**Add fields:** `category`, `description`, `amount`, `expense_date`

---

### Worker Management

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/worker/add_worker.php` | Admin | Create worker account |
| GET  | `admin/worker/view_all_workers.php` | Admin | All workers |
| GET  | `admin/worker/view_single_worker.php?id=` | Admin | Single worker |
| POST | `admin/worker/update_worker.php` | Admin | Update worker |
| POST | `admin/worker/delete_worker.php` | Admin | Delete worker |

**Add fields:** `name`, `phone`, `email`, `password`, `role` (`worker|supervisor`)

---

### Egg Availability (Listings)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `admin/availability/add_availability.php` | Admin | Publish egg listing |
| GET  | `admin/availability/view_all_availability.php` | Admin | All listings |
| POST | `admin/availability/update_availability.php` | Admin | Update listing |
| POST | `admin/availability/delete_availability.php` | Admin | Remove listing |

**Add fields:** `flock_id`, `available_crates`, `price_per_crate`, `description`, `is_available`

> 1 crate = 30 eggs. `available_crates` decreases automatically when a buyer places a booking.

---

### Booking Management (Admin)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET  | `admin/booking/view_all_bookings.php` | Admin | All buyer orders |
| GET  | `admin/booking/view_single_booking.php?id=` | Admin | Single order + tracking |
| POST | `admin/booking/update_booking_status.php` | Admin | Update order status |

**update_booking_status fields:** `booking_id`, `order_status` (`pending|confirmed|paid|dispatched|delivered|cancelled`), `note`

> Each status update is recorded in `order_tracking` with the admin's ID, timestamp, and note.

---

## 7. Worker (User) APIs

Base path: `API/user/`

Workers use role `user`. They log daily operations using their JWT.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `user/auth/login.php` | Public | Worker login |
| GET  | `user/flock/view_all_flocks.php` | User | View all flocks |
| GET  | `user/flock/view_single_flock.php?id=` | User | Single flock |
| POST | `user/feed_consumption/add_feed_consumption.php` | User | Log daily feed |
| GET  | `user/feed_consumption/view_all_feed_consumption.php` | User | All feed logs |
| POST | `user/egg_production/add_egg_production.php` | User | Log egg collection |
| GET  | `user/egg_production/view_all_egg_production.php` | User | All production logs |
| POST | `user/mortality/add_mortality.php` | User | Log bird deaths |
| GET  | `user/mortality/view_all_mortality.php` | User | All mortality records |

---

## 8. Buyer APIs

Base path: `API/buyer/`

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `buyer/auth/register.php` | Public | Register a buyer account |
| POST | `buyer/auth/login.php` | Public | Login, receive JWT |

**Register request:** `name`, `phone`, `email`, `password`, `address` (optional)
**Register response:** buyer profile + `access_token`

**Login request:** `email`, `password`
**Login response:** `id`, `name`, `phone`, `email`, `address`, `access_token`, `token_type`

---

### Egg Availability

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `buyer/availability/view_availability.php` | **Public** | Browse active egg listings |

**Response per listing:**
```json
{
  "id": 1,
  "bird_type": "layer",
  "farm_name": "Block A",
  "available_crates": 50,
  "price_per_crate": 2500.00,
  "eggs_per_crate": 30,
  "total_eggs": 1500,
  "description": "Fresh grade-A eggs",
  "created_at": "2026-04-15 10:00:00"
}
```

---

### Booking

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `buyer/booking/create_booking.php` | Buyer | Place an egg order |
| GET  | `buyer/booking/view_my_bookings.php` | Buyer | My orders |
| GET  | `buyer/booking/view_single_booking.php?id=` | Buyer | Order detail + tracking |
| POST | `buyer/booking/cancel_booking.php` | Buyer | Cancel pending order |

**create_booking request:**
```
availability_id  (int)   — from egg listing
quantity_crates  (int)   — how many crates to order
delivery_address (string)
delivery_date    (date, optional) — YYYY-MM-DD
notes            (string, optional)
```

> Booking is created with `order_status: pending`, `payment_status: unpaid`.
> `available_crates` in the listing is decremented immediately to reserve stock.
> If booking is cancelled, stock is restored.

**view_single_booking response includes `tracking_timeline`:**
```json
{
  "id": 1,
  "order_status": "confirmed",
  "payment_status": "paid",
  "tracking_timeline": [
    { "status": "confirmed", "note": "Payment confirmed via Paystack.", "created_at": "..." },
    { "status": "dispatched", "note": "Driver: Emeka, truck ABC123", "created_at": "..." }
  ]
}
```

---

### Payment

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `buyer/payment/initiate_payment.php` | Buyer | Start Paystack checkout |
| POST | `buyer/payment/verify_payment.php` | Public | Verify payment after redirect |

**initiate_payment request:** `booking_id`
**initiate_payment response:**
```json
{
  "booking_id": 5,
  "reference": "PF_5_1712500000",
  "amount": 5000.00,
  "payment_url": "https://checkout.paystack.com/XXXXX",
  "access_code": "XXXXX"
}
```
> Redirect the buyer's browser to `payment_url`.

**verify_payment request:** `reference`
**verify_payment response:** booking detail with `payment_status: paid`, `order_status: confirmed`

---

## 9. Egg Booking Flow

```
1. Buyer registers / logs in
        │
2. Browser egg listings (public, no auth)
        │
3. Select a listing → fill booking form
   (quantity_crates, delivery_address, delivery_date)
        │
4. POST /buyer/booking/create_booking.php
   → booking created (pending / unpaid)
   → stock reserved
        │
5. POST /buyer/payment/initiate_payment.php
   → Paystack payment URL returned
        │
6. Redirect buyer to Paystack checkout
        │
7. Buyer pays on Paystack
        │
8. Paystack redirects to PAYSTACK_CALLBACK_URL
   ?booking_id=X&ref=PF_X_TIMESTAMP
        │
9. POST /buyer/payment/verify_payment.php  { reference }
   → amount verified against booking total
   → egg_booking.payment_status = 'paid'
   → egg_booking.order_status   = 'confirmed'
   → order_tracking entry created
        │
10. Admin updates status: confirmed → dispatched → delivered
    POST /admin/booking/update_booking_status.php
    → each update appended to order_tracking
        │
11. Buyer views tracking timeline in
    GET /buyer/booking/view_single_booking.php?id=X
```

---

## 10. Payment Integration (Paystack)

### Keys
- Get test/live keys from [Paystack Dashboard](https://dashboard.paystack.com/#/settings/developers)
- Set in `API/config.php`: `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`

### Callback URL
Set `PAYSTACK_CALLBACK_URL` to your frontend payment-success page:
```
http://yourdomain.com/pound_farming/frontend/payment-success.html
```
Paystack appends `?booking_id=X&ref=REFERENCE` when redirecting back.

### Amount
Paystack accepts amounts in **kobo** (₦1 = 100 kobo).
The API converts automatically: `$amountInKobo = (int)($booking['total_amount'] * 100)`

### Idempotency
`verify_payment.php` checks `payment_status === 'paid'` before processing. Double-verifying returns `200 OK` without re-processing.

### Amount mismatch guard
If Paystack reports a paid amount less than the booking total, verification is rejected with `400 Bad Request`.

---

*Poultry Farming System — April 2026*
