-- Company assets (what Mbunie pays for, when bought / ends, where bought) and admin tasks.
-- Additive; "duplicate column" / "table already exists" = already applied.

ALTER TABLE `users` ADD COLUMN `job_title` VARCHAR(80) NULL;

CREATE TABLE IF NOT EXISTS `company_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(30) NOT NULL DEFAULT 'other',
    `vendor` VARCHAR(150) NULL,
    `purchase_url` VARCHAR(500) NULL,
    `account` VARCHAR(150) NULL,
    `purchased_at` DATE NULL,
    `expires_at` DATE NULL,
    `billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'yearly',
    `cost` DECIMAL(12,2) NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'TZS',
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
    `responsible_id` BIGINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `reminded_days` SMALLINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `company_assets_expires_at_index` (`expires_at`),
    CONSTRAINT `company_assets_responsible_id_foreign` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `company_assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `priority` VARCHAR(10) NOT NULL DEFAULT 'normal',
    `status` VARCHAR(15) NOT NULL DEFAULT 'todo',
    `due_at` DATE NULL,
    `assigned_to` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `completed_at` TIMESTAMP NULL,
    `reminded_on` DATE NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `admin_tasks_assigned_to_status_index` (`assigned_to`, `status`),
    CONSTRAINT `admin_tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `admin_tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_task_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_task_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `admin_task_comments_admin_task_id_foreign` FOREIGN KEY (`admin_task_id`) REFERENCES `admin_tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `admin_task_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
