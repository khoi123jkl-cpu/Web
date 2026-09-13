<?php
require_once('../../../Private/security/session.php');
session_start();
require_once('../../utils/utility.php');
require_once('../../database/dbhelper.php');
require_once('../../../Private/security/csrf.php');

$conn = getDB();
$error = '';

$rememberCookie = $_COOKIE['remember_token'] ?? '';
if (!isset($_SESSION['user_id']) && $rememberCookie !== '') {
    $tokenHash = hash('sha256', $rememberCookie);
    $statement = mysqli_prepare($conn, 'SELECT u.id, u.fullname, u.email, u.role_id FROM auth_tokens t INNER JOIN `User` u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.deleted = 0 AND u.role_id = 1 LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $tokenHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $fullname, $email, $roleId);
    if (mysqli_stmt_fetch($statement)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;
        $_SESSION['role_id'] = $roleId;
        mysqli_stmt_close($statement);
        header('Location: ../index.php');
        exit;
    }
    mysqli_stmt_close($statement);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Email hoặc mật khẩu không hợp lệ.';
    } else {
        $statement = mysqli_prepare($conn, 'SELECT id, fullname, email, password, role_id FROM `User` WHERE email = ? AND deleted = 0 LIMIT 1');
        mysqli_stmt_bind_param($statement, 's', $email);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $id, $fullname, $userEmail, $passwordHash, $roleId);

        if (!mysqli_stmt_fetch($statement)) {
            $error = 'Email hoặc mật khẩu không đúng.';
        }
        mysqli_stmt_close($statement);

        if ($error === '' && (int)$roleId !== 1) {
            $error = 'Tài khoản này không có quyền quản trị.';
        }

        $isValidPassword = $error === '' && password_verify($password, $passwordHash);

        if ($error === '' && !$isValidPassword) {
            $error = 'Email hoặc mật khẩu không đúng.';
        }

        if ($error === '') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['email'] = $userEmail;
            $_SESSION['role_id'] = 1;

            if (isset($_POST['remember'])) {
                $rememberToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rememberToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
                $createdAt = date('Y-m-d H:i:s');
                $tokenStatement = mysqli_prepare($conn, 'INSERT INTO auth_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($tokenStatement, 'isss', $id, $tokenHash, $expiresAt, $createdAt);
                mysqli_stmt_execute($tokenStatement);
                mysqli_stmt_close($tokenStatement);
                setcookie('remember_token', $rememberToken, [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => APP_FORCE_HTTPS,
                    'samesite' => 'Lax'
                ]);
            }

            header('Location: ../index.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang đăng nhập | KhoaiLaptop</title>
    <link rel="stylesheet" href="../../assets/css/adminlogin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <form action="admin_login.php" method="post">
                <?php echo adminCsrfField(); ?>
                <p class="login-eyebrow">KHU VỰC QUẢN TRỊ</p>
                <h2 class="login-title">Đăng nhập Admin</h2>
                <?php if ($error !== ''): ?><p class="login-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

                <div class="input-box">
                    <span class="icon"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" required autocomplete="email">
                    <label>Email</label>
                </div>

                <div class="input-box">
                    <span class="icon"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" required autocomplete="current-password">
                    <label>Mật khẩu</label>
                </div>

                <div class="remember-forgot-box">
                    <label><input type="checkbox" name="remember"> Ghi nhớ tôi</label>
                    <a href="#">Quên mật khẩu?</a>
                </div>

                <button class="login-btn" type="submit">Đăng nhập</button>
                <p class="register"><a href="loginregister.php">Đăng nhập user thường</a></p>

            </form>
        </div>
    </div>
</body>
</html>