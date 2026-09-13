<?php
define('APP_FORCE_HTTPS', true);

if (APP_FORCE_HTTPS &&
    (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') &&
    isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => APP_FORCE_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
