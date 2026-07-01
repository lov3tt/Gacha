<?php
// api/wake.php — wake-up endpoint for the Aiven database service.
// Called by Angular once on page load (see app.component.ts ngOnInit).
//
// Aiven's free tier powers OFF the database after a period of inactivity.
// A powered-off service refuses connections, so pull.php / stats.php would
// fail until it comes back. This endpoint sends a "power on" signal to the
// Aiven management API so the database starts waking up the moment someone
// opens the site — before they ever click "Pull".
//
// This file does NOT touch the database itself (that would fail while it's
// asleep). It only talks to Aiven's REST API over HTTPS.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Config from environment variables (set these on Render) ───────
//   AIVEN_TOKEN   — a personal access token from Aiven console
//   AIVEN_PROJECT — the project name that contains the service
//   AIVEN_SERVICE — the service name of the MySQL database
$token   = getenv('AIVEN_TOKEN');
$project = getenv('AIVEN_PROJECT');
$service = getenv('AIVEN_SERVICE');

// If the wake feature isn't configured, don't error the page — just say so.
// The app still works; it simply won't pre-warm the database.
if (!$token || !$project || !$service) {
    echo json_encode([
        'status'  => 'skipped',
        'message' => 'Aiven wake not configured (missing AIVEN_TOKEN/PROJECT/SERVICE)',
    ]);
    exit;
}

// Aiven API endpoint for a single service.
// PATCH with {"powered": true} turns the service on if it was off.
$url = "https://api.aiven.io/v1/project/"
     . rawurlencode($project)
     . "/service/"
     . rawurlencode($service);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['powered' => true]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: aivenv1 $token",
    "Content-Type: application/json",
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// 200 means Aiven accepted the request (service is on or powering up).
// We report status back but never expose the token or raw error to the client.
if ($curlErr) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Could not reach Aiven API']);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['status' => 'waking', 'message' => 'Wake signal sent to Aiven']);
} else {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => "Aiven API returned HTTP $httpCode"]);
}
