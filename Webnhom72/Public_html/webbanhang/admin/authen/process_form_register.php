<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/users_otp.php');
require_once(__DIR__ . '/../../database/dbhelper.php');

function registerFail($message, $fullname = '', $email = '', $phone = '', $birthday = '') {
    $_SESSION['register_error'] = $message;
    $_SESSION['register_fullname'] = $fullname;
    $_SESSION['register_email'] = $email;
    $_SESSION['register_phone'] = $phone;
    $_SESSION['register_birthday'] = $birthday;
    header('Location: loginregister.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: loginregister.php');
    exit;
}

adminVerifyCsrf();

$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['address'] ?? $_POST['phone'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$password = $_POST['password'] ?? '';
$confirmationPassword = $_POST['confirmation_pwd'] ?? '';

if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    registerFail('Vui lòng nhập họ tên và địa chỉ email hợp lệ.', $fullname, $email, $phone, $birthday);
}

if (strlen($password) < 8) {
    registerFail('Mật khẩu phải có ít nhất 8 ký tự.', $fullname, $email, $phone, $birthday);
}

if ($password !== $confirmationPassword) {
    registerFail('Mật khẩu và xác nhận mật khẩu không trùng khớp.', $fullname, $email, $phone, $birthday);
}

$conn = getDB();

try {
    $check = mysqli_prepare($conn, 'SELECT id FROM `user` WHERE email = ? AND deleted = 0 LIMIT 1');
    mysqli_stmt_bind_param($check, 's', $email);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
        mysqli_stmt_close($check);
        registerFail('Email này đã được đăng ký trên hệ thống. Vui lòng đăng nhập hoặc dùng email khác.', $fullname, $email, $phone, $birthday);
    }
    mysqli_stmt_close($check);

    // Thông tin hợp lệ -> Tạo mã OTP 6 số và tự động gửi về email của người dùng
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $otpCode = userOtpGenerateCode();
    $sent = userOtpSendEmail($email, $otpCode, 'register', $fullname);

    $_SESSION['user_auth_pending'] = [
        'action'          => 'register',
        'fullname'        => $fullname,
        'email'           => $email,
        'phone'           => $phone,
        'birthday'        => $birthday,
        'password_hash'   => $passwordHash,
        'otp_code'        => $otpCode,
        'otp_expires'     => time() + 300, // 5 phút
        'resend_cooldown' => time() + 60, // 60 giây
        'attempts'        => 0,
        'mail_sent'       => $sent,
        'dev_otp'         => $otpCode
    ];

    header('Location: users_authentication.php');
    exit;
} catch (Throwable $e) {
    error_log('[webbanhang] Lỗi tạo tài khoản: ' . $e->getMessage());
    registerFail('Đăng ký thất bại do lỗi hệ thống: ' . $e->getMessage(), $fullname, $email, $phone, $birthday);
}