-- Self-hosted live rooms (LiveKit SFU + coturn) — replaces the Jitsi/JaaS integration.
-- Additive only. Safe to run more than once (duplicate columns are skipped, IF NOT EXISTS guards).
-- The old `learning_room_attendances`.`jitsi_participant_id` column is no longer used by the code;
-- it is left in place because alters never drop columns (see README.md).

ALTER TABLE `learning_rooms`
    ADD COLUMN `allow_screen_share` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_participant_media`;

ALTER TABLE `learning_rooms`
    ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_screen_share`;

ALTER TABLE `learning_room_attendances`
    ADD COLUMN `can_publish_audio` TINYINT(1) NULL AFTER `removed_by`;

ALTER TABLE `learning_room_attendances`
    ADD COLUMN `can_publish_video` TINYINT(1) NULL AFTER `can_publish_audio`;

ALTER TABLE `learning_room_attendances`
    ADD COLUMN `can_share_screen` TINYINT(1) NULL AFTER `can_publish_video`;

ALTER TABLE `learning_room_sessions`
    ADD COLUMN `egress_id` VARCHAR(100) NULL AFTER `peak_participants`;

ALTER TABLE `learning_room_sessions`
    ADD COLUMN `recording_started_at` TIMESTAMP NULL AFTER `egress_id`;

CREATE TABLE IF NOT EXISTS `learning_room_materials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `learning_room_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `disk` VARCHAR(30) NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime` VARCHAR(100) NULL,
    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `learning_room_materials_room_index` (`learning_room_id`),
    CONSTRAINT `learning_room_materials_learning_room_id_fk` FOREIGN KEY (`learning_room_id`) REFERENCES `learning_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `learning_room_materials_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
