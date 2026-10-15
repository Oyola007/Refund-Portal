<?php
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u) { http_response_code(401); exit('Log in again.'); }

$s = db()->prepare('SELECT f.*, cv.refund_id, cv.kind, cv.user_one, cv.user_two FROM message_files f
                    JOIN messages m ON m.id = f.message_id JOIN conversations cv ON cv.id = m.conversation_id WHERE f.id = ?');
$s->execute([(int)($_GET['id'] ?? 0)]);
$f = $s->fetch();

$ok = false;
if ($f) {
    if ($f['kind'] === 'direct') {
        $ok = in_array((int)$u['id'], [(int)$f['user_one'], (int)$f['user_two']], true);
    } else {
        $refund = refund_for_user((int)$f['refund_id'], $u);
        $ok = $refund && thread_allowed($refund, $u, $f['kind']);
    }
}
if (!$ok) { http_response_code(404); exit('Not found.'); }
session_write_close();

$path = UPLOAD_DIR . '/' . basename($f['file_path']);
if (!is_file($path)) { http_response_code(404); exit('File missing.'); }

$mimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    'pdf' => 'application/pdf', 'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$inline = in_array($f['file_type'], ['image', 'video'], true) || $ext === 'pdf';

header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['original_name']));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

$size = filesize($path);
$start = 0;
$end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] === '' && $m[2] !== '') {
        $start = max(0, $size - (int)$m[2]);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = min((int)$m[2], $end);
    }
    if ($start > $end) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp)) {
    $chunk = fread($fp, min(8192, $left));
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fp);
