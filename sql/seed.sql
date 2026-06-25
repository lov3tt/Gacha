-- sql/seed.sql
-- Kitty Treat Gacha — item seed data.
-- Run this AFTER schema.sql has created the tables.

-- Tell MySQL to interpret this file as utf8mb4 so emoji insert correctly.
-- Without this, emoji bytes get misread as Latin-1 characters.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ── 5-star items (rarest tier) ───────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('🍗 Chicken Chuupa 🍗', 5),
    ('🍣 Salmon Chuupa 🍣',  5),
    ('🐟 Tuna Chuupa 🐟',    5);

-- ── 4-star items (mid tier) ───────────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('Spank Kitty Butt 👋', 4),
    ('Pet the Kitty 👋',    4),
    ('Rub Kitty Chin 👋',   4),
    ('Kiss the Kitty 😽',   4),
    ('Boop the Kitty 👃',   4);

-- ── 3-star items (common tier) ────────────────────────────────
INSERT INTO items (name, rarity) VALUES
    ('Kitty is not impressed 😾',         3),
    ('Kitty turned their back on you 🙀', 3),
    ('Kitty walked away slowly... 🐾',    3),
    ('Kitty judged you silently 😼',      3),
    ('Kitty knocked it off the table 🐱', 3),
    ('Kitty blinked once and left 😿',    3),
    ('Kitty sat in the empty box 📦',     3),
    ('Kitty ignored you completely 🐈',   3);