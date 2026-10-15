<?php
// ---- Where is this running? ----
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array(preg_replace('/:\d+$/', '', $host), ['localhost', '127.0.0.1'], true);

// ---- Database ----
if ($isLocal) {
    // XAMPP
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'refund_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // LIVE (ProFreeHost): copy these from your hosting control panel > MySQL Databases
    define('DB_HOST', 'xxxxxxxx.com');       // e.g. sqlXXX.yourhost.com (not "localhost")
    define('DB_NAME', 'xxxxxxxxxx_refundsystem_db');    // e.g. ezyro_43068553_refund
    define('DB_USER', 'xxxxxxxxxxxx');    // e.g. ezyro_43068553
    define('DB_PASS', 'xxxxxxxxxxxxxxxxx');
}

// ---- App ----
define('APP_NAME', 'REFUND PORTAL');

// BASE_URL: "/refund_system" on XAMPP, "" on the host (files directly in htdocs).
// Works out the folder automatically, no trailing slash.
$docRoot     = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$projectRoot = rtrim(str_replace('\\', '/', (string) realpath(__DIR__)), '/');
define('BASE_URL', ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0)
    ? substr($projectRoot, strlen($docRoot))
    : '');

// SITE_URL: used for links in emails. Built from the current request.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('SITE_URL', $scheme . '://' . $host);

// ---- Email (login codes and status updates) ----
define('MAIL_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'olivercustomercare@gmail.com');
define('SMTP_PASS', 'xxxxxxxxxxxxxxxxx');
define('MAIL_FROM', 'olivercustomercare@gmail.com');
define('MAIL_FROM_NAME', 'REFUND PORTAL');

// ---- Login codes ----
define('OTP_TTL', 300);          // seconds a code stays valid
define('OTP_MAX_ATTEMPTS', 5);   // wrong guesses allowed per code
define('OTP_RESEND_WAIT', 60);   // seconds before a new code can be requested

// Showing the code on screen is only safe for testing.
// Always on locally. On the live site, set $LIVE_OTP_DEBUG to false once email works.
$LIVE_OTP_DEBUG = true;
define('OTP_DEBUG', $isLocal ? true : $LIVE_OTP_DEBUG);

// ---- Chat uploads ----
define('UPLOAD_DIR', __DIR__ . '/uploads/chat');
define('MAX_UPLOAD_BYTES', 25 * 1024 * 1024);
define('MAX_FILES_PER_MESSAGE', 5);