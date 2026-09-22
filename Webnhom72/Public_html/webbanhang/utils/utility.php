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
    if(isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    $token = getCookie('token');
    if (!$token) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE token = :token');
    $stmt->execute(['token' => $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tokenData) {
        $userId = $tokenData['id'];
        $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user'] = $user;
            return $user;
        }
    }
    return null;
}
