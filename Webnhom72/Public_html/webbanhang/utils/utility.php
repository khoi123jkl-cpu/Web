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
