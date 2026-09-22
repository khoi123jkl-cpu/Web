<?php
require_once('config.php');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect(HOST, USERNAME, PASSWORD, DATABASE);
        if (!$conn) {
            error_log('[webbanhang] Database connection failed: ' . mysqli_connect_error());
            http_response_code(503);
            exit('Hệ thống đang tạm thời gián đoạn. Vui lòng thử lại sau.');
        }

        if (!mysqli_set_charset($conn, 'utf8mb4')) {
            error_log('[webbanhang] Failed to set database character set: ' . mysqli_error($conn));
            mysqli_close($conn);
            $conn = null;
            http_response_code(503);
            exit('Hệ thống đang tạm thời gián đoạn. Vui lòng thử lại sau.');
        }
    }
    return $conn;
}

function execute($sql) {
    $conn = getDB();
    return mysqli_query($conn, $sql);
}

function executeResult($sql, $isSingle = false) {
    $conn = getDB();
    $resultset = mysqli_query($conn, $sql);

    if (!$resultset) {
        return false;
    }

    if ($isSingle) {
        $data = mysqli_fetch_assoc($resultset);
    } else {
        $data = [];
        while ($row = mysqli_fetch_assoc($resultset)) {
            $data[] = $row;
        }
    }

    mysqli_free_result($resultset);
    return $data;
}