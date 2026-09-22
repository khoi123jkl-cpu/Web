<?php
    require_once(__DIR__ . '/../../../../Private/security/session.php');
    session_start();
    require_once('../../utils/utility.php');
    require_once('../../database/dbhelper.php');
    require_once(__DIR__ . '/../../../../Private/security/csrf.php');
    require_once(__DIR__ . '/../../../../Private/security/auth_throttle.php');
    require_once(__DIR__ . '/../../../../Private/security/mfa.php');

    $conn = getDB();
    $email = '';
    $error = '';
    $lockSeconds = 0;

    $rememberCookie = $_COOKIE['remember_token'] ?? '';
    if (!isset($_SESSION['user_id']) && $rememberCookie !== '') {
        $tokenHash = hash('sha256', $rememberCookie);
        $autoLogin = mysqli_prepare($conn, 'SELECT u.id, u.fullname, u.email, u.role_id, u.updated_at FROM auth_tokens t INNER JOIN `User` u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.deleted = 0 LIMIT 1');
        mysqli_stmt_bind_param($autoLogin, 's', $tokenHash);
        mysqli_stmt_execute($autoLogin);
        mysqli_stmt_bind_result($autoLogin, $id, $fullname, $userEmail, $roleId, $authUpdatedAt);

        if (mysqli_stmt_fetch($autoLogin)) {
            mysqli_stmt_close($autoLogin);
            authRotateRememberToken($conn, $id);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['email'] = $userEmail;
            $_SESSION['role_id'] = $roleId;
            $_SESSION['auth_updated_at'] = $authUpdatedAt;
            header('Location: ' . ((int)$roleId === 1 ? 'admin_login.php' : '../../utils/index.php'));
            exit;
        }
        mysqli_stmt_close($autoLogin);
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
            $error = 'Bạn đã thử đăng nhập sai quá 5 lần. Vui lòng đợi 60 giây rồi thử lại.';
            $lockSeconds = authThrottleRemainingSeconds($conn, $emailKey, $ipKey);
        } else {
            $query = mysqli_prepare($conn, 'SELECT id, fullname, email, password, role_id, updated_at FROM `User` WHERE email = ? AND deleted = 0 LIMIT 1');
            mysqli_stmt_bind_param($query, 's', $email);
            mysqli_stmt_execute($query);
            mysqli_stmt_bind_result($query, $id, $fullname, $userEmail, $passwordHash, $roleId, $authUpdatedAt);

            if (!mysqli_stmt_fetch($query)) {
                $error = 'Email hoặc mật khẩu không đúng.';
            }
            mysqli_stmt_close($query);

            $isValidPassword = $error === '' && password_verify($password, $passwordHash);
            if ($error === '' && !$isValidPassword) {
                $error = 'Email hoặc mật khẩu không đúng.';
            }

            if ($error === '' && (int)$roleId === 1) {
                $error = 'Tài khoản Admin cần đăng nhập tại trang quản trị.';
            }

            if ($error !== '') {
                authThrottleRecordFailure($conn, $emailKey, $ipKey);
                if (authThrottleIsBlocked($conn, $emailKey, $ipKey)) {
                    $error = 'Bạn đã thử đăng nhập sai quá 5 lần. Vui lòng đợi 60 giây rồi thử lại.';
                    $lockSeconds = authThrottleRemainingSeconds($conn, $emailKey, $ipKey);
                }
            } else {
                authThrottleClear($conn, $emailKey, $ipKey);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $userEmail;
                $_SESSION['role_id'] = $roleId;
                $_SESSION['auth_updated_at'] = $authUpdatedAt;

                if (isset($_POST['remember'])) {
                    authRotateRememberToken($conn, $id);
                }

                header('Location: ../../utils/index.php');
                exit;
            }
        }
    }
?>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User | Đăng nhập và đăng ký</title>
    <link rel="stylesheet" href="../../assets/css/loginregister.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <main class="auth-shell" id="authShell">
        <section class="auth-form login-form" aria-label="Đăng nhập">
            <div class="form-content">
                <span class="eyebrow">Chào mừng trở lại</span>
                <h1>Đăng nhập</h1>
                <p class="form-intro">Đăng nhập để tiếp tục quản lý tài khoản của bạn.</p>
                <?php if ($error !== ''): ?><p class="login-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><?php if ($lockSeconds > 0): ?> <span class="login-countdown" data-login-countdown data-seconds="<?php echo $lockSeconds; ?>"></span><?php endif; ?></p><?php endif; ?>
                <div class="social-links" aria-label="Đăng nhập bằng mạng xã hội">
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="Google"><i class="fa-brands fa-google"></i></a>
                    <a href="#" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
                </div>
                <span class="divider"><span>hoặc đăng nhập bằng email</span></span>
                <form action="loginregister.php" method="POST">
                    <?php echo adminCsrfField(); ?>
                    <label for="login-email">Email</label>
                    <div class="input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="login-email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" required>
                    </div>
                    <label for="login-password">Mật khẩu</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="login-password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                    <div class="form-options">
                        <label class="check-label"><input type="checkbox" name="remember"> <span>Ghi nhớ tôi</span></label>
                        <a href="#">Quên mật khẩu?</a>
                    </div>
                    <button class="primary-button" data-login-submit type="submit"<?php echo $lockSeconds > 0 ? ' disabled' : ''; ?>>Đăng nhập <i class="fa-solid fa-arrow-right"></i></button>
                </form>
                <p class="mobile-switch">Chưa có tài khoản? <button type="button" data-show="register">Đăng ký</button></p>
            </div>
        </section>

        <section class="auth-form register-form" aria-label="Đăng ký">
            <div class="form-content">
                <span class="eyebrow">Bắt đầu hành trình</span>
                <h1>Tạo tài khoản</h1>
                <p class="form-intro">Điền thông tin bên dưới để tham gia cùng chúng tôi.</p>
                <form action="process_form_register.php" method="POST">
                    <?php echo adminCsrfField(); ?>
                    <label for="fullname">Họ và tên</label>
                    <div class="input-wrap"><i class="fa-regular fa-user"></i><input type="text" id="fullname" name="fullname" placeholder="Nguyễn Văn A" required></div>
                    <label for="register-email">Email</label>
                    <div class="input-wrap"><i class="fa-regular fa-envelope"></i><input type="email" id="register-email" name="email" placeholder="you@example.com" required></div>
                    <div class="field-row">
                        <div><label for="address">Số điện thoại</label><div class="input-wrap"><i class="fa-solid fa-phone"></i><input type="text" id="address" name="address" placeholder="0123 456 789"></div></div>
                        <div><label for="birthday">Ngày sinh</label><div class="input-wrap"><i class="fa-regular fa-calendar"></i><input type="date" id="birthday" name="birthday"></div></div>
                    </div>
                    <label for="pwd">Mật khẩu</label>
                    <div class="input-wrap"><i class="fa-solid fa-lock"></i><input type="password" id="pwd" name="password" placeholder="Tối thiểu 8 ký tự" required></div>
                    <label for="confirmation_pwd">Xác nhận mật khẩu</label>
                    <div class="input-wrap"><i class="fa-solid fa-shield-halved"></i><input type="password" id="confirmation_pwd" name="confirmation_pwd" placeholder="Nhập lại mật khẩu" required></div>
                    <label class="check-label terms"><input type="checkbox" name="terms" required> <span>Tôi đồng ý với các điều khoản sử dụng</span></label>
                    <button class="primary-button" type="submit">Đăng ký <i class="fa-solid fa-arrow-right"></i></button>
                </form>
                <p class="mobile-switch">Đã có tài khoản? <button type="button" data-show="login">Đăng nhập</button></p>
            </div>
        </section>

        <aside class="welcome-panel">
            <div class="welcome-content welcome-register">
                <i class="welcome-icon fa-solid fa-user-plus"></i>
                <span class="eyebrow">Xin chào bạn</span>
                <h2>Để đây để test giao diện</h2>
                <p>Tham gia ngay để mở khóa toàn bộ tiện ích dành riêng cho bạn.</p>
                <button class="outline-button" type="button" data-show="register">Tạo tài khoản <i class="fa-solid fa-arrow-right"></i></button>
            </div>
            <div class="welcome-content welcome-login">
                <i class="welcome-icon fa-solid fa-right-to-bracket"></i>
                <span class="eyebrow">Rất vui được gặp lại</span>
                <h2>Chào mừng<br>trở lại.</h2>
                <p>Đăng nhập để kết nối với không gian của riêng bạn.</p>
                <button class="outline-button" type="button" data-show="login"><i class="fa-solid fa-arrow-left"></i> Đăng nhập</button>
            </div>
        </aside>
    </main>
    <script src="../../assets/js/loginregister.js"></script>
    <?php if ($lockSeconds > 0): ?><script src="../../assets/js/login-countdown.js" defer></script><?php endif; ?>
</body>
</html>