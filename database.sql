-- ============================================================
-- Poultry Farming System — Database Schema
-- Database: pound_farming
-- Created: April 2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS pound_farming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pound_farming;

-- ─────────────────────────────────────────────────────────────
-- 1. ADMIN
-- ─────────────────────────────────────────────────────────────
CREATE TABLE admin (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    VARCHAR(50)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,  -- bcrypt hash
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 2. WORKER  (user role)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE worker (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    phone       VARCHAR(15)  NOT NULL UNIQUE,
    email       VARCHAR(150)          DEFAULT NULL,
    password    VARCHAR(255) NOT NULL,  -- bcrypt hash
    role        ENUM('worker','supervisor') NOT NULL DEFAULT 'worker',
    status      ENUM('active','inactive')  NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 3. FARM  (pen / housing unit)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE farm (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    location    VARCHAR(200) NOT NULL,
    capacity    INT UNSIGNED NOT NULL DEFAULT 0,
    pen_type    ENUM('broiler','layer','turkey','duck','mixed') NOT NULL DEFAULT 'broiler',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 4. FLOCK  (batch of birds per farm)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE flock (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id         INT UNSIGNED NOT NULL,
    batch_number    VARCHAR(50)  NOT NULL UNIQUE,
    bird_type       ENUM('broiler','layer','turkey','duck','cockerel','other') NOT NULL DEFAULT 'broiler',
    initial_count   INT UNSIGNED NOT NULL DEFAULT 0,
    current_count   INT UNSIGNED NOT NULL DEFAULT 0,
    date_stocked    DATE         NOT NULL,
    age_weeks       INT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('active','sold','closed') NOT NULL DEFAULT 'active',
    notes           TEXT                  DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_flock_farm FOREIGN KEY (farm_id) REFERENCES farm(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 5. FEED  (feed inventory)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE feed (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    feed_type       ENUM('starter','grower','finisher','layer','broiler','supplement','other') NOT NULL DEFAULT 'starter',
    quantity_kg     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier        VARCHAR(150)  DEFAULT NULL,
    purchase_date   DATE          NOT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 6. FEED CONSUMPTION  (daily feeding log)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE feed_consumption (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flock_id            INT UNSIGNED  NOT NULL,
    feed_id             INT UNSIGNED  NOT NULL,
    quantity_kg         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    consumption_date    DATE          NOT NULL,
    recorded_by         INT UNSIGNED  NOT NULL,  -- worker.id
    notes               TEXT          DEFAULT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fc_flock  FOREIGN KEY (flock_id)    REFERENCES flock(id),
    CONSTRAINT fk_fc_feed   FOREIGN KEY (feed_id)     REFERENCES feed(id),
    CONSTRAINT fk_fc_worker FOREIGN KEY (recorded_by) REFERENCES worker(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 7. HEALTH RECORD  (vaccination / treatment / checkup)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE health_record (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flock_id        INT UNSIGNED  NOT NULL,
    record_type     ENUM('vaccination','treatment','checkup','deworming','other') NOT NULL DEFAULT 'checkup',
    description     TEXT          NOT NULL,
    medication      VARCHAR(150)  DEFAULT NULL,
    dosage          VARCHAR(100)  DEFAULT NULL,
    administered_by VARCHAR(100)  DEFAULT NULL,
    record_date     DATE          NOT NULL,
    next_due_date   DATE          DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hr_flock FOREIGN KEY (flock_id) REFERENCES flock(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 8. EGG PRODUCTION  (daily egg collection)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE egg_production (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flock_id            INT UNSIGNED NOT NULL,
    eggs_collected      INT UNSIGNED NOT NULL DEFAULT 0,
    broken_eggs         INT UNSIGNED NOT NULL DEFAULT 0,
    collection_date     DATE         NOT NULL,
    recorded_by         INT UNSIGNED NOT NULL,  -- worker.id
    notes               TEXT         DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ep_flock  FOREIGN KEY (flock_id)    REFERENCES flock(id),
    CONSTRAINT fk_ep_worker FOREIGN KEY (recorded_by) REFERENCES worker(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 9. MORTALITY  (bird death records)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE mortality (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flock_id        INT UNSIGNED NOT NULL,
    count           INT UNSIGNED NOT NULL DEFAULT 1,
    cause           ENUM('disease','predator','injury','unknown','other') NOT NULL DEFAULT 'unknown',
    mortality_date  DATE         NOT NULL,
    recorded_by     INT UNSIGNED NOT NULL,  -- worker.id
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mort_flock  FOREIGN KEY (flock_id)    REFERENCES flock(id),
    CONSTRAINT fk_mort_worker FOREIGN KEY (recorded_by) REFERENCES worker(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 10. SALE
-- ─────────────────────────────────────────────────────────────
CREATE TABLE sale (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    flock_id        INT UNSIGNED  NOT NULL,
    sale_type       ENUM('live_birds','eggs','dressed_birds') NOT NULL DEFAULT 'live_birds',
    quantity        INT UNSIGNED  NOT NULL DEFAULT 0,
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    buyer_name      VARCHAR(150)  NOT NULL,
    buyer_phone     VARCHAR(15)   DEFAULT NULL,
    sale_date       DATE          NOT NULL,
    recorded_by     INT UNSIGNED  NOT NULL,  -- worker.id or 0 for admin
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_flock FOREIGN KEY (flock_id) REFERENCES flock(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 11. EXPENSE
-- ─────────────────────────────────────────────────────────────
CREATE TABLE expense (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    category        ENUM('feed','medication','equipment','labor','utilities','transport','other') NOT NULL DEFAULT 'other',
    description     VARCHAR(255)  NOT NULL,
    amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expense_date    DATE          NOT NULL,
    recorded_by     INT UNSIGNED  NOT NULL,  -- worker.id
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exp_worker FOREIGN KEY (recorded_by) REFERENCES worker(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 12. BUYER  (registered egg buyers)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE buyer (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    phone           VARCHAR(15)   NOT NULL UNIQUE,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    address         TEXT          DEFAULT NULL,
    password        VARCHAR(255)  NOT NULL,  -- bcrypt hash
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 13. EGG AVAILABILITY  (admin publishes egg listings)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE egg_availability (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    flock_id            INT UNSIGNED  NOT NULL,
    available_crates    INT UNSIGNED  NOT NULL DEFAULT 0,   -- 1 crate = 30 eggs
    price_per_crate     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description         TEXT          DEFAULT NULL,
    is_available        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ea_flock FOREIGN KEY (flock_id) REFERENCES flock(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 14. EGG BOOKING  (buyer order)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE egg_booking (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    buyer_id            INT UNSIGNED  NOT NULL,
    availability_id     INT UNSIGNED  NOT NULL,
    quantity_crates     INT UNSIGNED  NOT NULL DEFAULT 1,
    unit_price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_address    TEXT          NOT NULL,
    delivery_date       DATE          DEFAULT NULL,
    order_status        ENUM('pending','confirmed','paid','dispatched','delivered','cancelled') NOT NULL DEFAULT 'pending',
    payment_status      ENUM('unpaid','paid','refunded')  NOT NULL DEFAULT 'unpaid',
    payment_reference   VARCHAR(100)  DEFAULT NULL,
    notes               TEXT          DEFAULT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eb_buyer        FOREIGN KEY (buyer_id)        REFERENCES buyer(id),
    CONSTRAINT fk_eb_availability FOREIGN KEY (availability_id) REFERENCES egg_availability(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 15. ORDER TRACKING  (admin status update history)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE order_tracking (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NOT NULL,
    status      ENUM('pending','confirmed','paid','dispatched','delivered','cancelled') NOT NULL,
    note        VARCHAR(255) DEFAULT NULL,
    updated_by  INT UNSIGNED NOT NULL,  -- admin.id
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ot_booking FOREIGN KEY (booking_id) REFERENCES egg_booking(id),
    CONSTRAINT fk_ot_admin   FOREIGN KEY (updated_by) REFERENCES admin(id)
) ENGINE=InnoDB;
