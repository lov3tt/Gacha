<?php
// api/pull.php — POST /api/pull
// Angular sends a POST request here when the user clicks "Pull x1".
// This file runs the pull logic and returns the result as JSON.
// It never outputs any HTML — only JSON.

// ── CORS headers ──────────────────────────────────────────────────
// CORS (Cross-Origin Resource Sharing) tells the browser it's OK
// for Angular (running on one port) to talk to PHP (on another port).
// Without these headers the browser blocks the request for security.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS is a "preflight" request browsers send before POST to ask
// "is this allowed?" — we just say yes and exit immediately.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests — reject everything else.
// http_response_code(405) means "Method Not Allowed".
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Load shared database connection and functions ─────────────────
// __DIR__ is the folder this file lives in (api/).
// This loads $pdo and all the gacha functions from db.php.
require_once __DIR__ . '/db.php';

// ── Run the pull ──────────────────────────────────────────────────
$userId = 1; // hardcoded for now — will be replaced when users are added

// Get current pity counts BEFORE the pull so we can report them.
$pityCounts     = getPityCounts($pdo, $userId);
$pityCount      = $pityCounts['pity_count'];
$pityCount4star = $pityCounts['pity_count_4star'];

// Track whether hard pity triggered this pull.
$wasPity5 = ($pityCount >= 99);
$wasPity4 = ($pityCount4star >= 9);

// Roll the rarity tier.
$rarity = rollRarity($pityCount, $pityCount4star);

// Pick a specific item from that tier.
$item = pickItem($pdo, $rarity);

// Guard: if no items exist for this rarity, return an error.
if ($item === false) {
    http_response_code(500);
    echo json_encode(['error' => "No items found for rarity {$rarity}. Check seed data."]);
    exit;
}

// Log the pull and update pity counters.
logPull($pdo, $userId, $item['id']);
updatePityCount($pdo, $userId, $rarity, $pityCount4star);

// Fetch updated pity counts AFTER the pull for the response.
$newCounts      = getPityCounts($pdo, $userId);
$newPityCount   = $newCounts['pity_count'];
$newPity4star   = $newCounts['pity_count_4star'];

// ── Build and send the JSON response ─────────────────────────────
// json_encode() converts a PHP array into a JSON string.
// Angular will receive this and use it to update the UI.
echo json_encode([
    // The item that was pulled
    'item' => [
        'id'     => (int) $item['id'],
        'name'   => $item['name'],
        'rarity' => (int) $item['rarity'],
    ],
    // Whether pity triggered this pull
    'was_pity_5' => $wasPity5,
    'was_pity_4' => $wasPity4,
    // Updated pity state AFTER this pull (for Angular to update the bars)
    'pity' => [
        'count_5star'    => $newPityCount,
        'count_4star'    => $newPity4star,
        'current_rate'   => getCurrentFiveStarRate($newPityCount),
        'in_soft_pity'   => ($newPityCount >= 10 && $newPityCount < 99),
        'in_hard_pity'   => ($newPityCount >= 99),
        'in_4star_pity'  => ($newPity4star >= 9),
    ],
]);
