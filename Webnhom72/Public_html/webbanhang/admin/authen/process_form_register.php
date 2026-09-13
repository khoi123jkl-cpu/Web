<?php
require_once('../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: loginregister.php');
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmationPassword = $_POST['confirmation_pwd'] ?? '';

if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Vui lòng nhập họ tên và email hợp lệ.');
}

if (strlen($password) < 8 || $password !== $confirmationPassword) {
    exit('Mật khẩu phải có ít nhất 8 ký tự và trùng khớp.');
}

$conn = getDB();
$check = mysqli_prepare($conn, 'SELECT id FROM `User` WHERE email = ? AND deleted = 0 LIMIT 1');
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    exit('Email đã được đăng ký trên hệ thống.');
}
mysqli_stmt_close($check);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$createdAt = date('Y-m-d H:i:s');
$result = mysqli_query($conn, 'SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `User`');
$newUserId = (int)mysqli_fetch_assoc($result)['next_id'];
mysqli_free_result($result);
$insert = mysqli_prepare($conn, 'INSERT INTO `User` (id, fullname, email, password, role_id, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, 2, ?, ?, 0)');
mysqli_stmt_bind_param($insert, 'isssss', $newUserId, $fullname, $email, $passwordHash, $createdAt, $createdAt);

if (!mysqli_stmt_execute($insert)) {
    mysqli_stmt_close($insert);
    exit('Không thể tạo tài khoản. Vui lòng thử lại.');
}

mysqli_stmt_close($insert);
$_SESSION['user_id'] = $newUserId;
$_SESSION['fullname'] = $fullname;
$_SESSION['email'] = $email;
$_SESSION['role_id'] = 2;
header('Location: ../../utils/index.php');
exit;