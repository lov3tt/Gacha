-- sql/seed.sql
-- Populates the "items" table with example pullable items.
-- Run this AFTER schema.sql has created the tables.

-- ── 5-star items (rarest tier) ───────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('Crimson Phoenix', 5),
    ('Void Empress', 5),
    ('Stormbringer', 5);

-- ── 4-star items (mid tier) ───────────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('Frost Knight', 4),
    ('Jade Archer', 4),
    ('Iron Sentinel', 4),
    ('Wind Dancer', 4),
    ('Ember Mystic', 4);

-- ── 3-star items (common tier) ────────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('Rusty Sword', 3),
    ('Wooden Shield', 3),
    ('Travel Boots', 3),
    ('Simple Bow', 3),
    ('Leather Armor', 3),
    ('Basic Staff', 3);