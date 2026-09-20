-- AI Assistant chat: one ongoing thread per admin, plus its messages.
-- Additive. "table already exists" is treated as already-applied.

CREATE TABLE IF NOT EXISTS ai_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ai_conversations_user_id_unique (user_id),
    CONSTRAINT ai_conversations_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content LONGTEXT NOT NULL,
    tools_used JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ai_messages_conversation_id_index (conversation_id),
    CONSTRAINT ai_messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
