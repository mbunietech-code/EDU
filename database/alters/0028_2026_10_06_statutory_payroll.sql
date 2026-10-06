-- Tanzania statutory payroll: PAYE bands, NSSF, SDL, WCF, provisions, and a tracker for monthly statutory returns.
ALTER TABLE `finance_payroll_items` ADD COLUMN `taxable_pay` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `nssf_employer` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `sdl_amount` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `wcf_amount` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `leave_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `severance_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `gratuity_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_items` ADD COLUMN `employer_cost` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `nssf_employer` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `sdl_amount` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `wcf_amount` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `leave_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `severance_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `gratuity_provision` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `total_provisions` DECIMAL(14,2) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS `finance_statutory_returns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `finance_payroll_period_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `authority` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `due_date` DATE NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `paid_at` DATE NULL,
  `reference` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `paid_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_statutory_returns_period_type_unique` (`finance_payroll_period_id`, `type`),
  KEY `finance_statutory_returns_status_due_date_index` (`status`, `due_date`),
  KEY `finance_statutory_returns_paid_by_foreign` (`paid_by`),
  CONSTRAINT `finance_statutory_returns_period_foreign` FOREIGN KEY (`finance_payroll_period_id`) REFERENCES `finance_payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_statutory_returns_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
