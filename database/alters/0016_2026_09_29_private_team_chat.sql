-- Private one-to-one Team Chat. A direct chat is an admin group with exactly two members,
-- identified by direct_key "<lower user id>:<higher user id>"; only those two can read it.
-- The old shared threads (admin_conversations, readable by every super admin) are copied into
-- direct chats between the admin and the super admin who answered most (no answer: first super admin).
-- Old tables are kept. Additive; "duplicate column/key" = already applied.

ALTER TABLE admin_groups ADD COLUMN direct_key VARCHAR(41) NULL AFTER name;

ALTER TABLE admin_groups ADD UNIQUE KEY admin_groups_direct_key_unique (direct_key);

ALTER TABLE admin_conversations ADD COLUMN peer_id BIGINT UNSIGNED NULL;

ALTER TABLE admin_conversations ADD COLUMN migrated_at TIMESTAMP NULL;

-- Counterpart of each old thread: the super admin who replied most.
UPDATE admin_conversations c SET c.peer_id = (SELECT m.sender_id FROM admin_messages m WHERE m.admin_conversation_id = c.id AND m.sender_id <> c.admin_id GROUP BY m.sender_id ORDER BY COUNT(*) DESC, MIN(m.id) LIMIT 1) WHERE c.migrated_at IS NULL AND c.peer_id IS NULL;

-- Unanswered threads go to the first super admin.
UPDATE admin_conversations c SET c.peer_id = (SELECT u.id FROM users u WHERE u.is_admin = 1 AND (u.role = 'super_admin' OR u.role IS NULL OR u.role = '') AND u.id <> c.admin_id ORDER BY u.id LIMIT 1) WHERE c.migrated_at IS NULL AND c.peer_id IS NULL AND EXISTS (SELECT 1 FROM admin_messages m WHERE m.admin_conversation_id = c.id);

INSERT IGNORE INTO admin_groups (name, direct_key, created_by, created_at, updated_at) SELECT 'Private chat', CONCAT(LEAST(c.admin_id, c.peer_id), ':', GREATEST(c.admin_id, c.peer_id)), MIN(c.admin_id), MIN(c.created_at), MAX(c.updated_at) FROM admin_conversations c WHERE c.migrated_at IS NULL AND c.peer_id IS NOT NULL AND EXISTS (SELECT 1 FROM admin_messages m WHERE m.admin_conversation_id = c.id) GROUP BY CONCAT(LEAST(c.admin_id, c.peer_id), ':', GREATEST(c.admin_id, c.peer_id));

INSERT INTO admin_group_messages (admin_group_id, sender_id, type, body, file_path, file_name, edited_at, is_deleted, created_at, updated_at) SELECT g.id, m.sender_id, m.type, m.body, m.file_path, m.file_name, m.edited_at, m.is_deleted, m.created_at, m.updated_at FROM admin_messages m JOIN admin_conversations c ON c.id = m.admin_conversation_id JOIN admin_groups g ON g.direct_key = CONCAT(LEAST(c.admin_id, c.peer_id), ':', GREATEST(c.admin_id, c.peer_id)) WHERE c.migrated_at IS NULL AND c.peer_id IS NOT NULL ORDER BY m.created_at, m.id;

INSERT IGNORE INTO admin_group_members (admin_group_id, user_id, created_at, updated_at) SELECT g.id, CAST(SUBSTRING_INDEX(g.direct_key, ':', 1) AS UNSIGNED), NOW(), NOW() FROM admin_groups g WHERE g.direct_key IS NOT NULL;

INSERT IGNORE INTO admin_group_members (admin_group_id, user_id, created_at, updated_at) SELECT g.id, CAST(SUBSTRING_INDEX(g.direct_key, ':', -1) AS UNSIGNED), NOW(), NOW() FROM admin_groups g WHERE g.direct_key IS NOT NULL;

-- Copied history counts as read, so nobody gets a burst of old "unread" messages.
UPDATE admin_group_members gm JOIN admin_groups g ON g.id = gm.admin_group_id SET gm.last_read_message_id = (SELECT MAX(x.id) FROM admin_group_messages x WHERE x.admin_group_id = gm.admin_group_id) WHERE g.direct_key IS NOT NULL AND gm.last_read_message_id IS NULL;

UPDATE admin_conversations SET migrated_at = NOW() WHERE migrated_at IS NULL AND peer_id IS NOT NULL;
