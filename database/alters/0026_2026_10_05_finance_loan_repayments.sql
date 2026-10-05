-- Track repayments made against capital entries that were recorded as loans.
CREATE TABLE IF NOT EXISTS `finance_loan_repayments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `finance_capital_entry_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `paid_at` DATE NOT NULL,
  `method` VARCHAR(255) NULL,
  `reference` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `finance_loan_repayments_finance_capital_entry_id_foreign` (`finance_capital_entry_id`),
  KEY `finance_loan_repayments_created_by_foreign` (`created_by`),
  CONSTRAINT `finance_loan_repayments_finance_capital_entry_id_foreign` FOREIGN KEY (`finance_capital_entry_id`) REFERENCES `finance_capital_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_loan_repayments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
