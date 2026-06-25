-- sql/schema.sql
-- Defines the database structure for the gacha app.
-- Run once to create the tables.

-- Force utf8mb4 before creating tables so emoji store correctly.
-- Default MySQL utf8 only handles 3-byte characters — emoji need 4 bytes.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ── Table: items ──────────────────────────────────────────────
-- Every possible thing a user can pull. Each row is one item.
-- CHARACTER SET utf8mb4 is required for emoji in item names.
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rarity TINYINT NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Table: user_stats ─────────────────────────────────────────
-- Tracks per-user gacha statistics. One row per user.
CREATE TABLE IF NOT EXISTS user_stats (
    user_id          INT PRIMARY KEY,
    pity_count       INT NOT NULL DEFAULT 0,
    pity_count_4star INT NOT NULL DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Table: pulls ──────────────────────────────────────────────
-- A log of every pull ever made. One row = one pull event.
CREATE TABLE IF NOT EXISTS pulls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    pulled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_user_id (user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;