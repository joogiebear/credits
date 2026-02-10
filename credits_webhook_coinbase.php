<?php

define('IN_MYBB', 1);
define('NO_ONLINE', 1);

require_once './global.php';
require_once MYBB_ROOT . 'inc/plugins/credits/core.php';
require_once MYBB_ROOT . 'inc/plugins/credits/payments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    exit;
}

$signature = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';
if (empty($signature)) {
    http_response_code(401);
    exit;
}

if (!credits_coinbase_verify_webhook($payload, $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($payload, true);
if (!$event || !isset($event['event'])) {
    http_response_code(400);
    exit;
}

credits_coinbase_handle_webhook($event['event']);

http_response_code(200);
echo json_encode(array('status' => 'ok'));
exit;
