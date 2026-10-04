-- Keep meeting guest metadata separate from real member/customer reporting.
CREATE TABLE `learning_room_guests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `learning_room_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `admitted_at` TIMESTAMP NULL,
  `denied_at` TIMESTAMP NULL,
  `last_seen_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_room_guests_user_id_unique` (`user_id`),
  KEY `learning_room_guests_room_status_index` (`learning_room_id`, `admitted_at`, `denied_at`),
  KEY `learning_room_guests_last_seen_at_index` (`last_seen_at`),
  CONSTRAINT `learning_room_guests_room_fk` FOREIGN KEY (`learning_room_id`) REFERENCES `learning_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_room_guests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `learning_room_guests` (`learning_room_id`, `user_id`, `name`, `admitted_at`, `denied_at`, `last_seen_at`, `created_at`, `updated_at`)
SELECT `guest_room_id`, `id`, `name`, `guest_admitted_at`, `guest_denied_at`, `last_seen_at`, COALESCE(`created_at`, NOW()), COALESCE(`updated_at`, NOW())
FROM `users`
WHERE `is_guest` = 1 AND `guest_room_id` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `learning_room_id` = VALUES(`learning_room_id`),
  `name` = VALUES(`name`),
  `admitted_at` = VALUES(`admitted_at`),
  `denied_at` = VALUES(`denied_at`),
  `last_seen_at` = VALUES(`last_seen_at`),
  `updated_at` = VALUES(`updated_at`);
