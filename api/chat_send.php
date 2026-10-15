<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

function fail(int $code, string $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');
$u = current_user();
if (!$u) fail(401, 'Log in again.');

// If the upload exceeded post_max_size PHP drops $_POST entirely.
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) fail(413, 'The files are too large. Keep each file under 25 MB.');
if (!csrf_ok()) fail(419, 'Your session expired. Refresh the page and try again.');

$t = resolve_thread($u, $_POST, true);
if (!$t['ok']) fail($t['code'], $t['error']);

$body = trim((string)($_POST['body'] ?? ''));
if (mb_strlen($body) > 4000) fail(422, 'Message is too long. Keep it under 4000 characters.');

try {
    $accepted = validate_uploads(collect_uploads('files'));
    if ($body === '' && !$accepted) fail(422, 'Type a message or attach a file.');
    $alreadyAlerted = unread_from_me($t['conv_id'], (int)$u['id']) > 0;
    $msgId = save_message($t['conv_id'], (int)$u['id'], $body, $accepted);
} catch (UploadException $ex) {
    fail($ex->getCode() ?: 422, $ex->getMessage());
} catch (Throwable $ex) {
    error_log('chat_send: ' . $ex->getMessage());
    fail(500, 'Message not sent. Try again.');
}

// Answer first, then email the other side, so sending never waits on the mail server.
$payload = json_encode(['ok' => true, 'id' => $msgId]);
ignore_user_abort(true);
header('Content-Length: ' . strlen($payload));
header('Connection: close');
echo $payload;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) ob_end_flush();
    flush();
}
if (!$alreadyAlerted) notify_recipients($u, $t);
