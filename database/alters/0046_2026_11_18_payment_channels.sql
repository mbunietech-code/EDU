-- Payment channels: M-Pesa, Airtel Money, Mixx by Yas, HaloPesa, card, bank and PayPal, each served by
-- one gateway (PesaPal, ClickPesa, AzamPay, PayPal), switched on one by one in Admin -> Payment methods
-- and shown at checkout in the admin's priority order.
-- Additive; "table already exists" = already applied. The rows are created by the app on first use:
-- off, except channels of a gateway that was already live (so checkout keeps working).

CREATE TABLE IF NOT EXISTS `payment_channels` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(60) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `kind` VARCHAR(20) NOT NULL,
    `gateway` VARCHAR(40) NULL,
    `network` VARCHAR(40) NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_channels_code_unique` (`code`),
    KEY `payment_channels_enabled_sort_order_index` (`enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
