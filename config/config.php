<?php
// Site Configuration
define('SITE_NAME', 'Caneeli');
define('SITE_URL', 'http://localhost:8000'); // Update this for production

// Load .env
$env = parse_ini_file(__DIR__ . '/../.env');

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

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
