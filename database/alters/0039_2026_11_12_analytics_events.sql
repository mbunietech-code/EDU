-- Site and app analytics (Admin -> Reports -> Analytics): page views, app downloads, app opens.
-- "visitor" is a hashed device id, never a raw cookie or IP. Additive; "table already exists" = already applied.

CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(20) NOT NULL,
    `visitor` VARCHAR(64) NOT NULL,
    `new_visitor` TINYINT(1) NOT NULL DEFAULT 0,
    `platform` VARCHAR(20) NULL,
    `source` VARCHAR(20) NULL,
    `app_version` VARCHAR(20) NULL,
    `path` VARCHAR(255) NULL,
    `referrer` VARCHAR(120) NULL,
    `device` VARCHAR(10) NULL,
    `os` VARCHAR(20) NULL,
    `browser` VARCHAR(20) NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `analytics_events_type_created_at_index` (`type`, `created_at`),
    KEY `analytics_events_type_visitor_index` (`type`, `visitor`),
    CONSTRAINT `analytics_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
