<?php
// Load .env
$env = parse_ini_file(__DIR__ . '/../.env');

// Site Configuration
define('SITE_NAME', 'Caneeli');
define('SITE_URL', $env['SITE_URL'] ?? 'http://localhost:8000');

// Database Configuration
define('DB_HOST', $env['DB_HOST']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);

// Admin Credentials
define('ADMIN_USER', $env['ADMIN_USER']);
define('ADMIN_PASS', $env['ADMIN_PASS']);

// Stripe
define('STRIPE_PUBLIC_KEY', $env['STRIPE_PUBLIC_KEY']);
define('STRIPE_SECRET_KEY', $env['STRIPE_SECRET_KEY']);
define('STRIPE_WEBHOOK_SECRET', $env['STRIPE_WEBHOOK_SECRET']);

// Email (SMTP) — used by includes/mailer.php for order + shipping emails.
// Missing values fall back to empty; the mailer skips sending (and logs) if
// SMTP_HOST is blank, so local dev without mail config won't error.
define('SMTP_HOST',       $env['SMTP_HOST']       ?? '');
define('SMTP_PORT',       (int) ($env['SMTP_PORT'] ?? 587));
define('SMTP_USER',       $env['SMTP_USER']       ?? '');
define('SMTP_PASS',       $env['SMTP_PASS']       ?? '');
define('SMTP_SECURE',     $env['SMTP_SECURE']     ?? 'tls'); // 'tls' (587) or 'ssl' (465)
define('MAIL_FROM_EMAIL', $env['MAIL_FROM_EMAIL'] ?? ($env['SMTP_USER'] ?? ''));
define('MAIL_FROM_NAME',  $env['MAIL_FROM_NAME']  ?? SITE_NAME);
define('OWNER_EMAIL',     $env['OWNER_EMAIL']     ?? ''); // BCC'd on order confirmations

// Error reporting — display errors only in local dev
$is_local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
error_reporting(E_ALL);
ini_set('display_errors', $is_local ? 1 : 0);
ini_set('log_errors', 1);
