<?php
require_once('config.php');

//connection
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect(HOST, USERNAME, PASSWORD, DATABASE);
        if (!$conn) {
            die('Connection failed: ' . mysqli_connect_error());
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

//INSERT, UPDATE, DELETE
function execute($sql) {
    $conn = getDB();
    return mysqli_query($conn, $sql);
}

// SELECT lay data
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