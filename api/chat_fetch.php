<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

function fail(int $code, string $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

$u = current_user();
if (!$u) fail(401, 'Log in again.');
$t = resolve_thread($u, $_GET);
if (!$t['ok']) fail($t['code'], $t['error']);
session_write_close();   // do not block other requests while polling

$pdo = db();
$convId = $t['conv_id'];
$after = (int)($_GET['after'] ?? 0);

$s = $pdo->prepare('SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name, u.role
                    FROM messages m JOIN users u ON u.id = m.sender_id
                    WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100');
$s->execute([$convId, $after]);
$rows = $s->fetchAll();

$files = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $f = $pdo->prepare("SELECT id, message_id, original_name, file_type, file_size FROM message_files WHERE message_id IN ($in)");
    $f->execute($ids);
    foreach ($f->fetchAll() as $x) {
        $files[$x['message_id']][] = ['id' => (int)$x['id'], 'name' => $x['original_name'], 'type' => $x['file_type'], 'size' => (int)$x['file_size']];
    }
}

$out = [];
foreach ($rows as $m) {
    $out[] = [
        'id' => (int)$m['id'],
        'mine' => (int)$m['sender_id'] === (int)$u['id'],
        'name' => $m['full_name'],
        'role' => $m['role'],
        'body' => (string)$m['body'],
        'time' => date('d M, H:i', strtotime($m['created_at'])),
        'files' => $files[$m['id']] ?? [],
    ];
}
$pdo->prepare('UPDATE messages SET read_at = NOW() WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL')
    ->execute([$convId, $u['id']]);

$counts = $t['refund'] ? unread_counts((int)$t['refund']['id'], $u) : [];
echo json_encode(['messages' => $out, 'counts' => $counts]);
