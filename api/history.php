<?php
// api/history.php — GET /api/history
// Angular calls this to fetch pull history for the history table
// and the "View Last 100 Pulls" popup.
// Returns two lists: recent (last 10) and full (last 100).

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

require_once __DIR__ . '/db.php';

$userId = 1;

// ── Fetch last 10 pulls for the main history table ────────────────
$stmt = $pdo->prepare(
    "SELECT items.name, items.rarity, pulls.pulled_at
     FROM pulls
     JOIN items ON pulls.item_id = items.id
     WHERE pulls.user_id = :user_id
     ORDER BY pulls.pulled_at DESC
     LIMIT 10"
);
$stmt->execute(['user_id' => $userId]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch last 100 pulls for the popup ───────────────────────────
$stmt = $pdo->prepare(
    "SELECT items.name, items.rarity, pulls.pulled_at
     FROM pulls
     JOIN items ON pulls.item_id = items.id
     WHERE pulls.user_id = :user_id
     ORDER BY pulls.pulled_at DESC
     LIMIT 100"
);
$stmt->execute(['user_id' => $userId]);
$full = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Count rarities in the full history for the summary bar ────────
$count5 = count(array_filter($full, fn($r) => $r['rarity'] == 5));
$count4 = count(array_filter($full, fn($r) => $r['rarity'] == 4));
$count3 = count(array_filter($full, fn($r) => $r['rarity'] == 3));

echo json_encode([
    'recent' => $recent,   // last 10 pulls
    'full'   => $full,     // last 100 pulls
    'summary' => [         // rarity counts for the popup header
        '5star' => $count5,
        '4star' => $count4,
        '3star' => $count3,
        'total' => count($full),
    ],
]);
