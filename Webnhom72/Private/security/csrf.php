<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function adminCsrfToken()
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function adminCsrfField()
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function adminVerifyCsrf()
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken) ||
        $submittedToken === '' || $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}
