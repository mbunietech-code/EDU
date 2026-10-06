-- PayPal support for gateway payments: charged in USD, paid on PayPal's own page.
ALTER TABLE `gateway_payments` ADD COLUMN `charged_amount` DECIMAL(14,2) NULL AFTER `currency`;
ALTER TABLE `gateway_payments` ADD COLUMN `charged_currency` VARCHAR(3) NULL AFTER `charged_amount`;
ALTER TABLE `gateway_payments` ADD COLUMN `payer_email` VARCHAR(255) NULL AFTER `charged_currency`;
ALTER TABLE `gateway_payments` ADD COLUMN `redirect_url` TEXT NULL AFTER `payer_email`;
