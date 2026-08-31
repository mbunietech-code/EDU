-- Research & Consultancy library
-- Safe to run more than once (IF NOT EXISTS guards).

ALTER TABLE `users`
    ADD COLUMN `can_write_research` TINYINT(1) NOT NULL DEFAULT 0 AFTER `permissions`;

CREATE TABLE IF NOT EXISTS `research_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) NULL,
    `icon` VARCHAR(60) NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `research_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `researches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_category_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `summary` VARCHAR(1000) NULL,
    `cover_image` VARCHAR(255) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
    `review_note` TEXT NULL,
    `reviewed_by` BIGINT UNSIGNED NULL,
    `submitted_at` TIMESTAMP NULL,
    `published_at` TIMESTAMP NULL,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `researches_slug_unique` (`slug`),
    KEY `researches_status_category_index` (`status`, `research_category_id`),
    CONSTRAINT `researches_category_fk` FOREIGN KEY (`research_category_id`) REFERENCES `research_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `researches_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `researches_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_chapters` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `research_chapters_research_position_index` (`research_id`, `position`),
    CONSTRAINT `research_chapters_research_fk` FOREIGN KEY (`research_id`) REFERENCES `researches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_sections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_chapter_id` BIGINT UNSIGNED NOT NULL,
    `heading` VARCHAR(255) NOT NULL,
    `body` LONGTEXT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `research_sections_chapter_position_index` (`research_chapter_id`, `position`),
    CONSTRAINT `research_sections_chapter_fk` FOREIGN KEY (`research_chapter_id`) REFERENCES `research_chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id` BIGINT UNSIGNED NOT NULL,
    `reviewer_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(30) NOT NULL,
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `research_reviews_research_index` (`research_id`),
    CONSTRAINT `research_reviews_research_fk` FOREIGN KEY (`research_id`) REFERENCES `researches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `research_reviews_reviewer_fk` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_reading_progress` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `research_id` BIGINT UNSIGNED NOT NULL,
    `last_section_id` BIGINT UNSIGNED NULL,
    `percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `done_section_ids` JSON NULL,
    `last_read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `research_reading_progress_user_research_unique` (`user_id`, `research_id`),
    CONSTRAINT `research_reading_progress_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `research_reading_progress_research_fk` FOREIGN KEY (`research_id`) REFERENCES `researches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `research_reading_progress_section_fk` FOREIGN KEY (`last_section_id`) REFERENCES `research_sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
