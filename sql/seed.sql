-- sql/seed.sql
-- Populates the "items" table with example pullable items.
-- Run this AFTER schema.sql has created the tables.

-- ── 5-star items (rarest tier) ───────────────────────────────
-- Genshin-style: very few 5-star items exist in the pool,
-- which is part of why the odds feel so low even at 0.6%.
INSERT INTO items (name, rarity) VALUES
    ('Crimson Phoenix', 5),
    ('Void Empress', 5),
    ('Stormbringer', 5);

-- ── 4-star items (mid tier) ───────────────────────────────────
-- More 4-star items in the pool than 5-star, but still limited.
INSERT INTO items (name, rarity) VALUES
    ('Frost Knight', 4),
    ('Jade Archer', 4),
    ('Iron Sentinel', 4),
    ('Wind Dancer', 4),
    ('Ember Mystic', 4);

-- ── 3-star items (common tier) ────────────────────────────────
-- The largest pool — this is the "everything else" bucket
-- that makes up 94.3% of pulls.
INSERT INTO items (name, rarity) VALUES
    ('Rusty Sword', 3),
    ('Wooden Shield', 3),
    ('Travel Boots', 3),
    ('Simple Bow', 3),
    ('Leather Armor', 3),
    ('Basic Staff', 3);
