<?php
define('APP_FORCE_HTTPS', true);

// ----------------------------------------------------------------------
// 1. NHẬN DIỆN HTTPS AN TOÀN (Tương thích Reverse Proxy / Load Balancer)
// ----------------------------------------------------------------------
$isHttps = false;

if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
    $isHttps = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $isHttps = true;
    $_SERVER['HTTPS'] = 'on'; 
}


if (APP_FORCE_HTTPS && !$isHttps && isset($_SERVER['HTTP_HOST'],$_SERVER['REQUEST_URI'])) {
    $requestHost = strtolower((string)$_SERVER['HTTP_HOST']);
    $requestHost = preg_replace('/:\d+$/', '', $requestHost);$developmentHosts = ['localhost', '127.0.0.1', '[::1]'];
    $productionHosts = defined('APP_ALLOWED_HOSTS') ? APP_ALLOWED_HOSTS : [getenv('APP_DOMAIN') ?: 'webbanhang.local'];

    if (in_array($requestHost,$productionHosts, true)) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] .$_SERVER['REQUEST_URI'], true, 301);
        exit;
    }

    if (!in_array($requestHost,$developmentHosts, true)) {
        http_response_code(400);
        exit('Invalid host.');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function authSessionIsValid($conn)
{
    if (!isset($_SESSION['user_id'],$_SESSION['auth_updated_at'])) {
        return false;
    }

    $userId = (int)$_SESSION['user_id'];
    $statement = mysqli_prepare($conn, 'SELECT fullname, email, role_id, updated_at FROM `user` WHERE id = ? AND deleted = 0 LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i',$userId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $fullname,$email, $roleId,$updatedAt);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$found || (string)$updatedAt !== (string)$_SESSION['auth_updated_at']) {
        return false;
    }

    $_SESSION['fullname'] =$fullname;
    $_SESSION['email'] =$email;
    $_SESSION['role_id'] = (int)$roleId;
    return true;
}

function authRequireUser($conn,$loginLocation = '../admin/authen/loginregister.php')
{
    if (!authSessionIsValid($conn)) {
        authInvalidateSession();
        header('Location: ' . $loginLocation);
        exit;
    }
}

function authInvalidateSession()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'],$params['domain'], $params['secure'],$params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function authRequireAdmin($conn,$loginLocation = '../authen/admin_login.php')
{
    if (!authSessionIsValid($conn) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
        authInvalidateSession();
        header('Location: ' . $loginLocation);
        exit;
    }
}