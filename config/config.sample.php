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
//
// IMPORTANT — SMTP_FROM below is a placeholder on a fake, non-existent
// domain (.test is reserved by RFC 2606 and never resolves in real DNS).
// It works fine with Ethereal for local testing, but if you go live with
// it unchanged, every real SMTP server will reject every email with
// "Sender address rejected: Domain not found". Change it to a real email
// address on a domain you actually own before deploying for real use.
// Also note: once anything on this page has ever been saved via
// /admin/smtp_settings.php, THAT saved value — not this file — is what's
// actually used; edit it there instead, or use that page's "Reset to
// config.php defaults" button first if you want this file to take effect.
define('SMTP_HOST', 'smtp.ethereal.email');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-ethereal-username@ethereal.email');
define('SMTP_PASS', 'your-ethereal-password');
define('SMTP_FROM', 'tracking@swiftcargo.test'); // CHANGE to a real address on a real domain before going live
define('SMTP_FROM_NAME', 'SwiftCargo Tracking');
define('SMTP_SECURE', 'tls'); // 'tls' for port 587, 'ssl' for port 465

// ---- Site ----
// SITE_NAME here is only a fallback for the very first page load — the real
// site name is set in the admin panel under Branding, which overrides this.
define('SITE_NAME', 'SwiftCargo');

// SITE_URL is THE ONE PLACE the site's own web address is configured. It is
// used to build absolute links that leave the site: tracking links and
// password-reset links in emails, the "track your shipment at ..." line on
// the waybill PDF, and the URLs in sitemap.php.
//
// Set this to your live domain, with https:// and no trailing slash, e.g.
//   define('SITE_URL', 'https://yourdomain.com');
//
// Leaving it blank makes the site auto-detect its address from each request,
// which is handy for a quick test deploy but is NOT recommended once real
// people use the site: the detected address comes from the request's own
// Host header, which an attacker can forge to poison a password-reset link
// (see get_site_url() in includes/functions.php). Set it explicitly and
// that risk disappears entirely.
//
// Moving the site to a new domain later? This line is the only thing to
// change — nothing else in the codebase hardcodes a domain.
define('SITE_URL', '');
