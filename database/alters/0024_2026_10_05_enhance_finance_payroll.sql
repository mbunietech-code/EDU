-- Expand Finance payroll from period totals into detailed staff-level payroll snapshots.
ALTER TABLE `finance_payroll_periods` ADD COLUMN `basic_pay` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `staff_count`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `total_allowances` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `basic_pay`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `overtime_pay` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `total_allowances`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `bonus_pay` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `overtime_pay`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `taxable_pay` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `gross_pay`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `total_deductions`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `pension_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `tax_amount`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `insurance_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `pension_amount`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `loan_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `insurance_amount`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `other_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `loan_deductions`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `employer_cost` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `net_pay`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `notes` TEXT NULL AFTER `employer_cost`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `prepared_at` TIMESTAMP NULL AFTER `notes`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `approved_at` TIMESTAMP NULL AFTER `prepared_at`;
ALTER TABLE `finance_payroll_periods` ADD COLUMN `paid_at` TIMESTAMP NULL AFTER `approved_at`;
UPDATE `finance_payroll_periods` SET `basic_pay` = `gross_pay`, `taxable_pay` = `gross_pay`, `employer_cost` = `gross_pay`, `prepared_at` = COALESCE(`prepared_at`, `created_at`) WHERE `basic_pay` = 0;
CREATE TABLE IF NOT EXISTS `finance_payroll_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `finance_payroll_period_id` BIGINT UNSIGNED NOT NULL,
  `finance_staff_id` BIGINT UNSIGNED NULL,
  `staff_number` VARCHAR(255) NULL,
  `staff_name` VARCHAR(255) NOT NULL,
  `department_name` VARCHAR(255) NULL,
  `position_name` VARCHAR(255) NULL,
  `basic_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `allowances` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `overtime_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `bonus_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `gross_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `pension_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `insurance_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `loan_deduction` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `other_deduction` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `net_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_channel` VARCHAR(255) NULL,
  `bank_name` VARCHAR(255) NULL,
  `bank_account_number` VARCHAR(255) NULL,
  `mobile_money` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_payroll_staff_unique` (`finance_payroll_period_id`, `finance_staff_id`),
  KEY `finance_payroll_items_period_department_index` (`finance_payroll_period_id`, `department_name`),
  KEY `finance_payroll_items_finance_staff_id_foreign` (`finance_staff_id`),
  CONSTRAINT `finance_payroll_items_finance_payroll_period_id_foreign` FOREIGN KEY (`finance_payroll_period_id`) REFERENCES `finance_payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_payroll_items_finance_staff_id_foreign` FOREIGN KEY (`finance_staff_id`) REFERENCES `finance_staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
