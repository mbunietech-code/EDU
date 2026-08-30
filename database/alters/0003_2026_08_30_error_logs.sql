-- Admin error log: captured server exceptions, viewable in Admin -> Error Logs.
-- Additive. "table already exists" is treated as already-applied.

CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fingerprint VARCHAR(64) NOT NULL,
    level VARCHAR(20) NOT NULL DEFAULT 'error',
    exception VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255) NULL,
    line INT UNSIGNED NULL,
    url VARCHAR(1000) NULL,
    method VARCHAR(10) NULL,
    user_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    trace LONGTEXT NULL,
    context JSON NULL,
    occurrences INT UNSIGNED NOT NULL DEFAULT 1,
    first_seen_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    resolved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY error_logs_fingerprint_unique (fingerprint),
    KEY error_logs_resolved_at_index (resolved_at),
    KEY error_logs_last_seen_at_index (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
