-- Website chatbot (public pages): visitor questions and bot replies, keyed by a random per-browser session key.
-- Settings (provider, model, encrypted API keys) live in the existing `settings` table, group "chatbot".
-- Additive; "table already exists" = already applied.

CREATE TABLE IF NOT EXISTS `chatbot_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_key` VARCHAR(64) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `role` VARCHAR(16) NOT NULL,
    `content` TEXT NOT NULL,
    `provider` VARCHAR(20) NULL,
    `model` VARCHAR(100) NULL,
    `status` VARCHAR(20) NULL,
    `ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `chatbot_messages_session_key_id_index` (`session_key`, `id`),
    KEY `chatbot_messages_created_at_index` (`created_at`),
    CONSTRAINT `chatbot_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
