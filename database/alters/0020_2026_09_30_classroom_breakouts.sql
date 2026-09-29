-- Breakout rooms in the live classroom (each one is its own SFU room "<provider_room>-b<n>").
-- Additive; "duplicate column" = already applied.

ALTER TABLE `learning_room_sessions` ADD COLUMN `breakout_count` TINYINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `learning_room_sessions` ADD COLUMN `breakouts_open` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `learning_room_attendances` ADD COLUMN `breakout_number` TINYINT UNSIGNED NULL;

ALTER TABLE `learning_room_messages` ADD COLUMN `breakout_number` TINYINT UNSIGNED NULL;
