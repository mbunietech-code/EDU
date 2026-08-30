-- Adds role-based access control to users.
--   role: 'user' | 'admin' | 'super_admin'
--   permissions: JSON array of permission keys (only used for 'admin')
-- Additive and safe to re-run (guarded backfills; duplicate-column errors are skipped).

ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_admin;
ALTER TABLE users ADD COLUMN permissions JSON NULL AFTER role;

-- Existing staff become normal admins...
UPDATE users SET role = 'admin' WHERE is_admin = 1 AND role = 'user';

-- ...and the earliest admin account is promoted to super admin so the
-- Team / Database screens are reachable right after applying.
UPDATE users SET role = 'super_admin'
WHERE is_admin = 1 AND role = 'admin'
  AND id = (SELECT x.id FROM (SELECT MIN(id) AS id FROM users WHERE is_admin = 1) x);
