-- sql/schema.sql
-- Defines the database structure for the gacha app.
-- Run once to create the tables (we'll automate this via Docker shortly).

-- ── Table: items ──────────────────────────────────────────────
-- Every possible thing a user can pull. Each row is one item.
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,   -- unique number, auto-assigned per row
    name VARCHAR(100) NOT NULL,          -- display name, e.g. "Diluc"
    rarity TINYINT NOT NULL,             -- 3, 4, or 5 (star rating)
    image_url VARCHAR(255) DEFAULT NULL  -- optional, for showing item art later
);

-- ── Table: pulls ──────────────────────────────────────────────
-- A log of every pull ever made. One row = one pull event.
-- This table is what lets us calculate pity later (count rows
-- since the last 5-star, per user).
CREATE TABLE IF NOT EXISTS pulls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                -- which user made this pull (no users table yet — see note below)
    item_id INT NOT NULL,                -- which item they got
    pulled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- when, set automatically

    -- FOREIGN KEY: enforces that item_id must match a real row in "items".
    -- Prevents "orphan" pulls referencing items that don't exist.
    FOREIGN KEY (item_id) REFERENCES items(id),

    -- INDEX: speeds up "WHERE user_id = ?" queries — without this,
    -- MySQL scans every row in the table every time history is fetched.
    INDEX idx_user_id (user_id)
);
