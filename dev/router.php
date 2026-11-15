<?php
/**
 * Router for PHP's built-in server (dev/serve-test.sh), standing in for the
 * production .htaccess: clean URLs map to .php files, and private folders
 * stay private.
 */
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Same folders .htaccess forbids, plus this one.
if (preg_match('#^/(config|includes|database|vendor|design|dev|node_modules)(/|$)|/\.(env|git)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/robots.txt')  { require $root . '/robots.php';  return true; }
if ($path === '/sitemap.xml') { require $root . '/sitemap.php'; return true; }

$file = $root . $path;

// Real files (CSS, images, .php) are served as-is.
if (is_file($file)) {
    return false;
}

// Directories fall through to their index.php.
if (is_dir($file)) {
    if (is_file(rtrim($file, '/') . '/index.php')) {
        return false;
    }
}

// /pages/shop/product → /pages/shop/product.php
if (is_file($file . '.php')) {
    $_SERVER['SCRIPT_NAME'] = $path . '.php';
    $_SERVER['SCRIPT_FILENAME'] = $file . '.php';
    chdir(dirname($file . '.php'));
    require $file . '.php';
    return true;
}

http_response_code(404);
if (is_file($root . '/error.php')) {
    require $root . '/error.php';
}
return true;
