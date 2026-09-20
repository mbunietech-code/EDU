-- Marks support-chat messages sent automatically (the "provider is late" auto-reply)
-- so they are never mistaken for a human answer. Additive; "duplicate column" = already applied.

ALTER TABLE chat_messages ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted;
