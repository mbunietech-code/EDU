-- Guest links: join a room with just a name (no account), through the host's waiting room.
-- Off until an admin turns it on. Additive; "duplicate column/key" = already applied.

ALTER TABLE `learning_rooms` ADD COLUMN `guest_token` VARCHAR(40) NULL;

ALTER TABLE `learning_rooms` ADD UNIQUE KEY `learning_rooms_guest_token_unique` (`guest_token`);

ALTER TABLE `learning_rooms` ADD COLUMN `guest_waiting_room` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `users` ADD COLUMN `is_guest` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `users` ADD COLUMN `guest_room_id` BIGINT UNSIGNED NULL;

ALTER TABLE `users` ADD INDEX `users_guest_room_id_index` (`guest_room_id`);

ALTER TABLE `users` ADD COLUMN `guest_admitted_at` TIMESTAMP NULL;

ALTER TABLE `users` ADD COLUMN `guest_denied_at` TIMESTAMP NULL;
