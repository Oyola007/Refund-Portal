<?php
require_once __DIR__ . '/../config.php';

date_default_timezone_set('Africa/Nairobi');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

const STATUSES = [
    'pending' => 'Pending', 'under_review' => 'Under review',
    'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid',
];
const REASONS = [
    'Flight cancelled by airline', 'Significant schedule change', 'Passenger cancelled / did not travel',
    'Duplicate booking or duplicate charge', 'Paid service not received', 'Downgrade / class-of-service issue', 'Other',
];
const DOCS = ['Ticket / e-ticket receipt', 'Boarding pass', 'Payment receipt', 'Cancellation notice', 'Other supporting document'];
const PAYMENT_METHODS = ['Credit card', 'Debit card', 'PayPal', 'Bank transfer', 'Other'];
const REFUND_METHODS = ['Original form of payment', 'Bank transfer', 'PayPal', 'Travel credit / voucher'];
const PAYOUT_METHODS = ['Original form of payment', 'Card refund', 'Bank transfer', 'PayPal', 'Travel credit / voucher'];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
        ]);
    }
    return $pdo;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path) { header('Location: ' . url($path)); exit; }
function money($n): string { return '$' . number_format((float)$n, 2); }
function badge(string $s): string { return '<span class="badge b-' . e($s) . '">' . e(STATUSES[$s] ?? $s) . '</span>'; }
function when(string $dt): string { return date('d M Y, H:i', strtotime($dt)); }

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function csrf_check(): void {
    if (!csrf_ok()) { http_response_code(419); exit('Your session expired. Go back, refresh the page and try again.'); }
}

// ---- Flash messages ----
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- Auth ----
function home_for(string $role): string { return $role . '/dashboard.php'; }
function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
            $s->execute([$_SESSION['uid']]);
            $user = $s->fetch() ?: null;
        }
    }
    return $user;
}
function require_role(string $role): array {
    $u = current_user();
    if (!$u) { flash('error', 'Log in to continue.'); redirect('login.php'); }
    if ($u['role'] !== $role) redirect(home_for($u['role']));
    return $u;
}

// ---- Email ----
function send_mail(string $to, string $subject, string $html, bool $debug = false): bool {
    $GLOBALS['mail_last_error'] = '';
    $GLOBALS['mail_debug'] = '';
    if (!MAIL_ENABLED) {
        $GLOBALS['mail_last_error'] = 'Email is switched off. Set MAIL_ENABLED to true in config.php.';
        return false;
    }
    if (SMTP_USER === '' || SMTP_USER === 'youraddress@gmail.com' || SMTP_PASS === '' || SMTP_PASS === 'your-16-char-app-password') {
        $GLOBALS['mail_last_error'] = 'SMTP_USER and SMTP_PASS in config.php still have the placeholder values.';
        return false;
    }
    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer', false)) {
            $dir = __DIR__ . '/../lib/PHPMailer/';
            require_once $dir . 'Exception.php';
            require_once $dir . 'PHPMailer.php';
            require_once $dir . 'SMTP.php';
        }
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host = SMTP_HOST;
        $m->Port = SMTP_PORT;
        $m->SMTPAuth = true;
        $m->Username = SMTP_USER;
        $m->Password = str_replace(' ', '', SMTP_PASS);   // Google shows app passwords with spaces
        $m->SMTPSecure = SMTP_PORT == 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->Timeout = 15;
        $m->CharSet = 'UTF-8';
        if ($debug) {
            $m->SMTPDebug = 2;
            $m->Debugoutput = function ($str) {
                $str = trim($str);
                if (preg_match('/^CLIENT -> SERVER: [A-Za-z0-9+\/=]{12,}$/', $str)) $str = 'CLIENT -> SERVER: [hidden]';
                $GLOBALS['mail_debug'] .= $str . "\n";
            };
        }
        $m->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $m->addAddress($to);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = $html;
        $m->AltBody = strip_tags($html);
        $m->send();
        return true;
    } catch (Throwable $ex) {
        $GLOBALS['mail_last_error'] = $ex->getMessage();
        error_log('Mail error: ' . $ex->getMessage());
        return false;
    }
}

// ---- One-time codes ----
function issue_otp(array $user): array {
    $pdo = db();
    $s = $pdo->prepare('SELECT created_at FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$user['id']]);
    $last = $s->fetch();
    if ($last && time() - strtotime($last['created_at']) < OTP_RESEND_WAIT) {
        return ['sent' => false, 'code' => null, 'throttled' => true];
    }
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$user['id']]);
    $pdo->prepare('INSERT INTO otp_codes (user_id, code_hash, expires_at, created_at) VALUES (?,?,?,?)')
        ->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + OTP_TTL), date('Y-m-d H:i:s')]);
    $mins = (int)(OTP_TTL / 60);
    $sent = send_mail($user['email'], 'Your ' . APP_NAME . ' login code',
        '<p>Hello ' . e($user['full_name']) . ',</p><p>Your login code is <strong style="font-size:22px;letter-spacing:4px">' . $code . '</strong></p><p>It expires in ' . $mins . ' minutes. If you did not try to log in, ignore this email.</p>');
    return ['sent' => $sent, 'code' => (!$sent && OTP_DEBUG) ? $code : null, 'throttled' => false];
}
function check_otp(int $userId, string $code): bool {
    $pdo = db();
    $s = $pdo->prepare('SELECT * FROM otp_codes WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $s->execute([$userId]);
    $r = $s->fetch();
    if (!$r || strtotime($r['expires_at']) < time() || $r['attempts'] >= OTP_MAX_ATTEMPTS) return false;
    if (!password_verify($code, $r['code_hash'])) {
        $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$r['id']]);
        return false;
    }
    $pdo->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$r['id']]);
    return true;
}

// ---- Refunds ----
function new_ref_no(): string { return 'TB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }

function log_history(int $refundId, ?int $by, ?string $old, string $new, ?string $note = null): void {
    db()->prepare('INSERT INTO refund_history (refund_id, changed_by, old_status, new_status, note) VALUES (?,?,?,?,?)')
        ->execute([$refundId, $by, $old, $new, $note]);
}

/** Returns the refund (with customer name/email) only if this user may see it. */
function refund_for_user(int $id, array $u): ?array {
    $sql = 'SELECT r.*, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
            FROM refund_requests r JOIN users c ON c.id = r.user_id WHERE r.id = ?';
    $args = [$id];
    if ($u['role'] === 'customer') { $sql .= ' AND r.user_id = ?'; $args[] = $u['id']; }
    elseif ($u['role'] === 'finance') { $sql .= " AND r.handler = 'finance'"; }
    $s = db()->prepare($sql);
    $s->execute($args);
    return $s->fetch() ?: null;
}

function ensure_conversation(int $refundId, int $customerId, string $kind = 'customer_admin'): int {
    $pdo = db();
    $s = $pdo->prepare('SELECT id FROM conversations WHERE refund_id = ? AND kind = ?');
    $s->execute([$refundId, $kind]);
    if ($id = $s->fetchColumn()) return (int)$id;
    $pdo->prepare('INSERT IGNORE INTO conversations (refund_id, customer_id, kind) VALUES (?,?,?)')->execute([$refundId, $customerId, $kind]);
    $s->execute([$refundId, $kind]);
    return (int)$s->fetchColumn();
}

require_once __DIR__ . '/uploads.php';

function history_for(int $refundId): array {
    $s = db()->prepare('SELECT h.*, u.full_name FROM refund_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.refund_id = ? ORDER BY h.id DESC');
    $s->execute([$refundId]);
    return $s->fetchAll();
}

// ---- Conversations: who can talk to whom ----
const THREAD_KINDS = ['customer_admin', 'customer_finance', 'admin_finance'];

/** Conversation kinds each role can ever take part in. */
function kinds_for_role(string $role): array {
    $map = [
        'customer' => ['customer_admin', 'customer_finance'],
        'admin'    => ['customer_admin', 'admin_finance'],
        'finance'  => ['customer_finance', 'admin_finance'],
    ];
    return $map[$role] ?? [];
}
function kinds_sql(string $role): string { return "'" . implode("','", kinds_for_role($role)) . "'"; }

/**
 * May this user open (or, with $forSending, write in) this conversation of this refund?
 * $refund must already have passed refund_for_user().
 */
function thread_allowed(array $refund, array $u, string $kind, bool $forSending = false): bool {
    if (!in_array($kind, kinds_for_role($u['role']), true)) return false;
    $transferred = !empty($refund['transferred_at']);
    switch ($kind) {
        case 'customer_admin':
            return true;
        case 'customer_finance':
            if (!$transferred) return false;
            return !$forSending || $refund['handler'] === 'finance';
        case 'admin_finance':
            return $transferred;
    }
    return false;
}

function chat_threads_for(array $refund, array $u): array {
    $labels = [
        'customer' => ['customer_admin' => 'Refunds team', 'customer_finance' => 'Finance'],
        'admin'    => ['customer_admin' => 'Customer', 'admin_finance' => 'Finance (internal)'],
        'finance'  => ['customer_finance' => 'Customer', 'admin_finance' => 'Admin (internal)'],
    ];
    $name = $refund['customer_name'] ?? 'the customer';
    $out = [];
    foreach (kinds_for_role($u['role']) as $kind) {
        if (!thread_allowed($refund, $u, $kind)) continue;
        if ($kind === 'admin_finance') $note = 'Internal. The customer cannot see this conversation.';
        elseif ($u['role'] === 'customer') $note = $kind === 'customer_admin' ? 'Private chat with the refunds team about this request.' : 'Private chat with the finance team about your payment.';
        else $note = 'Private chat with ' . $name . '.';
        $out[$kind] = ['label' => $labels[$u['role']][$kind], 'note' => $note, 'send' => thread_allowed($refund, $u, $kind, true)];
    }
    return $out;
}

/** Unread messages per conversation kind for this refund, as seen by this user. */
function unread_counts(int $refundId, array $u): array {
    $in = kinds_sql($u['role']);
    $s = db()->prepare("SELECT cv.kind, COUNT(*) c FROM messages m JOIN conversations cv ON cv.id = m.conversation_id
                        WHERE cv.refund_id = ? AND m.sender_id <> ? AND m.read_at IS NULL AND cv.kind IN ($in) GROUP BY cv.kind");
    $s->execute([$refundId, $u['id']]);
    $out = [];
    foreach ($s->fetchAll() as $r) $out[$r['kind']] = (int)$r['c'];
    return $out;
}

// ---- Direct conversations, inbox and notifications ----
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('error', 'Log in to continue.'); redirect('login.php'); }
    return $u;
}
function user_by_id(int $id): ?array {
    $s = db()->prepare('SELECT * FROM users WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function abs_url(string $path): string { return rtrim(defined('SITE_URL') ? SITE_URL : 'http://localhost', '/') . url($path); }

/** Customers may talk to staff, staff may talk to customers and each other's other role. Never customer to customer. */
function can_message(array $from, array $to): bool {
    if ((int)$from['id'] === (int)$to['id'] || empty($to['is_active'])) return false;
    $pair = [$from['role'], $to['role']];
    sort($pair);
    return in_array(implode('-', $pair), ['admin-customer', 'customer-finance', 'admin-finance'], true);
}
/** People this user may start a conversation with. */
function message_targets(array $u): array {
    $roles = ['customer' => ['admin', 'finance'], 'admin' => ['customer', 'finance'], 'finance' => ['customer', 'admin']][$u['role']] ?? [];
    if (!$roles) return [];
    $in = "'" . implode("','", $roles) . "'";
    $s = db()->prepare("SELECT id, full_name, email, role, is_active FROM users WHERE is_active = 1 AND id <> ? AND role IN ($in) ORDER BY role, full_name");
    $s->execute([$u['id']]);
    return $s->fetchAll();
}
function get_or_create_direct(array $from, array $to, ?string $subject): int {
    $a = min((int)$from['id'], (int)$to['id']);
    $b = max((int)$from['id'], (int)$to['id']);
    $key = $a . '-' . $b;
    $pdo = db();
    $s = $pdo->prepare("SELECT id, subject FROM conversations WHERE pair_key = ? AND kind = 'direct'");
    $s->execute([$key]);
    $c = $s->fetch();
    if (!$c) {
        $pdo->prepare("INSERT IGNORE INTO conversations (kind, user_one, user_two, started_by, pair_key, subject) VALUES ('direct',?,?,?,?,?)")
            ->execute([$a, $b, $from['id'], $key, $subject]);
        $s->execute([$key]);
        $c = $s->fetch();
    } elseif ($subject && !$c['subject']) {
        $pdo->prepare('UPDATE conversations SET subject = ? WHERE id = ?')->execute([$subject, $c['id']]);
    }
    return (int)$c['id'];
}
function direct_conversation_for_user(int $id, array $u): ?array {
    $s = db()->prepare("SELECT * FROM conversations WHERE id = ? AND kind = 'direct' AND (user_one = ? OR user_two = ?)");
    $s->execute([$id, $u['id'], $u['id']]);
    return $s->fetch() ?: null;
}

/** SQL (needs aliases cv and r) and args for every conversation this user may see in the inbox. */
function inbox_scope(array $u): array {
    $me = (int)$u['id'];
    $direct = "(cv.kind = 'direct' AND (cv.user_one = ? OR cv.user_two = ?))";
    switch ($u['role']) {
        case 'customer':
            return ["($direct OR (cv.kind IN ('customer_admin','customer_finance') AND cv.customer_id = ? AND cv.last_message_at IS NOT NULL))", [$me, $me, $me]];
        case 'admin':
            return ["($direct OR (cv.kind IN ('customer_admin','admin_finance') AND cv.last_message_at IS NOT NULL))", [$me, $me]];
        case 'finance':
            return ["($direct OR (cv.kind IN ('customer_finance','admin_finance') AND r.handler = 'finance' AND cv.last_message_at IS NOT NULL))", [$me, $me]];
    }
    return ['0 = 1', []];
}
function inbox_unread(array $u): int {
    [$where, $args] = inbox_scope($u);
    $s = db()->prepare("SELECT COUNT(*) FROM messages m JOIN conversations cv ON cv.id = m.conversation_id
                        LEFT JOIN refund_requests r ON r.id = cv.refund_id
                        WHERE $where AND m.sender_id <> ? AND m.read_at IS NULL");
    $s->execute(array_merge($args, [$u['id']]));
    return (int)$s->fetchColumn();
}
function thread_label(string $role, string $kind): string {
    $labels = [
        'customer' => ['customer_admin' => 'Refunds team', 'customer_finance' => 'Finance'],
        'admin'    => ['customer_admin' => 'Customer', 'admin_finance' => 'Finance (internal)'],
        'finance'  => ['customer_finance' => 'Customer', 'admin_finance' => 'Admin (internal)'],
    ];
    return $labels[$role][$kind] ?? $kind;
}

/**
 * Works out which conversation a chat request is about and whether this user may use it.
 * $in is $_GET or $_POST: either conv (a direct chat) or refund_id + kind (a refund chat).
 */
function resolve_thread(array $u, array $in, bool $forSending = false): array {
    $bad = fn(int $code, string $msg) => ['ok' => false, 'code' => $code, 'error' => $msg];
    if (!empty($in['conv'])) {
        $c = direct_conversation_for_user((int)$in['conv'], $u);
        if (!$c) return $bad(404, 'Conversation not found.');
        $other = user_by_id((int)((int)$c['user_one'] === (int)$u['id'] ? $c['user_two'] : $c['user_one']));
        if (!$other) return $bad(404, 'Conversation not found.');
        if ($forSending && !$other['is_active']) return $bad(403, 'That account is deactivated, so you cannot message it.');
        return ['ok' => true, 'conv_id' => (int)$c['id'], 'kind' => 'direct', 'refund' => null, 'other' => $other];
    }
    $refund = refund_for_user((int)($in['refund_id'] ?? 0), $u);
    if (!$refund) return $bad(404, 'Refund not found.');
    $kind = (string)($in['kind'] ?? kinds_for_role($u['role'])[0] ?? 'customer_admin');
    if (!in_array($kind, THREAD_KINDS, true) || !thread_allowed($refund, $u, $kind)) return $bad(403, 'You cannot open this conversation.');
    if ($forSending && !thread_allowed($refund, $u, $kind, true)) return $bad(403, 'Finance is not handling this refund right now, so messages cannot be sent here.');
    return ['ok' => true, 'conv_id' => ensure_conversation((int)$refund['id'], (int)$refund['user_id'], $kind), 'kind' => $kind, 'refund' => $refund, 'other' => null];
}

/** Messages from $me that the other side has not read yet. If there are none, a new message deserves an email. */
function unread_from_me(int $convId, int $me): int {
    $s = db()->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_id = ? AND read_at IS NULL');
    $s->execute([$convId, $me]);
    return (int)$s->fetchColumn();
}
function message_recipients(array $u, array $t): array {
    if ($t['kind'] === 'direct') return [$t['other']];
    $pdo = db();
    $byRole = function (string $r) use ($pdo) {
        $s = $pdo->prepare('SELECT * FROM users WHERE role = ? AND is_active = 1');
        $s->execute([$r]);
        return $s->fetchAll();
    };
    $customer = fn() => array_values(array_filter([user_by_id((int)$t['refund']['user_id'])]));
    switch ($t['kind']) {
        case 'customer_admin':   return $u['role'] === 'customer' ? $byRole('admin') : $customer();
        case 'customer_finance': return $u['role'] === 'customer' ? $byRole('finance') : $customer();
        case 'admin_finance':    return $byRole($u['role'] === 'admin' ? 'finance' : 'admin');
    }
    return [];
}
function notify_recipients(array $u, array $t): void {
    try {
        foreach (message_recipients($u, $t) as $r) {
            send_mail($r['email'], 'New message on ' . APP_NAME,
                '<p>Hello ' . e($r['full_name']) . ',</p><p>' . e($u['full_name']) . ' sent you a message.</p>'
                . '<p><a href="' . e(abs_url('login.php')) . '">Log in</a> to read and reply.</p>');
        }
    } catch (Throwable $ex) {
        error_log('notify: ' . $ex->getMessage());
    }
}
