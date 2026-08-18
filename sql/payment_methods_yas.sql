-- MbunieEduHub - Payment methods setup (run on Hostinger phpMyAdmin / mysql)
-- Safe to run: uses IF NOT EXISTS / INSERT ... ON DUPLICATE KEY UPDATE

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    qr_image VARCHAR(255) NULL,
    instructions TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payment_methods_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_methods (code, name, description, instructions, enabled, sort_order, created_at, updated_at) VALUES
('yas', 'MIX by YAS', 'Pay securely with MIX by YAS QR code.', 'Open the MIX by YAS app, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 1, NOW(), NOW()),
('alipay', 'Alipay', 'Pay securely with Alipay QR code.', 'Open Alipay, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 2, NOW(), NOW()),
('wechat_pay', 'WeChat Pay', 'Pay securely with WeChat Pay QR code.', 'Open WeChat, scan the QR code, enter the exact amount and complete the payment. Then fill in the transaction reference below.', 1, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    instructions = VALUES(instructions),
    enabled = VALUES(enabled),
    sort_order = VALUES(sort_order),
    updated_at = NOW();

INSERT INTO settings (`key`, `value`, `type`, `group`, `description`, `created_at`, `updated_at`) VALUES
('payment_methods', 'alipay,wechat_pay,yas', 'string', 'payments', 'Comma separated accepted payment methods', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group` = VALUES(`group`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();
