-- Call devices: a support admin's computer (browser Web Push) or phone (MHub app, FCM) that rings for video help calls even while signed out.
-- channel: webpush | fcm. token = push endpoint URL or FCM token; token_hash = sha256(token) for lookups.
-- Additive; "table already exists" = already applied.

CREATE TABLE IF NOT EXISTS `call_devices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `channel` VARCHAR(16) NOT NULL,
    `name` VARCHAR(120) NULL,
    `platform` VARCHAR(32) NULL,
    `token` TEXT NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `test_until` TIMESTAMP NULL,
    `last_rung_at` TIMESTAMP NULL,
    `last_seen_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `call_devices_token_hash_unique` (`token_hash`),
    KEY `call_devices_user_id_index` (`user_id`),
    CONSTRAINT `call_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
