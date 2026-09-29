-- Shared whiteboard in the live classroom (one per session; strokes stored for late joiners).
-- Additive; "duplicate column" / "table already exists" = already applied.

ALTER TABLE `learning_room_sessions` ADD COLUMN `board_active` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `learning_room_sessions` ADD COLUMN `board_all_can_draw` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `learning_room_sessions` ADD COLUMN `board_version` INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `learning_room_board_strokes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `learning_room_session_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `uid` VARCHAR(40) NOT NULL,
    `data` JSON NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `learning_room_board_strokes_learning_room_session_id_uid_unique` (`learning_room_session_id`, `uid`),
    CONSTRAINT `learning_room_board_strokes_learning_room_session_id_foreign` FOREIGN KEY (`learning_room_session_id`) REFERENCES `learning_room_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `learning_room_board_strokes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
