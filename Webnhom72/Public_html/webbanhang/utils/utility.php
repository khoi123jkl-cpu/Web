<?php

function fixSqlInject($sql) {
    $sql = str_replace('\\','\\\\', $sql);
    $sql = str_replace('\'','\\\'', $sql);
    return $sql;
}

function getGet($key) {
    $value = '';
    if(isset($_GET[$key])) {
        $value = $_GET[$key];
        $value = fixSqlInject($value);
    }
    return trim($value);
}

function getPost($key) {
    $value = '';
    if(isset($_POST[$key])) {
        $value = $_POST[$key];
        $value = fixSqlInject($value);
    }
    return trim($value);
}

function getCookie($key) {
    $value = '';
    if(isset($_COOKIE[$key])) {
        $value = $_COOKIE[$key];
        $value = fixSqlInject($value);
    }
    return trim($value);
}

function validateToken($token) {
    if($_SSESSION['user']) {
        return $_SESSION['user'];
    }
    $token = getCookie('token');
    $sql = "select * from Tokens where token = '$token'";
    $item = executeResult($sql, true);
    if($item != null) {
        $userId = $item['user_id'];
        $sql = "select * from User where id = '$userId'";
        $user = executeResult($sql, true);
        if($item != null) {
            $_SESSION['user'] = $item;
            return $item;
        }
    }
    return null;
}