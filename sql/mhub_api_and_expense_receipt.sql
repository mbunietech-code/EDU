-- MbunieEduHub — MHub app API (Sanctum) + expense receipt upload
-- Run on Hostinger phpMyAdmin / mysql. Safe to run repeatedly.
--
-- Adds:
--   1. personal_access_tokens   -> API token auth for the MHub cross-platform app
--   2. finance_expenses.receipt_path -> stores the uploaded receipt for an expense
--   3. records both migrations so `php artisan migrate` will not re-run them

-- ---------------------------------------------------------------------------
-- 1. Sanctum personal access tokens
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY personal_access_tokens_token_unique (token),
    KEY personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. finance_expenses.receipt_path  (MySQL has no "ADD COLUMN IF NOT EXISTS",
--    so guard with information_schema)
-- ---------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'finance_expenses'
      AND COLUMN_NAME = 'receipt_path'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE finance_expenses ADD COLUMN receipt_path VARCHAR(255) NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. Mark the Laravel migrations as already run
--    (batch value is not critical; uses current max batch + 1)
-- ---------------------------------------------------------------------------
SET @next_batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);

INSERT INTO migrations (migration, batch)
SELECT * FROM (SELECT '2026_08_30_000001_create_personal_access_tokens_table' AS migration, @next_batch AS batch) AS t
WHERE NOT EXISTS (
    SELECT 1 FROM migrations WHERE migration = '2026_08_30_000001_create_personal_access_tokens_table'
);

INSERT INTO migrations (migration, batch)
SELECT * FROM (SELECT '2026_08_30_000002_add_receipt_path_to_finance_expenses_table' AS migration, @next_batch AS batch) AS t
WHERE NOT EXISTS (
    SELECT 1 FROM migrations WHERE migration = '2026_08_30_000002_add_receipt_path_to_finance_expenses_table'
);
