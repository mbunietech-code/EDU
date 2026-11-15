-- Video help: a signed-in member asks for help on video; every support admin is rung until one accepts.
-- status: ringing | accepted | missed | cancelled. declined_by = JSON list of admin ids who declined.
-- Additive; "table already exists" = already applied.

CREATE TABLE IF NOT EXISTS `video_help_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `learning_room_id` BIGINT UNSIGNED NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'ringing',
    `reason` VARCHAR(500) NULL,
    `accepted_by` BIGINT UNSIGNED NULL,
    `declined_by` TEXT NULL,
    `ring_until` TIMESTAMP NULL,
    `accepted_at` TIMESTAMP NULL,
    `ended_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `video_help_requests_status_ring_until_index` (`status`, `ring_until`),
    KEY `video_help_requests_user_id_id_index` (`user_id`, `id`),
    CONSTRAINT `video_help_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `video_help_requests_learning_room_id_foreign` FOREIGN KEY (`learning_room_id`) REFERENCES `learning_rooms` (`id`) ON DELETE SET NULL,
    CONSTRAINT `video_help_requests_accepted_by_foreign` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
