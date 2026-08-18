-- MbunieEduHub - Software products schema (run on Hostinger phpMyAdmin / mysql)
-- Safe to run repeatedly on MariaDB: every ALTER uses ADD COLUMN IF NOT EXISTS.
-- Note: MySQL 8.0 (not MariaDB) does NOT support ADD COLUMN IF NOT EXISTS --
-- on MySQL run the plain ALTERs only once.

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS type ENUM('subscription','software') NOT NULL DEFAULT 'subscription' AFTER status,
    ADD COLUMN IF NOT EXISTS software_file VARCHAR(255) NULL AFTER type,
    ADD COLUMN IF NOT EXISTS software_filename VARCHAR(255) NULL AFTER software_file,
    ADD COLUMN IF NOT EXISTS software_version VARCHAR(255) NULL AFTER software_filename,
    ADD COLUMN IF NOT EXISTS software_key VARCHAR(255) NULL AFTER software_version;

-- Old per-buyer key pool (replaced by the single product-level software_key)
DROP TABLE IF EXISTS product_keys;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS software_access_expires_at TIMESTAMP NULL AFTER confirmed_at;