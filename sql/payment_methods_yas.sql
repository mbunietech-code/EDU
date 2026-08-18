-- MbunieEduHub - Payment methods setup (run on Hostinger phpMyAdmin / mysql)
-- Safe to run: uses IF NOT EXISTS / INSERT ... ON DUPLICATE KEY UPDATE

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    qr_image VARCHAR(255) NULL,
    link_url VARCHAR(255) NULL,
    store_url VARCHAR(255) NULL,
    instructions TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payment_methods_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_methods (code, name, description, instructions, enabled, sort_order, link_url, store_url, created_at, updated_at) VALUES
('yas', 'MIX by YAS', 'Pay securely with MIX by YAS QR code.', 'Open the MIX by YAS app, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 1, 'intent://#Intent;package=tz.tigo.mfsapp;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dtz.tigo.mfsapp;end', 'https://play.google.com/store/apps/details?id=tz.tigo.mfsapp', NOW(), NOW()),
('alipay', 'Alipay', 'Pay securely with Alipay QR code.', 'Open Alipay, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 2, NULL, NULL, NOW(), NOW()),
('wechat_pay', 'WeChat Pay', 'Pay securely with WeChat Pay QR code.', 'Open WeChat, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 3, NULL, NULL, NOW(), NOW()),
('airtel', 'Airtel Scan', 'Pay securely with Airtel Money QR code.', 'Open Airtel Money, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 4, NULL, NULL, NOW(), NOW()),
('voda', 'Voda M-Pesa', 'Pay securely with Vodacom M-Pesa QR code.', 'Open Vodacom M-Pesa, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 5, NULL, NULL, NOW(), NOW()),
('crdb', 'CRDB', 'Pay securely with CRDB QR code.', 'Open CRDB, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 6, NULL, NULL, NOW(), NOW()),
('halotel', 'Halotel', 'Pay securely with Halotel QR code.', 'Open Halotel, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 7, NULL, NULL, NOW(), NOW()),
('all_network', 'All Network', 'Pay securely with All Network QR code.', 'Open the app, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 8, NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    instructions = VALUES(instructions),
    enabled = VALUES(enabled),
    sort_order = VALUES(sort_order),
    link_url = VALUES(link_url),
    store_url = VALUES(store_url),
    updated_at = NOW();

INSERT INTO settings (`key`, `value`, `type`, `group`, `description`, `created_at`, `updated_at`) VALUES
('payment_methods', 'alipay,wechat_pay,yas,airtel,voda,crdb,halotel,all_network', 'string', 'payments', 'Comma separated accepted payment methods', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- =====================================================================
-- UPGRADE PATH (only if payment_methods already existed WITHOUT the
-- link_url / store_url columns from an earlier version of this script).
-- Run this ALTER *before* the INSERT above in that case.
-- =====================================================================
-- ALTER TABLE payment_methods
--     ADD COLUMN link_url VARCHAR(255) NULL AFTER qr_image,
--     ADD COLUMN store_url VARCHAR(255) NULL AFTER link_url;
