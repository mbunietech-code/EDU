-- Customer renewals: a renewal order points at the order it renews.
-- Additive; "duplicate column" / "duplicate key" = already applied.

ALTER TABLE `orders` ADD COLUMN `renews_order_id` BIGINT UNSIGNED NULL AFTER `plan_id`;
ALTER TABLE `orders` ADD CONSTRAINT `orders_renews_order_id_foreign` FOREIGN KEY (`renews_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;
