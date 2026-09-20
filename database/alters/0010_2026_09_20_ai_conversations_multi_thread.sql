-- AI Assistant: allow more than one conversation thread per admin, so past
-- chats can be listed and re-opened instead of one thread growing forever.
-- Additive/idempotent — safe to re-run.

ALTER TABLE ai_conversations ADD INDEX ai_conversations_user_id_index (user_id);
ALTER TABLE ai_conversations DROP INDEX ai_conversations_user_id_unique;
ALTER TABLE ai_conversations ADD COLUMN title VARCHAR(255) NULL AFTER user_id;
