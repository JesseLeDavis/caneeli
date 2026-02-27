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
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page ? 'active' : '';
}

/**
 * Format price for display
 */
function formatPrice($price) {
    return '$' . number_format($price, 2);
}
