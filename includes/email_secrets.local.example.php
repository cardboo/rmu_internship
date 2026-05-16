<?php
/**
 * SMTP credentials — example.
 *
 * Copy this file to:
 *     includes/email_secrets.local.php
 * (note the ".local" suffix) and fill in real values. The local copy
 * is gitignored so secrets never land in commit history.
 *
 * Anything you define() here overrides the defaults in
 * includes/email.php. You only need SMTP_USER and SMTP_PASS — the
 * rest already default to Brevo on port 2525.
 */

// ---------- Required ----------
define('SMTP_USER', 'your-brevo-account@example.com');
define('SMTP_PASS', 'xsmtpsib-your-brevo-smtp-key-here');

// ---------- Optional overrides ----------
// define('SMTP_HOST',         'smtp-relay.brevo.com');
// define('SMTP_PORT',         2525);
// define('SMTP_SECURE',       'tls');           // 'tls' or 'ssl'
// define('SMTP_FROM_ADDRESS', 'isabdulaisaiku@gmail.com');
// define('SMTP_FROM_NAME',    'RMU Internship Portal');
