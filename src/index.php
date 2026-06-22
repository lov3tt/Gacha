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

function rollRarity(): int
{
    $roll = mt_rand(1, 1000);
    if ($roll <= 6) {
        return 5;
    } elseif ($roll <= 57) {
        return 4;
    } else {
        return 3;
    }
}

function pickItem(PDO $pdo, int $rarity): array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, rarity FROM items WHERE rarity = :rarity ORDER BY RAND() LIMIT 1"
    );
    $stmt->execute(['rarity' => $rarity]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function logPull(PDO $pdo, int $userId, int $itemId): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO pulls (user_id, item_id) VALUES (:user_id, :item_id)"
    );
    $stmt->execute(['user_id' => $userId, 'item_id' => $itemId]);
}

$userId = 1;
$pulledItem = null; // will stay null unless a pull actually happens

// Only run the pull if this page was reached via a form POST submission
// (i.e. the user clicked the "Pull" button below).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rarity = rollRarity();
    $pulledItem = pickItem($pdo, $rarity);

    // Guard: if the item pool for this rarity is somehow empty,
    // pickItem() returns false — catch it before trying to use it.
    if ($pulledItem === false) {
        die("<h2>❌ No items found for rarity {$rarity}. Check your seed data.</h2>");
    }

    logPull($pdo, $userId, $pulledItem['id']);
}

// Fetch the user's 5 most recent pulls to display as history.
// JOIN combines rows from "pulls" and "items" where item_id matches id —
// this lets us show the item NAME, not just its numeric id.
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
        table { margin: 20px auto; border-collapse: collapse; }
        td, th { padding: 6px 14px; border-bottom: 1px solid #333; }
    </style>
</head>
<body>

    <h1>🎰 Gacha Pull</h1>

    <!-- 
        A <form> with method="POST" sends the request as POST when
        submitted. action="" means "submit back to this same page".
    -->
    <form method="POST">
        <button type="submit" class="pull-btn">Pull x1</button>
    </form>

    <?php if ($pulledItem): ?>
        <div class="result rarity-<?= $pulledItem['rarity'] ?>">
            <h2><?= str_repeat('⭐', $pulledItem['rarity']) ?></h2>
            <h2><?= htmlspecialchars($pulledItem['name']) ?></h2>
            <p><?= $pulledItem['rarity'] ?>-star</p>
        </div>
    <?php endif; ?>

    <h3>Recent Pulls</h3>
    <table>
        <tr><th>Item</th><th>Rarity</th><th>When</th></tr>
        <?php foreach ($history as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['rarity'] ?>★</td>
                <td><?= $row['pulled_at'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>
