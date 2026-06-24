<?php
// api/stats.php — GET /api/stats
// Angular calls this on page load to get the current pity state
// so it can render the pity bars before the first pull is made.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
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

$counts     = getPityCounts($pdo, $userId);
$pityCount  = $counts['pity_count'];
$pity4star  = $counts['pity_count_4star'];

echo json_encode([
    'pity' => [
        'count_5star'   => $pityCount,
        'count_4star'   => $pity4star,
        'current_rate'  => getCurrentFiveStarRate($pityCount),
        'in_soft_pity'  => ($pityCount >= 10 && $pityCount < 99),
        'in_hard_pity'  => ($pityCount >= 99),
        'in_4star_pity' => ($pity4star >= 9),
    ],
]);