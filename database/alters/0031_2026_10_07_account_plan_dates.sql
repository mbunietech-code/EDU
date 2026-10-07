-- AI plans we buy: each shared account records the plan we paid the provider for,
-- when we bought it and when it ends, so admins can see what is about to expire.
-- Additive; "duplicate column" / "duplicate key" = already applied.
ALTER TABLE `accounts` ADD COLUMN `plan_name` VARCHAR(120) NULL AFTER `name`;
ALTER TABLE `accounts` ADD COLUMN `purchased_at` DATE NULL AFTER `plan_name`;
ALTER TABLE `accounts` ADD COLUMN `expires_at` DATE NULL AFTER `purchased_at`;
ALTER TABLE `accounts` ADD COLUMN `cost` DECIMAL(12,2) NULL AFTER `expires_at`;
ALTER TABLE `accounts` ADD COLUMN `cost_currency` VARCHAR(3) NULL AFTER `cost`;
ALTER TABLE `accounts` ADD COLUMN `auto_renew` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cost_currency`;
ALTER TABLE `accounts` ADD INDEX `accounts_expires_at_index` (`expires_at`);
