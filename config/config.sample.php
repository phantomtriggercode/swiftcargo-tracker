<?php
/**
 * Copy this file to config.php and fill in your real credentials.
 * config.php is git-ignored — never commit real passwords.
 */

// ---- Database (Hostinger: hPanel > Databases > MySQL Databases) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_swiftcargo');
define('DB_USER', 'u123456789_swiftcargo');
define('DB_PASS', 'CHANGE_ME');

// ---- Outgoing mail (SMTP — no third-party email API used) ----
// For local testing: create a free throwaway inbox at https://ethereal.email
// and paste the generated host/username/password below. Every email sent
// will NOT reach a real inbox — view it at https://ethereal.email/messages
// using the same login. Swap these for real SMTP (e.g. your Hostinger
// mailbox, smtp.hostinger.com) when you go live.
define('SMTP_HOST', 'smtp.ethereal.email');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-ethereal-username@ethereal.email');
define('SMTP_PASS', 'your-ethereal-password');
define('SMTP_FROM', 'tracking@swiftcargo.test');
define('SMTP_FROM_NAME', 'SwiftCargo Tracking');
define('SMTP_SECURE', 'tls'); // 'tls' for port 587, 'ssl' for port 465

// ---- Site ----
define('SITE_NAME', 'SwiftCargo');
define('SITE_URL', 'http://localhost/swiftcargo-tracker');
