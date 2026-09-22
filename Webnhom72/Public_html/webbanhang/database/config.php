<?php
$dbPassword = getenv('WEBBANHANG_DB_PASSWORD');
if ($dbPassword === false || $dbPassword === '') {
    error_log('[webbanhang] WEBBANHANG_DB_PASSWORD is not configured.');
    http_response_code(503);
    exit('Hệ thống chưa được cấu hình cơ sở dữ liệu.');
}

define('HOST', getenv('WEBBANHANG_DB_HOST') ?: 'localhost');
define('USERNAME', getenv('WEBBANHANG_DB_USER') ?: 'webbanhang_app');
define('PASSWORD', $dbPassword);
define('DATABASE', getenv('WEBBANHANG_DB_NAME') ?: 'webbanhang');

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

try {
    $conn = new mysqli(HOST, USERNAME, PASSWORD, DATABASE);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('[webbanhang] Lỗi kết nối DB: ' . $e->getMessage());
    http_response_code(503);
    exit('Lỗi kết nối hệ thống. Vui lòng thử lại sau.');
}