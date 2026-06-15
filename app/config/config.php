<?php
define('MAIL_SERVER', '?????????????');
define('MAIL_IMAP_PORT', 993);
define('MAIL_SMTP_PORT', 587);
define('MAIL_ENCRYPTION_IMAP', 'ssl');
define('MAIL_ENCRYPTION_SMTP', 'tls');

define('APP_NAME', 'Klient Poczty');
define('APP_URL', '?????????????');
define('APP_DEBUG', false);
define('SMTP_DEBUG', false);

define('UPLOAD_DIR', __DIR__ . '/../../tmp/');
define('MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024);
define('EMAILS_PER_PAGE', 20);

date_default_timezone_set('Europe/Warsaw');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
