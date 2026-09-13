<?php
require_once('../../Private/security/session.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: authen/admin_login.php');
    exit;
}

if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ../index.php');
    exit;
}

header('Location: user/index.php');
exit;
