<?php
/**
 * Decentralized Trust Attorneys - Site Configuration
 *
 * Works two ways, automatically:
 *  1) RAILWAY: if you add a MySQL plugin to your Railway project and set
 *     the SMTP_* variables below in your service's Variables tab, this
 *     file picks them up automatically — you don't need to edit anything.
 *  2) cPANEL: if those environment variables aren't present, it falls
 *     back to the DB_* / SMTP_* constants hardcoded below — edit those
 *     instead.
 */

function env_or(string $key, $default = null) {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// ---- Database settings ----
// Railway's MySQL plugin auto-injects MYSQLHOST / MYSQLPORT / MYSQLDATABASE /
// MYSQLUSER / MYSQLPASSWORD into every service in the same project — those
// are used first if present. Otherwise the constants below (cPanel) apply.
define('DB_HOST', env_or('MYSQLHOST', 'localhost'));
define('DB_PORT', env_or('MYSQLPORT', '3306'));
define('DB_NAME', env_or('MYSQLDATABASE', 'change_me_dbname'));
define('DB_USER', env_or('MYSQLUSER', 'change_me_dbuser'));
define('DB_PASS', env_or('MYSQLPASSWORD', 'change_me_dbpass'));

// ---- Email / SMTP settings ----
// Railway has no built-in mail server, so emails are sent via SMTP using
// PHPMailer (vendored in includes/PHPMailer/). Set these as Variables in
// your Railway service (Settings > Variables) — no code changes needed.
// A free SMTP provider like Brevo (formerly Sendinblue, 300 emails/day
// free), Mailgun, or a Gmail account with an "app password" all work.
// If SMTP_HOST is left empty, the site keeps working — it just silently
// skips sending emails instead of erroring.
define('SMTP_HOST', env_or('SMTP_HOST', ''));
define('SMTP_PORT', (int) env_or('SMTP_PORT', '587'));
define('SMTP_USER', env_or('SMTP_USER', ''));
define('SMTP_PASS', env_or('SMTP_PASS', ''));
define('SMTP_SECURE', env_or('SMTP_SECURE', 'tls')); // 'tls' or 'ssl'
define('SMTP_FROM', env_or('SMTP_FROM', 'no-reply@decenttrustattorneys.com'));
define('SMTP_FROM_NAME', env_or('SMTP_FROM_NAME', 'Decentralized Trust Attorneys'));

// ---- Site settings ----
define('SITE_NAME', 'Decentralized Trust Attorneys');
define('SITE_URL', env_or('SITE_URL', 'https://decenttrustattorneys.com')); // update to your live domain
define('SUPPORT_EMAIL', 'contact@decenttrustattorneys.com');
define('SUPPORT_PHONE', '(307) 555-0123');

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily while debugging
