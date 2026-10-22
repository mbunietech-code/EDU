-- Finance expense types (umeme, bundles, kodi…) and a link to what created an
-- expense automatically (payroll, statutory return, product account, company
-- asset), so the same cost is never recorded twice.
-- Additive: existing expenses keep these NULL. "Duplicate column" /
-- "Duplicate key name" = already applied.

ALTER TABLE `finance_expenses` ADD COLUMN `expense_type` VARCHAR(40) NULL AFTER `category`;
ALTER TABLE `finance_expenses` ADD COLUMN `source_type` VARCHAR(40) NULL AFTER `expense_type`;
ALTER TABLE `finance_expenses` ADD COLUMN `source_id` BIGINT UNSIGNED NULL AFTER `source_type`;
ALTER TABLE `finance_expenses` ADD INDEX `finance_expenses_expense_type_index` (`expense_type`);
ALTER TABLE `finance_expenses` ADD INDEX `finance_expenses_source_index` (`source_type`, `source_id`);
