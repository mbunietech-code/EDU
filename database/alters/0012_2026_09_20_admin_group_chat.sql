-- Team Chat groups: several admins chatting together in one thread.
-- Additive. "table already exists" is treated as already-applied.

CREATE TABLE IF NOT EXISTS admin_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT admin_groups_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_group_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_message_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY admin_group_members_admin_group_id_user_id_unique (admin_group_id, user_id),
    CONSTRAINT admin_group_members_admin_group_id_foreign FOREIGN KEY (admin_group_id) REFERENCES admin_groups (id) ON DELETE CASCADE,
    CONSTRAINT admin_group_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_group_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_group_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL DEFAULT 'text',
    body TEXT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    edited_at TIMESTAMP NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY admin_group_messages_admin_group_id_id_index (admin_group_id, id),
    CONSTRAINT admin_group_messages_admin_group_id_foreign FOREIGN KEY (admin_group_id) REFERENCES admin_groups (id) ON DELETE CASCADE,
    CONSTRAINT admin_group_messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
