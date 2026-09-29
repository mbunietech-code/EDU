-- Live classroom "raise hand": when the participant raised their hand (NULL = hand down).
-- Additive; "duplicate column" = already applied.

ALTER TABLE `learning_room_attendances` ADD COLUMN `hand_raised_at` TIMESTAMP NULL AFTER `can_share_screen`;
