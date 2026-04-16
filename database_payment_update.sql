-- ============================================================
-- Payment Update Migration
-- Run this against the pound_farming database
-- Adds: payment_method, payment_date, processed_by, receipt_number
-- ============================================================

USE pound_farming;

ALTER TABLE egg_booking
    ADD COLUMN payment_method   ENUM('cash','online') DEFAULT NULL         AFTER payment_reference,
    ADD COLUMN payment_date     DATETIME              DEFAULT NULL         AFTER payment_method,
    ADD COLUMN processed_by     INT UNSIGNED          DEFAULT NULL         AFTER payment_date,
    ADD COLUMN receipt_number   VARCHAR(60)           DEFAULT NULL UNIQUE  AFTER processed_by;
