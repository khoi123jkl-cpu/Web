<?php
if (!function_exists('appEnv')) {
    $searchPaths = [
        dirname(__DIR__, 3) . '/Private/env_loader.php',
        dirname(__DIR__, 2) . '/Private/env_loader.php',
        dirname(__DIR__, 1) . '/Private/env_loader.php',
        __DIR__ . '/../../../Private/env_loader.php',
        __DIR__ . '/../../Private/env_loader.php',
        __DIR__ . '/../Private/env_loader.php',
    ];
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }
    if (!function_exists('appEnv')) {
        function appEnv($key, $default = null) {
            $val = getenv($key);
            return ($val !== false && $val !== '') ? $val : $default;
        }
    }
}

$hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
$cleanHost = strtolower((string)preg_replace('/:\d+$/', '', $hostHeader));
$isLocalDev = ($cleanHost === '' || in_array($cleanHost, ['localhost', '127.0.0.1', '::1'], true) || PHP_SAPI === 'cli');

$defaultUser = $isLocalDev ? 'root' : 'webbanhang_app';
$defaultPass = $isLocalDev ? '' : null;

$dbPassword = appEnv('WEBBANHANG_DB_PASSWORD', $defaultPass);
if ($dbPassword === null && !$isLocalDev) {
    error_log('[webbanhang] WEBBANHANG_DB_PASSWORD is not configured.');
    http_response_code(503);
    exit('Hệ thống chưa được cấu hình cơ sở dữ liệu.');
}

$dbHost = ($isLocalDev && appEnv('WEBBANHANG_DB_HOST') !== 'localhost') ? 'localhost' : appEnv('WEBBANHANG_DB_HOST', 'localhost');
$dbUser = ($isLocalDev && appEnv('WEBBANHANG_DB_HOST') !== 'localhost') ? 'root' : appEnv('WEBBANHANG_DB_USER', $defaultUser);
$dbPass = ($isLocalDev && appEnv('WEBBANHANG_DB_HOST') !== 'localhost') ? '' : (string)($dbPassword ?? '');
$dbName = ($isLocalDev && appEnv('WEBBANHANG_DB_HOST') !== 'localhost') ? 'webbanhang' : appEnv('WEBBANHANG_DB_NAME', 'webbanhang');

define('HOST', $dbHost);
define('USERNAME', $dbUser);
define('PASSWORD', $dbPass);
define('DATABASE', $dbName);

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

try {
    $conn = new mysqli(HOST, USERNAME, PASSWORD, DATABASE);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('[webbanhang] Lỗi kết nối DB: ' . $e->getMessage());
    http_response_code(503);
    exit('Lỗi kết nối hệ thống. Vui lòng thử lại sau.');
}