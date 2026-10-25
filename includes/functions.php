<?php
/**
 * Sanitize user input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if current page matches the given page name
 */
function isActivePage($path) {
    return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
}

/**
 * Format price for display
 */
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

/**
 * Is this request running over HTTPS?
 *
 * Checks X-Forwarded-Proto as well — behind Hostinger's load balancer the
 * PHP process can see a plain-HTTP connection even though the browser is on
 * TLS, and treating that as insecure would drop the session cookie.
 */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Root-relative URL for a local asset, stamped with its modified time.
 *
 * The CDN tells browsers to keep CSS/JS for a week, so without the stamp a
 * deploy goes unseen by returning visitors (new markup, stale styles).
 * Root-relative rather than SITE_URL so www and the bare domain both load
 * it as same-origin — the CSP only allows 'self'.
 */
function asset(string $path): string {
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../' . $path;
    return '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
