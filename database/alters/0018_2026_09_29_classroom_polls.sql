-- Polls and quick quizzes inside a live class (host asks, participants vote once, results on close).
-- Additive; "table already exists" = already applied.

CREATE TABLE IF NOT EXISTS `learning_room_polls` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `learning_room_id` BIGINT UNSIGNED NOT NULL,
    `learning_room_session_id` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `question` VARCHAR(300) NOT NULL,
    `options` JSON NOT NULL,
    `correct_option` TINYINT UNSIGNED NULL,
    `closed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `learning_room_polls_room_session_index` (`learning_room_id`, `learning_room_session_id`),
    CONSTRAINT `learning_room_polls_learning_room_id_foreign` FOREIGN KEY (`learning_room_id`) REFERENCES `learning_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `learning_room_polls_learning_room_session_id_foreign` FOREIGN KEY (`learning_room_session_id`) REFERENCES `learning_room_sessions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `learning_room_polls_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_room_poll_votes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `learning_room_poll_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `choice` TINYINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `learning_room_poll_votes_learning_room_poll_id_user_id_unique` (`learning_room_poll_id`, `user_id`),
    CONSTRAINT `learning_room_poll_votes_learning_room_poll_id_foreign` FOREIGN KEY (`learning_room_poll_id`) REFERENCES `learning_room_polls` (`id`) ON DELETE CASCADE,
    CONSTRAINT `learning_room_poll_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
