-- MbunieEduHub - Software products schema (run on Hostinger phpMyAdmin / mysql)
-- Run this once to enable: product type 'software', product keys table, and the
-- 20-minute delivery window on orders.

ALTER TABLE products
    ADD COLUMN type ENUM('subscription','software') NOT NULL DEFAULT 'subscription' AFTER status,
    ADD COLUMN software_file VARCHAR(255) NULL AFTER type,
    ADD COLUMN software_filename VARCHAR(255) NULL AFTER software_file,
    ADD COLUMN software_version VARCHAR(255) NULL AFTER software_filename;

CREATE TABLE IF NOT EXISTS product_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    key_value VARCHAR(255) NOT NULL,
    status ENUM('available','sold','revoked') NOT NULL DEFAULT 'available',
    order_id BIGINT UNSIGNED NULL,
    sold_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY product_keys_key_value_index (key_value),
    KEY product_keys_product_id_status_index (product_id, status),
    CONSTRAINT product_keys_product_id_foreign FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT product_keys_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN software_access_expires_at TIMESTAMP NULL AFTER confirmed_at;
