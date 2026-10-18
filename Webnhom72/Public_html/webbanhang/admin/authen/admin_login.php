<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../utils/utility.php');
require_once(__DIR__ . '/../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/auth_throttle.php');
require_once(__DIR__ . '/../../../../Private/security/mfa.php');

$conn = getDB();
$email = '';
$password = '';
$error = '';
$lockSeconds = 0;
$mfaError = '';

$rememberCookie = $_COOKIE['remember_token'] ?? '';
if (!isset($_SESSION['user_id']) && $rememberCookie !== '') {
    $tokenHash = hash('sha256', $rememberCookie);
    $statement = mysqli_prepare($conn, 'SELECT u.id, u.fullname, u.email, u.role_id, u.updated_at FROM auth_tokens t INNER JOIN `user` u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.deleted = 0 AND u.role_id = 1 LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $tokenHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $fullname, $email, $roleId, $authUpdatedAt);
    if (mysqli_stmt_fetch($statement)) {
        mysqli_stmt_close($statement);
        authRotateRememberToken($conn, $id);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;
        $_SESSION['role_id'] = $roleId;
        $_SESSION['auth_updated_at'] = $authUpdatedAt;
        header('Location: ../index.php');
        exit;
    } else {
        mysqli_stmt_close($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailKey = authThrottleEmailKey($email);
    $ipKey = authThrottleIpKey();

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Email hoặc mật khẩu không hợp lệ.';
    } elseif (authThrottleIsBlocked($conn, $emailKey, $ipKey)) {
        $error = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 60 giây.';
        $lockSeconds = authThrottleRemainingSeconds($conn, $emailKey, $ipKey);
    } else {
        try {
            $statement = mysqli_prepare($conn, 'SELECT id, fullname, email, password, role_id, updated_at FROM `user` WHERE email = ? AND deleted = 0 LIMIT 1');
            mysqli_stmt_bind_param($statement, 's', $email);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $id, $fullname, $userEmail, $passwordHash, $roleId, $authUpdatedAt);

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

            if ($error !== '') {
                authThrottleRecordFailure($conn, $emailKey, $ipKey);
                if (authThrottleIsBlocked($conn, $emailKey, $ipKey)) {
                    $error = 'Bạn đã thử đăng nhập sai quá 5 lần. Vui lòng đợi 60 giây rồi thử lại.';
                    $lockSeconds = authThrottleRemainingSeconds($conn, $emailKey, $ipKey);
                }
            }

            if ($error === '') {
                $mfa = mfaGetUser($conn, $id);
                if ($mfa !== null && $mfa['enabled']) {
                    $_SESSION['mfa_pending'] = [
                        'user_id' => $id,
                        'fullname' => $fullname,
                        'email' => $userEmail,
                        'role_id' => 1,
                        'auth_updated_at' => $authUpdatedAt,
                        'remember' => isset($_POST['remember'])
                    ];
                    header('Location: mfa_verify.php');
                    exit;
                }

                authThrottleClear($conn, $emailKey, $ipKey);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $userEmail;
                $_SESSION['role_id'] = 1;
                $_SESSION['auth_updated_at'] = $authUpdatedAt;

                if (isset($_POST['remember'])) {
                    authRotateRememberToken($conn, $id);
                }

                header('Location: ../index.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[webbanhang] Lỗi admin login: ' . $e->getMessage());
            $error = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage() . '. Vui lòng kiểm tra đã Import file webbanhang.sql lên phpMyAdmin chưa.';
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
                <?php if ($error !== ''): ?><p class="login-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><?php if ($lockSeconds > 0): ?> <span class="login-countdown" data-login-countdown data-seconds="<?php echo $lockSeconds; ?>"></span><?php endif; ?></p><?php endif; ?>

                <div class="input-box">
                    <span class="icon"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="email">
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

                <button class="login-btn" data-login-submit type="submit"<?php echo $lockSeconds > 0 ? ' disabled' : ''; ?>>Đăng nhập</button>
                <p class="register"><a href="loginregister.php">Đăng nhập user thường</a></p>

            </form>
        </div>
    </div>
<?php if ($lockSeconds > 0): ?><script src="../../assets/js/login-countdown.js" defer></script><?php endif; ?>
</body>
</html>