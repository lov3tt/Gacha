<?php
// api/db.php — shared file: database connection + all gacha functions.
// This file is NOT an endpoint itself — it never outputs anything.
// Other files (pull.php, history.php, stats.php) load it with:
//   require_once __DIR__ . '/db.php';
// and then use the $pdo connection and functions defined here.

// ── Database connection ───────────────────────────────────────────
// Reads all connection details from environment variables.
// Locally: MYSQL_HOST=db (Docker internal), no port needed, no SSL.
// On Render + Aiven: MYSQL_HOST=<aiven-host>, MYSQL_PORT=<port>, SSL required.
// This one db.php works for both environments — just change the .env values.
$host   = getenv('MYSQL_HOST') ?: 'db';  // falls back to 'db' if not set
$port   = getenv('MYSQL_PORT') ?: '3306';
$dbname = getenv('MYSQL_DATABASE');
$user   = getenv('MYSQL_USER');
$pass   = getenv('MYSQL_PASSWORD');
$useSSL = getenv('MYSQL_SSL') === 'true';

// Build DSN — port included so external databases (Aiven etc.) work.
// charset=utf8mb4 is required for emoji in item names.
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

// SSL options — Aiven requires encrypted connections.
// PHP 8.5 renamed PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT to
// Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT. We support both versions
// by checking which constant exists at runtime.
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if ($useSSL) {
    // Use the new PHP 8.5 constant if available, fall back to old name
    $sslConstant = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
        ? Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT
        : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
    $options[$sslConstant] = false;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ── Function: getPityCounts ───────────────────────────────────────
// Fetches both pity counters for a user from user_stats.
// Auto-creates the row on first pull via INSERT ... ON DUPLICATE KEY.
// Returns: ['pity_count' => int, 'pity_count_4star' => int]
function getPityCounts(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "INSERT INTO user_stats (user_id, pity_count, pity_count_4star)
         VALUES (:user_id, 0, 0)
         ON DUPLICATE KEY UPDATE pity_count = pity_count"
    );
    $stmt->execute(['user_id' => $userId]);

    $stmt = $pdo->prepare(
        "SELECT pity_count, pity_count_4star FROM user_stats WHERE user_id = :user_id"
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'pity_count'       => (int) $row['pity_count'],
        'pity_count_4star' => (int) $row['pity_count_4star'],
    ];
}

// ── Function: updatePityCount ─────────────────────────────────────
// Updates both pity counters after a pull.
// 4-star counter runs on a fixed 10-pull schedule:
//   - Every pull increments it (natural 4-stars don't reset it)
//   - Only resets to 0 when the guaranteed 10th pull triggers
// 5-star counter resets to 0 on a 5-star pull, increments otherwise.
function updatePityCount(PDO $pdo, int $userId, int $rarity, int $pityCount4star): void
{
    $fourStarWasGuaranteed = ($pityCount4star >= 9);

    if ($rarity === 5) {
        $stmt = $pdo->prepare(
            "UPDATE user_stats
             SET pity_count = 0,
                 pity_count_4star = " . ($fourStarWasGuaranteed ? "0" : "pity_count_4star + 1") . "
             WHERE user_id = :user_id"
        );
    } else {
        $stmt = $pdo->prepare(
            "UPDATE user_stats
             SET pity_count = pity_count + 1,
                 pity_count_4star = " . ($fourStarWasGuaranteed ? "0" : "pity_count_4star + 1") . "
             WHERE user_id = :user_id"
        );
    }
    $stmt->execute(['user_id' => $userId]);
}

// ── Function: rollRarity ──────────────────────────────────────────
// Decides the rarity tier (3, 4, or 5) for a pull.
// 5-star: staged rate climbing every 10 pulls, hard pity at pull 100.
// 4-star: guaranteed every 10 pulls via pityCount4star.
function rollRarity(int $pityCount, int $pityCount4star): int
{
    // Hard pity: pull 100 always gives a 5-star
    if ($pityCount >= 99) {
        return 5;
    }

    // 4-star hard pity: guaranteed on the 10th pull
    if ($pityCount4star >= 9) {
        return 4;
    }

    // Staged 5-star rate — climbs every 10 pulls
    // intdiv(23, 10) = 2 → stage 2 → 1.5% etc.
    $stage = intdiv($pityCount, 10);

    $rateTable = [
        0 =>   1,   // pull  1–10:  0.1%
        1 =>  10,   // pull 11–20:  1.0%
        2 =>  15,   // pull 21–30:  1.5%
        3 =>  20,   // pull 31–40:  2.0%
        4 =>  25,   // pull 41–50:  2.5%
        5 =>  50,   // pull 51–60:  5.0%
        6 =>  60,   // pull 61–70:  6.0%
        7 =>  70,   // pull 71–80:  7.0%
        8 =>  80,   // pull 81–90:  8.0%
        9 => 200,   // pull 91–99: 20.0%
    ];

    $fiveStarThreshold = isset($rateTable[$stage]) ? $rateTable[$stage] : 200;

    // 4-star sits on top of 5-star so ranges don't overlap
    $fourStarThreshold = $fiveStarThreshold + 51;

    $roll = mt_rand(1, 1000);

    if ($roll <= $fiveStarThreshold) {
        return 5;
    } elseif ($roll <= $fourStarThreshold) {
        return 4;
    } else {
        return 3;
    }
}

// ── Function: getCurrentFiveStarRate ─────────────────────────────
// Returns the current 5-star rate as a human-readable percentage.
// Mirrors the rate table in rollRarity() — both must stay in sync.
// Used by Angular's pity bar to show "Current rate: 1.5%"
function getCurrentFiveStarRate(int $pityCount): float
{
    if ($pityCount >= 99) return 100.0;

    $stage = intdiv($pityCount, 10);
    $rateTable = [
        0 => 0.1,
        1 => 1.0,
        2 => 1.5,
        3 => 2.0,
        4 => 2.5,
        5 => 5.0,
        6 => 6.0,
        7 => 7.0,
        8 => 8.0,
        9 => 20.0,
    ];
    return isset($rateTable[$stage]) ? $rateTable[$stage] : 20.0;
}

// ── Function: pickItem ────────────────────────────────────────────
// Picks a random item from the given rarity tier.
// ORDER BY RAND() shuffles all matching rows, LIMIT 1 takes the first.
// Returns an associative array or false if no items exist for that tier.
function pickItem(PDO $pdo, int $rarity): array|false
{
    $stmt = $pdo->prepare(
        "SELECT id, name, rarity FROM items WHERE rarity = :rarity ORDER BY RAND() LIMIT 1"
    );
    $stmt->execute(['rarity' => $rarity]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Function: logPull ─────────────────────────────────────────────
// Writes one row to the pulls table recording what was pulled and when.
// pulled_at is set automatically by MySQL's DEFAULT CURRENT_TIMESTAMP.
function logPull(PDO $pdo, int $userId, int $itemId): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO pulls (user_id, item_id) VALUES (:user_id, :item_id)"
    );
    $stmt->execute(['user_id' => $userId, 'item_id' => $itemId]);
}
