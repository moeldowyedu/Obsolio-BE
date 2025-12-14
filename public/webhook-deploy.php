<?php
/**
 * GitHub Webhook Handler for Auto-Deploy
 * Backend URL: https://api.obsolio.com/webhook-deploy.php?type=backend
 * Frontend URL: https://api.obsolio.com/webhook-deploy.php?type=frontend
 */

$config = [
    'backend' => [
        'secret' => 'ObsolioBackendWebhook2025Secret',
        'repo' => 'moeldowyedu/Obsolio-BE',
        'branch' => 'main',
        'script' => '/home/obsolio/scripts/deploy-backend.sh'
    ],
    'frontend' => [
        'secret' => 'ObsolioFrontendWebhook2025Secret',
        'repo' => 'moeldowyedu/Obsolio-FE',
        'branch' => 'main',
        'script' => '/home/obsolio_fe/scripts/deploy-frontend.sh'
    ]
];

$logFile = '/home/obsolio/logs/webhook.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

$type = $_GET['type'] ?? 'backend';

if (!isset($config[$type])) {
    http_response_code(400);
    logMessage("Invalid type: $type");
    die('Invalid type');
}

$settings = $config[$type];
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $settings['secret']);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    logMessage("[$type] Invalid signature");
    die('Invalid signature');
}

$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    logMessage("[$type] Invalid payload");
    die('Invalid payload');
}

$ref = $data['ref'] ?? '';
$expectedRef = 'refs/heads/' . $settings['branch'];

if ($ref !== $expectedRef) {
    http_response_code(200);
    logMessage("[$type] Push to different branch: $ref (expected: $expectedRef)");
    echo "Push to different branch";
    exit;
}

$repo = $data['repository']['full_name'] ?? '';
if ($repo !== $settings['repo']) {
    http_response_code(400);
    logMessage("[$type] Invalid repository: $repo");
    die('Invalid repository');
}

$commits = $data['commits'] ?? [];
$pusher = $data['pusher']['name'] ?? 'unknown';
$commitCount = count($commits);

logMessage("[$type] Received push from $pusher with $commitCount commits");

// Execute deploy script using sudo
$script = $settings['script'];
$cmd = "sudo $script >> /tmp/deploy_$type.log 2>&1 &";
shell_exec($cmd);

logMessage("[$type] Deploy command executed: $cmd");

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Deploy triggered',
    'type' => $type,
    'commits' => $commitCount
]);
