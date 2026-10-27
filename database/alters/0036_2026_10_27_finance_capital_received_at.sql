-- Finance: the date capital or a loan was actually received (it may be
-- recorded days later). Additive: existing entries keep NULL and count on the
-- day they were recorded until edited. "Duplicate column" = already applied.

ALTER TABLE `finance_capital_entries` ADD COLUMN `received_at` DATE NULL AFTER `amount`;
