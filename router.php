<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($path ?? '/');

if ($path === '' || $path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

$resolvedPath = __DIR__ . $path;

if (is_file($resolvedPath)) {
    return false;
}

if (is_file($resolvedPath . '.php')) {
    require $resolvedPath . '.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
