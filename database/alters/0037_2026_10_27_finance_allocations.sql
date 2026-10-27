-- Finance: money already in an account, given to one line of the overview (a
-- software, a cost type or a name) as its capital. Money does not move: the
-- account balance and the company's total stay the same.
-- Additive. "Already exists" = already applied.

CREATE TABLE IF NOT EXISTS `finance_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `money_account_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `tool_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `allocated_at` DATE NOT NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `finance_allocations_money_account_id_foreign` FOREIGN KEY (`money_account_id`) REFERENCES `finance_money_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_allocations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `finance_allocations_tool_id_foreign` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE SET NULL,
    CONSTRAINT `finance_allocations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
