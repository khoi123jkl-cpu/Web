<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../../../Private/security/csrf.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức yêu cầu không được hỗ trợ.');
}
adminVerifyCsrf();
$logoutRole = (int)($_SESSION['role_id'] ?? 2);
$rememberToken = $_COOKIE['remember_token'] ?? '';
if ($rememberToken !== '') {
    require_once(__DIR__ . '/../../database/dbhelper.php');
    $conn = getDB();
    $tokenHash = hash('sha256', $rememberToken);
    $lookupToken = mysqli_prepare($conn, 'SELECT user_id FROM auth_tokens WHERE token_hash = ? LIMIT 1');
    mysqli_stmt_bind_param($lookupToken, 's', $tokenHash);
    mysqli_stmt_execute($lookupToken);
    mysqli_stmt_bind_result($lookupToken, $tokenUserId);
    $tokenFound = mysqli_stmt_fetch($lookupToken);
    mysqli_stmt_close($lookupToken);

    if ($tokenFound) {
        $deleteToken = mysqli_prepare($conn, 'DELETE FROM auth_tokens WHERE user_id = ?');
        mysqli_stmt_bind_param($deleteToken, 'i', $tokenUserId);
        mysqli_stmt_execute($deleteToken);
        mysqli_stmt_close($deleteToken);
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
header('Location: ' . ($logoutRole === 1 ? 'admin_login.php' : '../../utils/index.php'));
exit;