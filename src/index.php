<?php
// src/index.php — main entry point, serves the gacha pull page.
//
// KEY CONCEPT: $_SERVER['REQUEST_METHOD'] tells us HOW this page was
// requested. A normal visit/refresh is "GET". Clicking a <form> submit
// button is "POST". We only run the pull logic on POST — so loading
// or refreshing the page no longer burns a pull.

$host   = 'db';
$dbname = getenv('MYSQL_DATABASE');
$user   = getenv('MYSQL_USER');
$pass   = getenv('MYSQL_PASSWORD');

// Wrap connection in try/catch — if MySQL isn't ready or credentials
// are wrong, we show a friendly error instead of a raw PHP fatal crash.
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<h2>❌ Database connection failed</h2><p>" . $e->getMessage() . "</p>");
}

// ── Pity: get current pity counts for this user ─────────────────
// Returns both the 5-star and 4-star pity counters as an array.
// Uses INSERT ... ON DUPLICATE KEY UPDATE to auto-create the row
// on first pull without needing a separate existence check.
function getPityCounts(PDO $pdo, int $userId): array
{
    // Ensure a row exists for this user with both counters at 0.
    // ON DUPLICATE KEY UPDATE ... = ... means "do nothing if row exists".
    $stmt = $pdo->prepare(
        "INSERT INTO user_stats (user_id, pity_count, pity_count_4star)
         VALUES (:user_id, 0, 0)
         ON DUPLICATE KEY UPDATE pity_count = pity_count"
    );
    $stmt->execute(['user_id' => $userId]);

    // Fetch both counters in one query.
    $stmt = $pdo->prepare(
        "SELECT pity_count, pity_count_4star FROM user_stats WHERE user_id = :user_id"
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return as a named array so callers can do $counts['pity_count'] etc.
    return [
        'pity_count'       => (int) $row['pity_count'],
        'pity_count_4star' => (int) $row['pity_count_4star'],
    ];
}

// ── Pity: update both pity counters after a pull ─────────────────
function updatePityCount(PDO $pdo, int $userId, int $rarity): void
{
    if ($rarity === 5) {
        // 5-star resets ONLY the 5-star counter.
        // The 4-star counter keeps incrementing independently —
        // a 5-star pull does NOT count as satisfying the 4-star pity.
        $stmt = $pdo->prepare(
            "UPDATE user_stats
             SET pity_count = 0, pity_count_4star = pity_count_4star + 1
             WHERE user_id = :user_id"
        );
    } elseif ($rarity === 4) {
        // 4-star resets only the 4-star counter, keeps the 5-star
        // counter incrementing — getting a 4-star doesn't help your
        // progress toward a 5-star.
        $stmt = $pdo->prepare(
            "UPDATE user_stats
             SET pity_count = pity_count + 1, pity_count_4star = 0
             WHERE user_id = :user_id"
        );
    } else {
        // 3-star increments both counters.
        $stmt = $pdo->prepare(
            "UPDATE user_stats
             SET pity_count = pity_count + 1, pity_count_4star = pity_count_4star + 1
             WHERE user_id = :user_id"
        );
    }
    $stmt->execute(['user_id' => $userId]);
}

// ── Rarity roll with staged soft pity + hard pity at 100 ─────────
// 5-star rate climbs in stages every 10 pulls:
//   Pull  1–10:  0.1%  (1/1000)
//   Pull 11–20:  2.5%  (25/1000)
//   Pull 21–30:  5%    (50/1000)
//   Pull 31–40:  10%   (100/1000)
//   Pull 41–50:  20%   (200/1000)
//   Pull 51–60:  35%   (350/1000)
//   Pull 61–70:  50%   (500/1000)
//   Pull 71–80:  65%   (650/1000)
//   Pull 81–90:  80%   (800/1000)
//   Pull 91–99:  95%   (950/1000)
//   Pull 100:    100%  (hard pity)
//
// 4-star system: guaranteed every 10 pulls (tracked by pityCount4star)
function rollRarity(int $pityCount, int $pityCount4star): int
{
    // ── Hard pity: pull 100 is always a 5-star ──
    // pityCount >= 99 means 99 pulls have passed without a 5-star,
    // so this (the 100th) is guaranteed.
    if ($pityCount >= 99) {
        return 5;
    }

    // ── 4-star hard pity: guaranteed every 10 pulls ──
    if ($pityCount4star >= 9) {
        return 4;
    }

    // ── Staged 5-star rate based on which 10-pull bracket we're in ──
    // We use integer division (intdiv) to find which bracket:
    //   pityCount 0–9   → intdiv = 0 → stage 0 → 0.1%
    //   pityCount 10–19 → intdiv = 1 → stage 1 → 2.5%
    //   pityCount 20–29 → intdiv = 2 → stage 2 → 5%
    //   etc.
    $stage = intdiv($pityCount, 10);

    // Rate table: index = stage, value = threshold out of 1000
    // All rates halved from original values:
    //   Original → Halved
    //   0.1%     → 0.05%  (1   → 0, floored to minimum of 1 to avoid 0% chance)
    //   2.5%     → 1.25%  (25  → 12)
    //   5%       → 2.5%   (50  → 25)
    //   10%      → 5%     (100 → 50)
    //   20%      → 10%    (200 → 100)
    //   35%      → 17.5%  (350 → 175)
    //   50%      → 25%    (500 → 250)
    //   65%      → 32.5%  (650 → 325)
    //   80%      → 40%    (800 → 400)
    //   95%      → 47.5%  (950 → 475)
    $rateTable = [
        0 =>   1,   // pull  1–10:  0.1% (minimum 1 to keep non-zero chance)
        1 =>  10,   // pull 11–20:  1.0%
        2 =>  15,   // pull 21–30:  1.5%
        3 =>  20,   // pull 31–40:  2.0%
        4 => 25,   // pull 41–50:  2.5%
        5 => 50,   // pull 51–60:  5.0%
        6 => 60,   // pull 61–70:  6.0%
        7 => 70,   // pull 71–80:  7.0%
        8 => 80,   // pull 81–90:  8.0%
        9 => 200,   // pull 91–99:  20.0%
    ];

    // Look up the threshold for the current stage.
    // isset() guards against any stage value outside the table.
    $fiveStarThreshold = isset($rateTable[$stage]) ? $rateTable[$stage] : 950;

    // 4-star threshold sits on top of 5-star so ranges don't overlap:
    //   roll 1 – $fiveStarThreshold           → 5-star
    //   roll $fiveStarThreshold+1 – (that+51)  → 4-star (5.1% base)
    //   everything else                         → 3-star
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

// ── Helper: get the current 5-star rate % for display ────────────
// Mirrors the rate table in rollRarity() so the UI can show
// the exact % the player is currently rolling at.
function getCurrentFiveStarRate(int $pityCount): float
{
    if ($pityCount >= 99) return 100.0;

    $stage = intdiv($pityCount, 10);
    $rateTable = [
        0 => 0.1,   // pull  1–10:  0.05%
        1 => 1.0,   // pull 11–20:  1.25%
        2 => 1.5,    // pull 21–30:  1.5%
        3 => 2.0,    // pull 31–40:  2%
        4 => 2.5,   // pull 41–50:  2.5%
        5 => 5.0,   // pull 51–60:  5.0%
        6 => 6.0,   // pull 61–70:  6.0%
        7 => 7.0,   // pull 71–80:  7%
        8 => 8.0,   // pull 81–90:  8.0%
        9 => 20.0,   // pull 91–99:  20%
    ];
    return isset($rateTable[$stage]) ? $rateTable[$stage] : 95.0;
}

// ── Pick one random item from the given rarity tier ──────────────
function pickItem(PDO $pdo, int $rarity): array|false
{
    // ORDER BY RAND() shuffles all matching rows randomly.
    // LIMIT 1 takes just the first — effectively a random pick
    // with equal odds for every item in that rarity tier.
    $stmt = $pdo->prepare(
        "SELECT id, name, rarity FROM items WHERE rarity = :rarity ORDER BY RAND() LIMIT 1"
    );
    $stmt->execute(['rarity' => $rarity]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    // Returns an associative array like ['id'=>4, 'name'=>'Frost Knight', 'rarity'=>4]
    // or false if no items exist for that rarity tier.
}

// ── Log the pull into the pulls table ────────────────────────────
function logPull(PDO $pdo, int $userId, int $itemId): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO pulls (user_id, item_id) VALUES (:user_id, :item_id)"
    );
    $stmt->execute(['user_id' => $userId, 'item_id' => $itemId]);
    // pulled_at is set automatically by MySQL's DEFAULT CURRENT_TIMESTAMP.
}

// ── Main pull flow ────────────────────────────────────────────────
$userId     = 1;
$pulledItem = null;  // stays null if no pull happened this request
$pityCounts = getPityCounts($pdo, $userId); // always fetch for display
$pityCount       = $pityCounts['pity_count'];
$pityCount4star  = $pityCounts['pity_count_4star'];
$wasPity5    = false; // tracks whether 5-star hard pity triggered
$wasPity4    = false; // tracks whether 4-star pity triggered

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if hard pity is about to trigger BEFORE rolling.
    $wasPity5 = ($pityCount >= 99);
    $wasPity4 = ($pityCount4star >= 9);

    // Roll rarity, passing BOTH pity counters.
    $rarity = rollRarity($pityCount, $pityCount4star);

    // Pick a specific item from that rarity tier.
    $pulledItem = pickItem($pdo, $rarity);

    // Guard: if the item pool for this rarity is somehow empty,
    // pickItem() returns false — catch it before trying to use it.
    if ($pulledItem === false) {
        die("<h2>❌ No items found for rarity {$rarity}. Check your seed data.</h2>");
    }

    // Log the pull to the pulls table.
    logPull($pdo, $userId, $pulledItem['id']);

    // Update both pity counters based on what rarity was pulled.
    updatePityCount($pdo, $userId, $rarity);

    // Refresh both counters for display AFTER the update.
    $pityCounts      = getPityCounts($pdo, $userId);
    $pityCount       = $pityCounts['pity_count'];
    $pityCount4star  = $pityCounts['pity_count_4star'];
}

// ── Fetch recent pull history ─────────────────────────────────────
// JOIN combines "pulls" and "items" rows where item_id matches id,
// so we get the item name and rarity alongside the pull timestamp.
$historyStmt = $pdo->prepare(
    "SELECT items.name, items.rarity, pulls.pulled_at
     FROM pulls
     JOIN items ON pulls.item_id = items.id
     WHERE pulls.user_id = :user_id
     ORDER BY pulls.pulled_at DESC
     LIMIT 10"
);
$historyStmt->execute(['user_id' => $userId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Determine soft pity status for UI display ─────────────────────
// Soft pity = any stage past the first (pull 11+)
// Hard pity = pull 100 (pityCount >= 99)
$inSoftPity  = ($pityCount >= 10 && $pityCount < 99);
$inHardPity  = ($pityCount >= 99);
$in4starPity = ($pityCount4star >= 9);

// Get the exact current 5-star rate % for UI display
$currentRate = getCurrentFiveStarRate($pityCount);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gacha Pull</title>
    <style>
        body {
            font-family: sans-serif;
            background: #1a1a2e;
            color: #eee;
            text-align: center;
            padding: 40px;
        }
        .pull-btn {
            font-size: 18px;
            padding: 14px 32px;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .pull-btn:hover { background: #d63852; }
        .result {
            margin: 30px auto;
            padding: 20px;
            max-width: 300px;
            border-radius: 10px;
            background: #16213e;
        }
        .rarity-5 { border: 2px solid gold; }
        .rarity-4 { border: 2px solid #b266ff; }
        .rarity-3 { border: 2px solid #888; }

        /* Pity counter bars */
        .pity-box {
            margin: 20px auto;
            max-width: 340px;
            background: #16213e;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 14px;
        }
        .pity-row { margin-top: 12px; }
        .pity-bar-bg {
            background: #0f3460;
            border-radius: 6px;
            height: 10px;
            margin-top: 6px;
            overflow: hidden;
        }
        .pity-bar-fill {
            height: 10px;
            border-radius: 6px;
            transition: width 0.3s ease;
        }
        .pity-normal  { background: #4caf50; }
        .pity-soft    { background: #ff9800; }
        .pity-hard    { background: #e94560; }
        .pity-4star   { background: #b266ff; }
        .pity-label { font-size: 12px; color: #aaa; margin-top: 4px; }

        table { margin: 20px auto; border-collapse: collapse; }
        td, th { padding: 6px 14px; border-bottom: 1px solid #333; font-size: 14px; }
        .star-5 { color: gold; }
        .star-4 { color: #b266ff; }
        .star-3 { color: #aaa; }
    </style>
</head>
<body>

    <h1>🎰 Gacha Pull</h1>

    <form method="POST">
        <button type="submit" class="pull-btn">Pull x1</button>
    </form>

    <!-- ── Pity counter display ── -->
    <?php
        // 5-star bar fills over 100 pulls
        $pityPercent = min(($pityCount / 100) * 100, 100);
        if ($inHardPity) {
            $barClass  = 'pity-hard';
            $pityLabel = '⚠️ HARD PITY — next pull is guaranteed 5-star!';
        } elseif ($inSoftPity) {
            $barClass  = 'pity-soft';
            $pityLabel = '🔥 Rate climbing — currently at ' . $currentRate . '%';
        } else {
            $barClass  = 'pity-normal';
            $pityLabel = 'Current rate: ' . $currentRate . '% · Rate climbs every 10 pulls · Hard pity at pull 100';
        }

        // 4-star bar: purple, fills every 10 pulls
        $pity4Percent = min(($pityCount4star / 10) * 100, 100);
        $pity4Label   = $in4starPity
            ? '💜 4-star guaranteed this pull!'
            : "Pull {$pityCount4star} / 10 — 4-star guaranteed every 10 pulls";
    ?>
    <div class="pity-box">
        <!-- 5-star pity bar -->
        <div class="pity-row">
            <div>⭐⭐⭐⭐⭐ Pity: <strong><?= $pityCount ?> / 100</strong> &nbsp;|&nbsp; Current rate: <strong><?= $currentRate ?>%</strong></div>
            <div class="pity-bar-bg">
                <div class="pity-bar-fill <?= $barClass ?>"
                     style="width: <?= $pityPercent ?>%"></div>
            </div>
            <div class="pity-label"><?= $pityLabel ?></div>
        </div>

        <!-- 4-star pity bar -->
        <div class="pity-row">
            <div>⭐⭐⭐⭐ Pity: <strong><?= $pityCount4star ?> / 10</strong></div>
            <div class="pity-bar-bg">
                <div class="pity-bar-fill pity-4star"
                     style="width: <?= $pity4Percent ?>%"></div>
            </div>
            <div class="pity-label"><?= $pity4Label ?></div>
        </div>
    </div>

    <!-- ── Pull result ── -->
    <?php if ($pulledItem): ?>
        <div class="result rarity-<?= $pulledItem['rarity'] ?>">
            <?php if ($wasPity5): ?>
                <p>⚡ 5-star hard pity triggered!</p>
            <?php elseif ($wasPity4): ?>
                <p>💜 4-star pity triggered!</p>
            <?php endif; ?>
            <h2><?= str_repeat('⭐', $pulledItem['rarity']) ?></h2>
            <h2><?= htmlspecialchars($pulledItem['name']) ?></h2>
            <p><?= $pulledItem['rarity'] ?>-star</p>
        </div>
    <?php endif; ?>

    <!-- ── Pull history ── -->
    <h3>Recent Pulls</h3>
    <table>
        <tr><th>Item</th><th>Rarity</th><th>When</th></tr>
        <?php foreach ($history as $row): ?>
            <tr class="star-<?= $row['rarity'] ?>">
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['rarity'] ?>★</td>
                <td><?= $row['pulled_at'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>