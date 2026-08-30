-- MbunieEduHub — Roles & permissions + Database admin page
-- Run on Hostinger phpMyAdmin. Safe to run repeatedly.
--
-- NOTE: You normally do NOT need this file. After deploying the code,
-- open  Admin → Database  as a super admin and click "Apply" — it creates
-- everything below for you. This script is only a manual fallback.

-- ---------------------------------------------------------------------------
-- 1. schema_alters — tracks which database/alters/*.sql files have been run
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_alters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    statements INT UNSIGNED NOT NULL DEFAULT 0,
    ok TINYINT(1) NOT NULL DEFAULT 1,
    error TEXT NULL,
    applied_by BIGINT UNSIGNED NULL,
    applied_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY schema_alters_filename_unique (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. users.role + users.permissions  (guarded — MySQL has no ADD IF NOT EXISTS)
-- ---------------------------------------------------------------------------
SET @has_role := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @ddl := IF(@has_role = 0,
    "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_admin",
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_perms := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'permissions');
SET @ddl := IF(@has_perms = 0,
    'ALTER TABLE users ADD COLUMN permissions JSON NULL AFTER role',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill: existing staff -> 'admin'; earliest admin -> 'super_admin'.
UPDATE users SET role = 'admin' WHERE is_admin = 1 AND role = 'user';
UPDATE users SET role = 'super_admin'
WHERE is_admin = 1 AND role = 'admin'
  AND id = (SELECT x.id FROM (SELECT MIN(id) AS id FROM users WHERE is_admin = 1) x);

-- ---------------------------------------------------------------------------
-- 3. Record the alter file so the Database page shows it as already applied
-- ---------------------------------------------------------------------------
INSERT INTO schema_alters (filename, checksum, statements, ok, applied_at, created_at, updated_at)
SELECT * FROM (SELECT
    '0001_2026_08_30_roles_and_permissions.sql' AS filename,
    'manual' AS checksum, 4 AS statements, 1 AS ok,
    NOW() AS applied_at, NOW() AS created_at, NOW() AS updated_at) t
WHERE NOT EXISTS (
    SELECT 1 FROM schema_alters WHERE filename = '0001_2026_08_30_roles_and_permissions.sql'
);
