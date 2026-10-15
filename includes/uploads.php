<?php
// Shared upload validation and message saving, used by the chat and by the refund form.

const UPLOAD_ALLOWED = [
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
    'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
    'pdf' => 'document', 'doc' => 'document', 'docx' => 'document',
];

class UploadException extends RuntimeException {}

/** Flatten $_FILES[$field] (multiple input) into a list, skipping empty slots. */
function collect_uploads(string $field): array {
    $out = [];
    if (empty($_FILES[$field]['name'])) return $out;
    foreach ((array)$_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name' => (string)$name, 'tmp' => $_FILES[$field]['tmp_name'][$i],
            'size' => (int)$_FILES[$field]['size'][$i], 'error' => $_FILES[$field]['error'][$i],
        ];
    }
    return $out;
}

/** Checks every file before anything is stored. Throws UploadException with a user-friendly message. */
function validate_uploads(array $incoming): array {
    if (count($incoming) > MAX_FILES_PER_MESSAGE) {
        throw new UploadException('You can attach up to ' . MAX_FILES_PER_MESSAGE . ' files at a time.');
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $ok = [];
    foreach ($incoming as $f) {
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new UploadException($f['name'] . ' is too large.', 413);
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp'])) throw new UploadException($f['name'] . ' could not be uploaded.');
        if ($f['size'] > MAX_UPLOAD_BYTES) throw new UploadException($f['name'] . ' is larger than 25 MB.', 413);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!isset(UPLOAD_ALLOWED[$ext])) throw new UploadException($f['name'] . ': this file type is not allowed. Use photos, videos, PDF or Word files.');
        $type = UPLOAD_ALLOWED[$ext];
        $mime = (string)$fi->file($f['tmp']);
        if (($type === 'image' && strpos($mime, 'image/') !== 0) || ($type === 'video' && strpos($mime, 'video/') !== 0)) {
            throw new UploadException($f['name'] . ' does not look like a real ' . $type . '.');
        }
        $ok[] = $f + ['ext' => $ext, 'type' => $type];
    }
    return $ok;
}

/**
 * Stores a chat message and its files. Uses the caller's transaction if one is open,
 * otherwise opens its own. Returns the new message id.
 */
function save_message(int $convId, int $senderId, ?string $body, array $accepted): int {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    $pdo = db();
    $own = !$pdo->inTransaction();
    $saved = [];
    if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)')
            ->execute([$convId, $senderId, ($body !== null && $body !== '') ? $body : null]);
        $msgId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = ?')->execute([$convId]);
        $ins = $pdo->prepare('INSERT INTO message_files (message_id, file_path, original_name, file_type, file_size) VALUES (?,?,?,?,?)');
        foreach ($accepted as $f) {
            $stored = bin2hex(random_bytes(16)) . '.' . $f['ext'];
            if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . '/' . $stored)) throw new RuntimeException('Could not store upload.');
            $saved[] = UPLOAD_DIR . '/' . $stored;
            $ins->execute([$msgId, $stored, mb_substr(basename($f['name']), 0, 255), $f['type'], $f['size']]);
        }
        if ($own) $pdo->commit();
        return $msgId;
    } catch (Throwable $ex) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        foreach ($saved as $p) @unlink($p);
        throw $ex;
    }
}
