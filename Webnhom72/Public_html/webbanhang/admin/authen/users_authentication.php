<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/auth_throttle.php');
require_once(__DIR__ . '/../../../../Private/security/users_otp.php');
require_once(__DIR__ . '/../../database/dbhelper.php');

$pending = $_SESSION['user_auth_pending'] ?? null;
if (!is_array($pending) || empty($pending['email']) || empty($pending['action'])) {
    header('Location: loginregister.php');
    exit;
}

$conn = getDB();
$error = '';
$successMessage = '';
$actionType = $pending['action'];
$targetEmail = $pending['email'];
$targetName = $pending['fullname'] ?? '';

$cooldownRemaining = max(0, ($pending['resend_cooldown'] ?? 0) - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $postAction = $_POST['form_action'] ?? 'verify';

    if ($postAction === 'cancel') {
        unset($_SESSION['user_auth_pending']);
        header('Location: loginregister.php');
        exit;
    }

    if ($postAction === 'resend') {
        if ($cooldownRemaining > 0) {
            $error = 'Vui lòng chờ ' . $cooldownRemaining . ' giây trước khi yêu cầu gửi lại mã OTP.';
        } else {
            $newCode = userOtpGenerateCode();
            $sent = userOtpSendEmail($targetEmail, $newCode, $actionType, $targetName);

            $_SESSION['user_auth_pending']['otp_code'] = $newCode;
            $_SESSION['user_auth_pending']['otp_expires'] = time() + 300;
            $_SESSION['user_auth_pending']['resend_cooldown'] = time() + 60;
            $_SESSION['user_auth_pending']['attempts'] = 0;
            $_SESSION['user_auth_pending']['dev_otp'] = $newCode;

            $cooldownRemaining = 60;
            $pending = $_SESSION['user_auth_pending'];
            $successMessage = 'Mã OTP mới đã được gửi thành công đến hộp thư của bạn.';
        }
    } elseif ($postAction === 'verify') {
        $enteredCode = trim((string)($_POST['otp_code'] ?? ''));
        $enteredCode = preg_replace('/\D/', '', $enteredCode);

        $attempts = (int)($pending['attempts'] ?? 0);
        $expiresAt = (int)($pending['otp_expires'] ?? 0);

        if ($attempts >= 5) {
            $error = 'Bạn đã nhập sai mã OTP quá 5 lần. Vui lòng bấm "Gửi lại mã OTP" để nhận mã mới.';
        } elseif (time() > $expiresAt) {
            $error = 'Mã OTP đã hết hạn (hiệu lực trong 5 phút). Vui lòng bấm "Gửi lại mã OTP" để nhận mã mới.';
        } elseif (strlen($enteredCode) !== 6) {
            $error = 'Vui lòng nhập đầy đủ 6 chữ số của mã xác thực OTP.';
        } elseif (!hash_equals((string)($pending['otp_code'] ?? ''), $enteredCode)) {
            $_SESSION['user_auth_pending']['attempts'] = $attempts + 1;
            $remainingAttempts = 5 - ($attempts + 1);
            if ($remainingAttempts > 0) {
                $error = 'Mã OTP không chính xác. Bạn còn ' . $remainingAttempts . ' lần thử lại.';
            } else {
                $error = 'Bạn đã nhập sai quá số lần cho phép. Vui lòng gửi lại mã mới.';
            }
        } else {
            if ($actionType === 'login') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$pending['user_id'];
                $_SESSION['fullname'] = $pending['fullname'];
                $_SESSION['email'] = $pending['email'];
                $_SESSION['role_id'] = (int)$pending['role_id'];
                $_SESSION['auth_updated_at'] = $pending['auth_updated_at'];

                if (!empty($pending['remember'])) {
                    authRotateRememberToken($conn, (int)$pending['user_id']);
                }

                unset($_SESSION['user_auth_pending']);
                header('Location: ../../utils/index.php');
                exit;
            } elseif ($actionType === 'register') {
                try {
                    $check = mysqli_prepare($conn, 'SELECT id FROM `user` WHERE email = ? AND deleted = 0 LIMIT 1');
                    mysqli_stmt_bind_param($check, 's', $pending['email']);
                    mysqli_stmt_execute($check);
                    mysqli_stmt_store_result($check);

                    if (mysqli_stmt_num_rows($check) > 0) {
                        mysqli_stmt_close($check);
                        unset($_SESSION['user_auth_pending']);
                        $error = 'Email này đã được đăng ký trên hệ thống. Vui lòng quay lại trang đăng nhập.';
                    } else {
                        mysqli_stmt_close($check);

                        $hasAi = false;
                        $aiCheck = mysqli_query($conn, "SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'id'");
                        if ($aiCheck && ($aiRow = mysqli_fetch_assoc($aiCheck))) {
                            $hasAi = (stripos($aiRow['EXTRA'] ?? '', 'auto_increment') !== false);
                            mysqli_free_result($aiCheck);
                        }

                        $createdAt = date('Y-m-d H:i:s');
                        $phone = $pending['phone'] ?? '';
                        $passwordHash = $pending['password_hash'] ?? '';
                        $fullname = $pending['fullname'] ?? '';
                        $userEmail = $pending['email'] ?? '';

                        if ($hasAi) {
                            $insert = mysqli_prepare($conn, 'INSERT INTO `user` (fullname, email, phone_number, password, role_id, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, 2, ?, ?, 0)');
                            mysqli_stmt_bind_param($insert, 'ssssss', $fullname, $userEmail, $phone, $passwordHash, $createdAt, $createdAt);
                            mysqli_stmt_execute($insert);
                            $newUserId = (int)mysqli_insert_id($conn);
                            mysqli_stmt_close($insert);
                        } else {
                            $maxResult = mysqli_query($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM `user`");
                            $maxRow = mysqli_fetch_assoc($maxResult);
                            $nextId = (int)($maxRow['next_id'] ?? 1);
                            mysqli_free_result($maxResult);

                            $insert = mysqli_prepare($conn, 'INSERT INTO `user` (id, fullname, email, phone_number, password, role_id, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, ?, 2, ?, ?, 0)');
                            mysqli_stmt_bind_param($insert, 'issssss', $nextId, $fullname, $userEmail, $phone, $passwordHash, $createdAt, $createdAt);
                            mysqli_stmt_execute($insert);
                            $newUserId = $nextId;
                            mysqli_stmt_close($insert);
                        }

                        if ($newUserId <= 0) {
                            $error = 'Không thể khởi tạo tài khoản do lỗi hệ thống. Vui lòng thử lại sau.';
                        } else {
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $newUserId;
                            $_SESSION['fullname'] = $fullname;
                            $_SESSION['email'] = $userEmail;
                            $_SESSION['role_id'] = 2;
                            $_SESSION['auth_updated_at'] = $createdAt;

                            unset($_SESSION['user_auth_pending']);
                            header('Location: ../../utils/index.php');
                            exit;
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[KhoaiLaptop] Lỗi kích hoạt tài khoản OTP: ' . $e->getMessage());
                    $error = 'Lỗi tạo tài khoản: ' . $e->getMessage();
                }
            }
        }
    }
}

$hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
$cleanHost = strtolower((string)preg_replace('/:\d+$/', '', $hostHeader));
$isLocalDev = ($cleanHost === '' || in_array($cleanHost, ['localhost', '127.0.0.1', '::1'], true) || PHP_SAPI === 'cli');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực mã OTP | KhoaiLaptop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --ink: #182329;
            --muted: #627279;
            --line: #dbe5e4;
            --accent: #147d78;
            --accent-dark: #0b5c5a;
            --cream: #f6f4ed;
            --bg-color: #dce9e5;
            --danger: #d9534f;
            --danger-bg: #fff0f0;
            --danger-border: #f5c6cb;
            --success: #2e7d32;
            --success-bg: #e8f5e9;
            --success-border: #c8e6c9;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #cde1dc 0%, #eef5f3 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--ink);
        }

        .auth-card {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(20, 61, 58, 0.15);
            border: 1px solid rgba(20, 125, 120, 0.15);
            padding: 44px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--accent) 0%, #35b3ab 100%);
        }

        .icon-circle {
            width: 76px;
            height: 76px;
            margin: 0 auto 20px;
            background: #eef7f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 32px;
            border: 2px solid rgba(20, 125, 120, 0.2);
            box-shadow: 0 8px 20px rgba(20, 125, 120, 0.12);
        }

        .eyebrow {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 8px;
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: var(--muted);
            margin-bottom: 24px;
        }

        .email-badge {
            display: inline-block;
            background: #edf5f4;
            color: var(--accent-dark);
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid var(--line);
            margin: 4px 0;
            word-break: break-all;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 22px;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger);
        }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success);
        }

        .alert-dev {
            background: #fff8e1;
            border: 1px solid #ffe082;
            color: #8d6e15;
            font-size: 12.5px;
        }

        .alert-dev strong {
            color: #b7791f;
        }

        .otp-input-wrap {
            margin: 24px 0 28px;
        }

        .otp-input-wrap label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .otp-input {
            width: 100%;
            height: 58px;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 14px;
            text-align: center;
            border: 2px solid var(--line);
            border-radius: 12px;
            background: #fafcfb;
            color: var(--ink);
            outline: none;
            transition: all 0.25s ease;
            font-family: 'Courier New', Courier, monospace;
            padding-left: 14px;
        }

        .otp-input:focus {
            border-color: var(--accent);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(20, 125, 120, 0.15);
        }

        .submit-btn {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 10px;
            background: var(--accent);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 6px 16px rgba(20, 125, 120, 0.25);
        }

        .submit-btn:hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(20, 125, 120, 0.35);
        }

        .submit-btn:active {
            transform: translateY(1px);
        }

        .actions-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            font-size: 13px;
            color: var(--muted);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px;
        }

        .resend-btn:disabled {
            color: #9aa7a8;
            cursor: not-allowed;
            text-decoration: none;
        }

        .cancel-btn {
            background: none;
            border: none;
            color: #88989b;
            font-size: 12.5px;
            cursor: pointer;
            text-decoration: none;
        }

        .cancel-btn:hover {
            color: var(--danger);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="auth-card" role="main">
        <div class="icon-circle" aria-hidden="true">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <span class="eyebrow">Xác thực 2 bước (OTP)</span>
        <h1>Nhập mã xác thực</h1>

        <p class="intro-text">
            <?php if ($actionType === 'register'): ?>
                Hệ thống đã gửi mã OTP 6 số để kích hoạt tài khoản tới:
            <?php else: ?>
                Vui lòng nhập mã OTP 6 số đã được gửi tới địa chỉ:
            <?php endif; ?>
            <br>
            <span class="email-badge"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8'); ?></span>
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <div><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="users_authentication.php">
            <?php echo adminCsrfField(); ?>
            <input type="hidden" name="form_action" value="verify">

            <div class="otp-input-wrap">
                <label for="otp_code">Mã OTP gồm 6 chữ số</label>
                <input 
                    type="text" 
                    id="otp_code" 
                    name="otp_code" 
                    class="otp-input" 
                    inputmode="numeric" 
                    pattern="[0-9]{6}" 
                    maxlength="6" 
                    autocomplete="one-time-code" 
                    placeholder="••••••" 
                    required 
                    autofocus
                >
            </div>

            <button type="submit" class="submit-btn">
                <span>Xác nhận và tiếp tục</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="actions-footer">
            <div>
                <span>Chưa nhận được mã? </span>
                <form method="POST" action="users_authentication.php" style="display: inline;">
                    <?php echo adminCsrfField(); ?>
                    <input type="hidden" name="form_action" value="resend">
                    <button 
                        type="submit" 
                        class="resend-btn" 
                        id="resendBtn" 
                        <?php echo $cooldownRemaining > 0 ? 'disabled' : ''; ?>
                    >
                        <?php if ($cooldownRemaining > 0): ?>
                            Gửi lại mã (<span id="countdown"><?php echo $cooldownRemaining; ?></span>s)
                        <?php else: ?>
                            Gửi lại mã OTP
                        <?php endif; ?>
                    </button>
                </form>
            </div>

            <form method="POST" action="users_authentication.php">
                <?php echo adminCsrfField(); ?>
                <input type="hidden" name="form_action" value="cancel">
                <button type="submit" class="cancel-btn">
                    <i class="fa-solid fa-arrow-left"></i> Hủy và quay lại đăng nhập
                </button>
            </form>
        </div>
    </main>

    <?php if ($cooldownRemaining > 0): ?>
    <script>
        (function() {
            let seconds = <?php echo $cooldownRemaining; ?>;
            const btn = document.getElementById('resendBtn');
            const countdownSpan = document.getElementById('countdown');

            const timer = setInterval(function() {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = 'Gửi lại mã OTP';
                } else if (countdownSpan) {
                    countdownSpan.textContent = seconds;
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
