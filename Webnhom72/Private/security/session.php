<?php
require_once(__DIR__ . '/../env_loader.php');
require_once(__DIR__ . '/csp.php');

define('APP_FORCE_HTTPS', appEnv('WEBBANHANG_FORCE_HTTPS') !== '0');

$requestHostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
$requestHost = strtolower((string)preg_replace('/:\d+$/', '', $requestHostHeader));
$developmentHosts = ['localhost', '127.0.0.1', '[::1]'];
$isCloudflareTunnel = (bool)preg_match('/^[a-z0-9-]+\.trycloudflare\.com$/', $requestHost);
if ($isCloudflareTunnel) {
    $developmentHosts[] = $requestHost;
}

$isInfinityFree = (bool)preg_match('/^[a-z0-9-]+\.(infinityfreeapp\.com|epizy\.com|rf\.gd|great-site\.net)$/', $requestHost);

$configuredHosts = appEnv('APP_ALLOWED_HOSTS') ?: appEnv('APP_DOMAIN') ?: 'webbanhang.local';
$productionHosts = array_values(array_filter(array_map(
    static function ($host) {
        return strtolower(trim($host));
    },
    explode(',', $configuredHosts)
)));
if ($isInfinityFree) {
    $productionHosts[] = $requestHost;
}
$allowedHosts = array_unique(array_merge($developmentHosts, $productionHosts));

if ($requestHost === '' || !in_array($requestHost, $allowedHosts, true)) {
    http_response_code(400);
    exit('Invalid host.');
}

$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$trustProxy = appEnv('APP_TRUST_PROXY') === '1' || $isCloudflareTunnel || $isInfinityFree;
if (!$isHttps && $trustProxy) {
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0]));
    $isHttps = $forwardedProto === 'https';
}

if (APP_FORCE_HTTPS && !$isHttps && !in_array($requestHost, $developmentHosts, true)) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $requestHostHeader . $requestUri, true, 301);
    exit;
}

if ($isHttps && !in_array($requestHost, $developmentHosts, true)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
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