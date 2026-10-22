-- Finance money accounts ("mifuko"): where the money sits, transfers between
-- them, and which one each expense / capital entry / loan repayment used.
-- Additive: existing rows keep money_account_id NULL ("not assigned").
-- "Duplicate column" / "already exists" / duplicate key = already applied.

CREATE TABLE IF NOT EXISTS `finance_money_accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'mobile_money',
    `number` VARCHAR(120) NULL,
    `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `opening_date` DATE NULL,
    `payment_methods` LONGTEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `finance_money_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_money_transfers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_account_id` BIGINT UNSIGNED NOT NULL,
    `to_account_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `transferred_at` DATE NOT NULL,
    `reference` VARCHAR(120) NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `finance_money_transfers_from_account_id_foreign` FOREIGN KEY (`from_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_money_transfers_to_account_id_foreign` FOREIGN KEY (`to_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_money_transfers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `finance_expenses` ADD COLUMN `money_account_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `finance_expenses` ADD CONSTRAINT `finance_expenses_money_account_id_foreign` FOREIGN KEY (`money_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE SET NULL;

ALTER TABLE `finance_capital_entries` ADD COLUMN `money_account_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `finance_capital_entries` ADD CONSTRAINT `finance_capital_entries_money_account_id_foreign` FOREIGN KEY (`money_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE SET NULL;

ALTER TABLE `finance_loan_repayments` ADD COLUMN `money_account_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `finance_loan_repayments` ADD CONSTRAINT `finance_loan_repayments_money_account_id_foreign` FOREIGN KEY (`money_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE SET NULL;
