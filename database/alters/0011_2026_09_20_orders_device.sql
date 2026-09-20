-- Which device the customer uses (filled by admins), so it is easy to remove
-- them from that device when the subscription ends. Stored once on the order;
-- subscriptions read it through their order. Additive; "duplicate column" = already applied.

ALTER TABLE orders ADD COLUMN device VARCHAR(100) NULL AFTER amount;
