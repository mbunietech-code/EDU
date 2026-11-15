-- AI Assistant chat (DeepSeek): replies are written while they are generated so the page can poll them.
-- status: streaming | done | stopped | error (NULL = older rows, shown as done). meta: small JSON (model, usage, error).
-- Additive; "duplicate column" = already applied.

ALTER TABLE `ai_messages` ADD COLUMN `status` VARCHAR(16) NULL AFTER `tools_used`;
ALTER TABLE `ai_messages` ADD COLUMN `meta` TEXT NULL AFTER `status`;
