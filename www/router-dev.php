<?php
// Dev-only router for PHP's built-in server, replicating the mod_rewrite
// front-controller rule in .htaccess (PHP's built-in server ignores .htaccess).
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve the requested static file as-is
}

require __DIR__ . '/index.php';
