<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/auth_throttle.php');
require_once(__DIR__ . '/../../../../Private/security/users_otp.php');
require_once(__DIR__ . '/../../database/dbhelper.php');

$hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
$cleanHost = strtolower((string)preg_replace('/:\d+$/', '', $hostHeader));
$isLocalDev = ($cleanHost === '' || in_array($cleanHost, ['localhost', '127.0.0.1', '::1'], true) || PHP_SAPI === 'cli');

$pending = $_SESSION['user_auth_pending'] ?? null;
if (!is_array($pending) || empty($pending['email']) || empty($pending['action'])) {
    if ($isLocalDev) {
        // Tự động khởi tạo phiên test thử nghiệm khi truy cập trực tiếp trên localhost
        $_SESSION['user_auth_pending'] = [
            'user_id'         => 1,
            'role_id'         => 2,
            'email'           => 'user.demo@khoailaptop.vn',
            'fullname'        => 'Người Dùng Thử Nghiệm',
            'action'          => 'login',
            'otp_code'        => '583921',
            'otp_expires'     => time() + 300,
            'resend_cooldown' => time() + 60,
            'dev_otp'         => '583921',
            'attempts'        => 0
        ];
        $pending = $_SESSION['user_auth_pending'];
    } else {
        header('Location: loginregister.php');
        exit;
    }
}


$conn = getDB();
$error = '';
$successMessage = '';
$actionType = $pending['action']; // 'login' hoặc 'register'
$targetEmail = $pending['email'];
$targetName = $pending['fullname'] ?? '';

// Tính thời gian cooldown gửi lại mã
$cooldownRemaining = max(0, ($pending['resend_cooldown'] ?? 0) - time());

// Xử lý các yêu cầu POST
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
            $_SESSION['user_auth_pending']['otp_expires'] = time() + 300; // 5 phút
            $_SESSION['user_auth_pending']['resend_cooldown'] = time() + 60; // 60 giây cooldown
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
            // Xác thực OTP thành công!
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
                    // Kiểm tra lại xem email có bị trùng lặp không
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

// Kiểm tra môi trường cục bộ để hỗ trợ hiển thị mã khi SMTP chưa cấu hình
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
            --accent-light: #e6f5f3;
            --cream: #f6f4ed;
            --bg-color: #dce9e5;
            --danger: #d9534f;
            --danger-bg: #fff0f0;
            --danger-border: #f5c6cb;
            --success: #2e7d32;
            --success-bg: #e8f5e9;
            --success-border: #c8e6c9;

            /* Ball colors (0 - 9) */
            --ball-0: #ef4444; --ball-0-dark: #b91c1c;
            --ball-1: #f97316; --ball-1-dark: #c2410c;
            --ball-2: #eab308; --ball-2-dark: #a16207;
            --ball-3: #10b981; --ball-3-dark: #047857;
            --ball-4: #0d9488; --ball-4-dark: #0f766e;
            --ball-5: #0284c7; --ball-5-dark: #0369a1;
            --ball-6: #2563eb; --ball-6-dark: #1d4ed8;
            --ball-7: #6366f1; --ball-7-dark: #4338ca;
            --ball-8: #9333ea; --ball-8-dark: #7e22ce;
            --ball-9: #ec4899; --ball-9-dark: #be185d;
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
            padding: 20px 16px;
            background: linear-gradient(135deg, #cde1dc 0%, #eef5f3 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--ink);
        }

        .auth-card {
            width: 100%;
            max-width: 640px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(20, 61, 58, 0.16);
            border: 1px solid rgba(20, 125, 120, 0.15);
            padding: 34px 28px;
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
            background: linear-gradient(90deg, var(--accent) 0%, #35b3ab 50%, #f97316 100%);
        }

        .icon-circle {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            background: #eef7f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 28px;
            border: 2px solid rgba(20, 125, 120, 0.2);
            box-shadow: 0 8px 18px rgba(20, 125, 120, 0.12);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            background: rgba(20, 125, 120, 0.08);
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 8px;
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .intro-text {
            font-size: 13.5px;
            line-height: 1.5;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .email-badge {
            display: inline-block;
            background: #edf5f4;
            color: var(--accent-dark);
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 16px;
            border: 1px solid var(--line);
            margin: 4px 0;
            word-break: break-all;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12.5px;
            line-height: 1.45;
            margin-bottom: 16px;
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
            font-size: 12px;
        }

        .alert-dev strong {
            color: #b7791f;
        }

        /* ============================================================
           SLINGSHOT GAME CONTAINER
           ============================================================ */
        .slingshot-game-wrapper {
            background: linear-gradient(180deg, #f3faf8 0%, #ebf5f3 100%);
            border: 1.5px solid #c9e4de;
            border-radius: 18px;
            padding: 16px;
            margin: 16px 0 20px;
            position: relative;
            box-shadow: inset 0 2px 6px rgba(20, 125, 120, 0.04), 0 4px 16px rgba(20, 61, 58, 0.04);
            user-select: none;
            -webkit-user-select: none;
        }

        /* Top status & toolbar */
        .game-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }

        .game-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid #c3ded8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            color: #136a66;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            max-width: 85%;
            text-align: left;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
            animation: pulseDot 2s infinite ease-in-out;
            flex-shrink: 0;
        }

        @keyframes pulseDot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.35); opacity: 0.7; }
        }

        .tool-btn {
            background: #ffffff;
            border: 1px solid #c3ded8;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .tool-btn:hover {
            background: var(--accent-light);
            border-color: var(--accent);
            transform: scale(1.08);
        }

        /* ------------------------------------------------------------
           TOP SECTION: TARGET SLOTS (CÁC Ô ĐỂ BẮN BÓNG VÀO)
           ------------------------------------------------------------ */
        .target-rack-container {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #cfe5e1;
            padding: 12px 14px 10px;
            box-shadow: 0 3px 10px rgba(20, 125, 120, 0.05);
        }

        .target-rack-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .target-counter {
            background: rgba(20, 125, 120, 0.1);
            color: var(--accent-dark);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
        }

        .target-slots-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }

        .target-slot {
            flex: 1;
            max-width: 54px;
            height: 60px;
            min-width: 42px;
            border-radius: 14px;
            background: #f8fbfa;
            border: 2px dashed #a5cfc9;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.03);
        }

        .target-slot:hover {
            border-color: var(--accent);
            background: #ffffff;
        }

        .target-slot.active {
            border: 2px solid var(--accent);
            background: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(20, 125, 120, 0.22), inset 0 2px 4px rgba(20, 125, 120, 0.08);
            transform: translateY(-2px);
            animation: slotActiveHalo 2s infinite ease-in-out;
        }

        @keyframes slotActiveHalo {
            0%, 100% { box-shadow: 0 0 0 3.5px rgba(20, 125, 120, 0.22); }
            50% { box-shadow: 0 0 0 6px rgba(20, 125, 120, 0.35); }
        }

        .target-slot.aim-locked {
            border: 2px solid #f97316 !important;
            box-shadow: 0 0 0 5px rgba(249, 115, 22, 0.35), 0 4px 12px rgba(249, 115, 22, 0.25) !important;
            transform: scale(1.08) translateY(-3px) !important;
            background: #fff8f5 !important;
        }

        .target-slot.filled {
            border: 2px solid #0d9488;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(13, 148, 136, 0.15);
        }

        .target-slot.filled:hover::after {
            content: "✕";
            position: absolute;
            top: -6px;
            right: -6px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--danger);
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.25);
            animation: popIn 0.15s ease;
        }

        .slot-idx-num {
            position: absolute;
            top: 3px;
            font-size: 9px;
            font-weight: 800;
            color: #8fa6a3;
            letter-spacing: 0.04em;
        }

        .slot-basket-rim {
            position: absolute;
            bottom: 6px;
            width: 22px;
            height: 5px;
            border-radius: 50%;
            background: rgba(0,0,0,0.06);
        }

        .slot-ball-holder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        /* Quick actions under targets */
        .target-actions-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 8px;
            border-top: 1px solid #eef5f3;
        }

        .subtle-btn {
            background: #f3f8f7;
            border: 1px solid #c9e0dc;
            color: #3b5f5b;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .subtle-btn:hover {
            background: #ffffff;
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }

        .subtle-btn.mode-active {
            background: rgba(20, 125, 120, 0.1);
            border-color: var(--accent);
            color: var(--accent-dark);
        }

        /* ------------------------------------------------------------
           MIDDLE SECTION: SLINGSHOT ARENA (NÁ THUN ĐỂ KÉO)
           ------------------------------------------------------------ */
        .slingshot-arena {
            position: relative;
            width: 100%;
            height: 210px;
            margin: 12px 0;
            border-radius: 16px;
            background: radial-gradient(circle at 50% 90%, #d8ece8 0%, #ebf5f3 65%, #f7fcfa 100%);
            border: 1px solid #bfe0d8;
            box-shadow: inset 0 2px 8px rgba(20, 125, 120, 0.08);
            overflow: hidden;
            touch-action: none;
            cursor: grab;
        }

        .slingshot-arena.grabbing {
            cursor: grabbing;
        }

        #slingshotCanvas {
            width: 100%;
            height: 100%;
            display: block;
        }

        .slingshot-overlay-prompt {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(20, 125, 120, 0.25);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            color: #1a6b67;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.25s ease;
            white-space: nowrap;
        }

        /* ------------------------------------------------------------
           BOTTOM SECTION: NUMBERED BALL TRAY (DÃY BÓNG 0 - 9)
           ------------------------------------------------------------ */
        .ball-ammo-section {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #cfe5e1;
            padding: 12px 14px;
            box-shadow: 0 3px 10px rgba(20, 125, 120, 0.05);
        }

        .ammo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .ammo-hint {
            font-size: 11px;
            font-weight: 600;
            text-transform: none;
            color: var(--accent);
        }

        .ball-ammo-tray {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }

        /* 3D Glossy Ball Styling */
        .ammo-ball, .slingshot-ball {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            outline: none;
            cursor: pointer;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.55);
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease, filter 0.2s ease;
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.22), inset -2px -4px 6px rgba(0, 0, 0, 0.35), inset 2px 3px 5px rgba(255, 255, 255, 0.7);
            user-select: none;
            flex-shrink: 0;
        }

        .ammo-ball::before, .slingshot-ball::before {
            content: "";
            position: absolute;
            top: 4px;
            left: 7px;
            width: 14px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(180deg, rgba(255,255,255,0.85) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }

        .ammo-ball:hover {
            transform: translateY(-6px) scale(1.1);
            filter: brightness(1.1);
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.28), inset -2px -4px 6px rgba(0,0,0,0.35), inset 2px 3px 5px rgba(255,255,255,0.7);
        }

        .ammo-ball:active {
            transform: translateY(1px) scale(0.96);
        }

        .ammo-ball.is-loaded {
            box-shadow: 0 0 0 3px #ffffff, 0 0 0 6px var(--accent), 0 8px 16px rgba(0,0,0,0.25);
            transform: translateY(-4px) scale(1.06);
            animation: loadedBallBounce 1.5s infinite ease-in-out;
        }

        @keyframes loadedBallBounce {
            0%, 100% { transform: translateY(-4px) scale(1.06); }
            50% { transform: translateY(-8px) scale(1.1); }
        }

        /* 10 Unique Color Gradients for 0-9 */
        .ball-0 { background: radial-gradient(circle at 35% 30%, #ff8a80 0%, var(--ball-0) 50%, var(--ball-0-dark) 95%); }
        .ball-1 { background: radial-gradient(circle at 35% 30%, #ffcc80 0%, var(--ball-1) 50%, var(--ball-1-dark) 95%); }
        .ball-2 { background: radial-gradient(circle at 35% 30%, #fff59d 0%, var(--ball-2) 50%, var(--ball-2-dark) 95%); color: #fff; }
        .ball-3 { background: radial-gradient(circle at 35% 30%, #a7f3d0 0%, var(--ball-3) 50%, var(--ball-3-dark) 95%); }
        .ball-4 { background: radial-gradient(circle at 35% 30%, #99f6e4 0%, var(--ball-4) 50%, var(--ball-4-dark) 95%); }
        .ball-5 { background: radial-gradient(circle at 35% 30%, #bae6fd 0%, var(--ball-5) 50%, var(--ball-5-dark) 95%); }
        .ball-6 { background: radial-gradient(circle at 35% 30%, #bfdbfe 0%, var(--ball-6) 50%, var(--ball-6-dark) 95%); }
        .ball-7 { background: radial-gradient(circle at 35% 30%, #c7d2fe 0%, var(--ball-7) 50%, var(--ball-7-dark) 95%); }
        .ball-8 { background: radial-gradient(circle at 35% 30%, #e9d5ff 0%, var(--ball-8) 50%, var(--ball-8-dark) 95%); }
        .ball-9 { background: radial-gradient(circle at 35% 30%, #fbcfe8 0%, var(--ball-9) 50%, var(--ball-9-dark) 95%); }

        /* Ball inside target slot */
        .slingshot-ball.in-slot {
            width: 40px;
            height: 40px;
            font-size: 17px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.25), inset -2px -3px 5px rgba(0,0,0,0.35), inset 2px 2px 4px rgba(255,255,255,0.7);
            animation: bounceInSlot 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes bounceInSlot {
            0% { transform: scale(0.3) translateY(-20px); opacity: 0; }
            70% { transform: scale(1.15) translateY(2px); }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        @keyframes popIn {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }

        /* ============================================================
           OTP PREVIEW & SUBMIT FORM
           ============================================================ */
        .otp-code-summary-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 16px 0 18px;
            background: #fafcfb;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 16px;
        }

        .summary-label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-digits {
            display: flex;
            gap: 6px;
        }

        .sum-digit {
            width: 24px;
            height: 28px;
            border-radius: 6px;
            background: #edf5f4;
            border: 1px solid #c9dedb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: var(--accent-dark);
            font-family: monospace;
        }

        .sum-digit.empty {
            color: #a4beba;
            font-weight: 400;
        }

        .submit-btn {
            width: 100%;
            height: 50px;
            border: none;
            border-radius: 12px;
            background: var(--accent);
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
            box-shadow: 0 6px 16px rgba(20, 125, 120, 0.25);
        }

        .submit-btn:not(:disabled):hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(20, 125, 120, 0.35);
        }

        .submit-btn:not(:disabled):active {
            transform: translateY(1px);
        }

        .submit-btn:disabled {
            background: #b2c9c6;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.75;
        }

        .submit-btn.ready-pulse {
            animation: readyGlow 1.5s infinite ease-in-out;
            background: linear-gradient(90deg, #147d78 0%, #10b981 100%);
        }

        @keyframes readyGlow {
            0%, 100% { box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35); }
            50% { box-shadow: 0 8px 26px rgba(16, 185, 129, 0.6); }
        }

        /* Manual text entry fallback */
        .manual-input-fallback {
            margin-top: 14px;
            text-align: center;
        }

        .toggle-manual-btn {
            background: none;
            border: none;
            color: #5c7b77;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: underline;
        }

        .toggle-manual-btn:hover {
            color: var(--accent);
        }

        .manual-input-drawer {
            margin-top: 10px;
            padding: 12px;
            background: #f8faf9;
            border: 1px dashed var(--line);
            border-radius: 10px;
        }

        .manual-input-tip {
            font-size: 11.5px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .manual-field-wrap {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .manual-otp-input {
            width: 160px;
            height: 38px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 6px;
            text-align: center;
            border: 1.5px solid var(--line);
            border-radius: 8px;
            outline: none;
            font-family: monospace;
            padding-left: 6px;
        }

        .manual-otp-input:focus {
            border-color: var(--accent);
        }

        .btn-apply-manual {
            background: var(--accent);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Footer buttons */
        .actions-footer {
            margin-top: 20px;
            padding-top: 16px;
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

        /* Responsive */
        @media (max-width: 580px) {
            .auth-card {
                padding: 24px 16px;
                border-radius: 20px;
            }
            .slingshot-game-wrapper {
                padding: 12px 10px;
            }
            .target-slot {
                height: 52px;
                min-width: 38px;
            }
            .ammo-ball {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
            .slingshot-ball.in-slot {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }
            .slingshot-arena {
                height: 185px;
            }
            .ball-ammo-tray {
                flex-wrap: wrap;
                justify-content: center;
                gap: 6px;
            }
            .ball-ammo-tray .ammo-ball {
                width: calc(20% - 6px);
                max-width: 44px;
            }
        }
    </style>
</head>
<body>
    <main class="auth-card" role="main">
        <div class="icon-circle" aria-hidden="true">
            <i class="fa-solid fa-crosshairs"></i>
        </div>

        <span class="eyebrow"><i class="fa-solid fa-gamepad"></i> Game Xác thực OTP Ná Thun</span>
        <h1>Bắn bóng nhập mã OTP</h1>

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

        <?php if ($isLocalDev && !empty($pending['dev_otp'])): ?>
            <div class="alert alert-dev" role="note">
                <i class="fa-solid fa-flask"></i>
                <div style="width: 100%; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <div>
                        <strong>Môi trường Local Dev:</strong> Mã OTP là 
                        <span style="font-size: 15px; font-weight: 800; letter-spacing: 2px; color: #b7791f; background: #fff; padding: 2px 8px; border-radius: 6px; border: 1px dashed #d4a373;">
                            <?php echo htmlspecialchars((string)$pending['dev_otp'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <button type="button" id="btnAutoFireDevOtp" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; border: none; background: #b7791f; color: #fff; font-weight: 700; cursor: pointer;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Tự động bắn mã này
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             TRÒ CHƠI BẮN SÚNG CAO SU (NÁ THUN) NHẬP OTP
             ============================================================ -->
        <div class="slingshot-game-wrapper">
            <!-- Top Status Bar -->
            <div class="game-top-bar">
                <div class="game-status-badge" id="gameStatusBadge">
                    <span class="status-dot"></span>
                    <span id="gameStatusText">🎯 Kéo ná hoặc chạm bóng số để nạp & bắn vào 6 ô OTP!</span>
                </div>
                <div class="game-sound-toggle">
                    <button type="button" class="tool-btn" id="btnToggleSound" title="Bật/Tắt âm thanh ná thun" aria-label="Bật/Tắt âm thanh">
                        <i class="fa-solid fa-volume-high" id="soundIcon"></i>
                    </button>
                </div>
            </div>

            <!-- 1. BÊN TRÊN: CÁC Ô ĐỂ BẮN CÁC QUẢ BÓNG ĐÁNH SỐ VÀO (6 Ô OTP) -->
            <div class="target-rack-container" aria-label="6 ô mục tiêu nhận bóng OTP">
                <div class="target-rack-label">
                    <span><i class="fa-solid fa-bullseye"></i> 6 Ô Mục tiêu OTP:</span>
                    <span class="target-counter" id="targetCounter">Đã nạp: 0/6</span>
                </div>
                <div class="target-slots-row" id="targetSlotsRow">
                    <div class="target-slot active" data-index="0" id="slot-0" title="Ô số 1 (Chạm để xóa nếu đã có bóng)">
                        <span class="slot-idx-num">#1</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                    <div class="target-slot" data-index="1" id="slot-1" title="Ô số 2">
                        <span class="slot-idx-num">#2</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                    <div class="target-slot" data-index="2" id="slot-2" title="Ô số 3">
                        <span class="slot-idx-num">#3</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                    <div class="target-slot" data-index="3" id="slot-3" title="Ô số 4">
                        <span class="slot-idx-num">#4</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                    <div class="target-slot" data-index="4" id="slot-4" title="Ô số 5">
                        <span class="slot-idx-num">#5</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                    <div class="target-slot" data-index="5" id="slot-5" title="Ô số 6">
                        <span class="slot-idx-num">#6</span>
                        <div class="slot-basket-rim"></div>
                        <div class="slot-ball-holder"></div>
                    </div>
                </div>

                <div class="target-actions-row">
                    <button type="button" class="subtle-btn" id="btnUndoSlot" title="Xóa quả bóng vừa bắn gần nhất">
                        <i class="fa-solid fa-delete-left"></i> Xóa số vừa bắn
                    </button>
                    <button type="button" class="subtle-btn" id="btnClearAllSlots" title="Xóa toàn bộ 6 ô để bắn lại">
                        <i class="fa-solid fa-rotate-left"></i> Bắn lại từ đầu
                    </button>
                    <button type="button" class="subtle-btn mode-active" id="btnToggleMode" title="Chuyển chế độ: Bắn nhanh tự động / Kéo ná thủ công">
                        <i class="fa-solid fa-bolt"></i> <span id="modeLabel">Bắn nhanh: Bật</span>
                    </button>
                </div>
            </div>

            <!-- 2. Ở GIỮA / BÊN TRÊN KHAY BÓNG: NÁ THUN ĐỂ KÉO BẮN -->
            <div class="slingshot-arena" id="slingshotArena">
                <canvas id="slingshotCanvas"></canvas>
                <div class="slingshot-overlay-prompt" id="slingshotPrompt">
                    <span id="promptText">👉 Chạm bóng bên dưới để nạp ná & bắn!</span>
                </div>
            </div>

            <!-- 3. BÊN DƯỚI: DÃY QUẢ BÓNG ĐÁNH SỐ TỪ 0 ĐẾN 9 -->
            <div class="ball-ammo-section" aria-label="Khay đạn bóng đánh số từ 0 đến 9">
                <div class="ammo-header">
                    <span><i class="fa-solid fa-circle-dot"></i> Khay bóng số (0 - 9):</span>
                    <small class="ammo-hint">Chạm bóng để nạp & bắn vào ô OTP</small>
                </div>
                <div class="ball-ammo-tray" id="ballAmmoTray">
                    <button type="button" class="ammo-ball ball-0" data-number="0" aria-label="Bóng số 0">0</button>
                    <button type="button" class="ammo-ball ball-1" data-number="1" aria-label="Bóng số 1">1</button>
                    <button type="button" class="ammo-ball ball-2" data-number="2" aria-label="Bóng số 2">2</button>
                    <button type="button" class="ammo-ball ball-3" data-number="3" aria-label="Bóng số 3">3</button>
                    <button type="button" class="ammo-ball ball-4" data-number="4" aria-label="Bóng số 4">4</button>
                    <button type="button" class="ammo-ball ball-5" data-number="5" aria-label="Bóng số 5">5</button>
                    <button type="button" class="ammo-ball ball-6" data-number="6" aria-label="Bóng số 6">6</button>
                    <button type="button" class="ammo-ball ball-7" data-number="7" aria-label="Bóng số 7">7</button>
                    <button type="button" class="ammo-ball ball-8" data-number="8" aria-label="Bóng số 8">8</button>
                    <button type="button" class="ammo-ball ball-9" data-number="9" aria-label="Bóng số 9">9</button>
                </div>
            </div>
        </div>

        <!-- FORM GỬI MÃ OTP LÊN MÁY CHỦ -->
        <form method="POST" action="users_authentication.php" id="otpForm">
            <?php echo adminCsrfField(); ?>
            <input type="hidden" name="form_action" value="verify">
            <input type="hidden" id="otp_code" name="otp_code" value="">

            <!-- Hiển thị mã đã nạp -->
            <div class="otp-code-summary-box">
                <span class="summary-label">Mã OTP đã nạp:</span>
                <div class="summary-digits" id="summaryDigits">
                    <span class="sum-digit empty" id="sum-0">_</span>
                    <span class="sum-digit empty" id="sum-1">_</span>
                    <span class="sum-digit empty" id="sum-2">_</span>
                    <span class="sum-digit empty" id="sum-3">_</span>
                    <span class="sum-digit empty" id="sum-4">_</span>
                    <span class="sum-digit empty" id="sum-5">_</span>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>
                <span>Xác nhận và tiếp tục</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <!-- Hỗ trợ bàn phím / Dán mã từ email -->
        <div class="manual-input-fallback">
            <button type="button" class="toggle-manual-btn" id="btnToggleManual">
                <i class="fa-solid fa-keyboard"></i> Dán mã hoặc nhập nhanh bằng bàn phím
            </button>
            <div class="manual-input-drawer" id="manualInputDrawer" style="display: none;">
                <p class="manual-input-tip">Dán mã 6 số từ email để ná tự động bắn vào các ô mục tiêu:</p>
                <div class="manual-field-wrap">
                    <input 
                        type="text" 
                        id="manualOtpInput" 
                        class="manual-otp-input" 
                        inputmode="numeric" 
                        maxlength="6" 
                        pattern="[0-9]{6}" 
                        placeholder="••••••" 
                        autocomplete="one-time-code"
                    >
                    <button type="button" id="btnApplyManual" class="btn-apply-manual">
                        <i class="fa-solid fa-crosshairs"></i> Bắn toàn bộ
                    </button>
                </div>
            </div>
        </div>

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

    <!-- ============================================================
         JAVASCRIPT ENGINE CHO GAME BẮN SÚNG CAO SU (NÁ THUN) OTP
         ============================================================ -->
    <script>
    (function() {
        'use strict';

        // 1. ÂM THANH GAME ĐỘNG (Web Audio API Synthesizer - Không phụ thuộc file ngoài)
        class SlingshotAudio {
            constructor() {
                this.ctx = null;
                this.muted = localStorage.getItem('slingshot_otp_muted') === 'true';
            }
            init() {
                if (!this.ctx) {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx) this.ctx = new AudioCtx();
                }
                if (this.ctx && this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
            }
            playSnap() {
                if (this.muted) return;
                this.init();
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(260, now);
                osc.frequency.exponentialRampToValueAtTime(35, now + 0.09);
                gain.gain.setValueAtTime(0.3, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.09);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start(now);
                osc.stop(now + 0.09);
            }
            playWhoosh() {
                if (this.muted) return;
                this.init();
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(320, now);
                osc.frequency.exponentialRampToValueAtTime(700, now + 0.14);
                gain.gain.setValueAtTime(0.02, now);
                gain.gain.linearRampToValueAtTime(0.12, now + 0.06);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start(now);
                osc.stop(now + 0.22);
            }
            playBasketHit(index) {
                if (this.muted) return;
                this.init();
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                const scale = [523.25, 587.33, 659.25, 783.99, 880.00, 1046.50];
                const freq = scale[Math.min(index, scale.length - 1)] || 587.33;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now);
                gain.gain.setValueAtTime(0.28, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.32);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start(now);
                osc.stop(now + 0.32);
            }
            playRemove() {
                if (this.muted) return;
                this.init();
                if (!this.ctx) return;
                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(380, now);
                osc.frequency.exponentialRampToValueAtTime(140, now + 0.1);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start(now);
                osc.stop(now + 0.1);
            }
            playWinFanfare() {
                if (this.muted) return;
                this.init();
                if (!this.ctx) return;
                const notes = [523.25, 659.25, 783.99, 1046.50];
                notes.forEach((f, i) => {
                    const now = this.ctx.currentTime + (i * 0.09);
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(f, now);
                    gain.gain.setValueAtTime(0.22, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
                    osc.connect(gain);
                    gain.connect(this.ctx.destination);
                    osc.start(now);
                    osc.stop(now + 0.3);
                });
            }
            toggleMute() {
                this.muted = !this.muted;
                localStorage.setItem('slingshot_otp_muted', this.muted ? 'true' : 'false');
                return this.muted;
            }
        }

        const sound = new SlingshotAudio();
        const soundBtn = document.getElementById('btnToggleSound');
        const soundIcon = document.getElementById('soundIcon');
        function updateSoundUI() {
            if (sound.muted) {
                soundIcon.className = 'fa-solid fa-volume-xmark';
                soundBtn.title = 'Bật âm thanh ná thun';
            } else {
                soundIcon.className = 'fa-solid fa-volume-high';
                soundBtn.title = 'Tắt âm thanh ná thun';
            }
        }
        updateSoundUI();
        soundBtn.addEventListener('click', function() {
            sound.toggleMute();
            updateSoundUI();
        });

        // 2. MÀU SẮC CHO CÁC QUẢ BÓNG TỪ 0 ĐẾN 9
        const BALL_THEMES = [
            { num: 0, color: '#ef4444', dark: '#b91c1c', light: '#ff8a80' },
            { num: 1, color: '#f97316', dark: '#c2410c', light: '#ffcc80' },
            { num: 2, color: '#eab308', dark: '#a16207', light: '#fff59d' },
            { num: 3, color: '#10b981', dark: '#047857', light: '#a7f3d0' },
            { num: 4, color: '#0d9488', dark: '#0f766e', light: '#99f6e4' },
            { num: 5, color: '#0284c7', dark: '#0369a1', light: '#bae6fd' },
            { num: 6, color: '#2563eb', dark: '#1d4ed8', light: '#bfdbfe' },
            { num: 7, color: '#6366f1', dark: '#4338ca', light: '#c7d2fe' },
            { num: 8, color: '#9333ea', dark: '#7e22ce', light: '#e9d5ff' },
            { num: 9, color: '#ec4899', dark: '#be185d', light: '#fbcfe8' }
        ];

        // 3. TRẠNG THÁI GAME
        const slotsData = [null, null, null, null, null, null]; // 6 ô
        let currentLoadedBall = 0; // Số hiện đang nạp vào ná
        let autoShootMode = true; // Bắn nhanh: chạm bóng là ná tự kéo & bắn
        let isShooting = false; // Khóa khi đạn đang bay

        // DOM Elements
        const targetSlots = document.querySelectorAll('.target-slot');
        const targetCounter = document.getElementById('targetCounter');
        const gameStatusText = document.getElementById('gameStatusText');
        const slingshotPrompt = document.getElementById('promptText');
        const otpCodeInput = document.getElementById('otp_code');
        const submitBtn = document.getElementById('submitBtn');
        const btnToggleMode = document.getElementById('btnToggleMode');
        const modeLabel = document.getElementById('modeLabel');
        const ammoButtons = document.querySelectorAll('.ammo-ball');

        // Chế độ bắn
        btnToggleMode.addEventListener('click', function() {
            autoShootMode = !autoShootMode;
            if (autoShootMode) {
                btnToggleMode.classList.add('mode-active');
                modeLabel.textContent = 'Bắn nhanh: Bật';
                gameStatusText.textContent = '⚡ Chế độ Bắn Nhanh: Chạm bóng bên dưới để ná tự động kéo & bắn!';
            } else {
                btnToggleMode.classList.remove('mode-active');
                modeLabel.textContent = 'Kéo tay: Bật';
                gameStatusText.textContent = '🎯 Chế độ Kéo Tay: Chọn bóng bên dưới, sau đó kéo ná thả tay để bắn!';
            }
        });

        function getFirstEmptySlotIndex() {
            return slotsData.findIndex(val => val === null);
        }

        function updateSlotsUI() {
            const firstEmpty = getFirstEmptySlotIndex();
            let filledCount = 0;

            slotsData.forEach((val, idx) => {
                const slotEl = targetSlots[idx];
                const holder = slotEl.querySelector('.slot-ball-holder');
                const sumEl = document.getElementById('sum-' + idx);

                slotEl.classList.remove('active', 'filled', 'aim-locked');

                if (val !== null) {
                    filledCount++;
                    slotEl.classList.add('filled');
                    const theme = BALL_THEMES[val];
                    holder.innerHTML = '<div class="slingshot-ball in-slot ball-' + val + '" data-number="' + val + '">' + val + '</div>';
                    if (sumEl) {
                        sumEl.textContent = val;
                        sumEl.classList.remove('empty');
                    }
                } else {
                    holder.innerHTML = '';
                    if (sumEl) {
                        sumEl.textContent = '_';
                        sumEl.classList.add('empty');
                    }
                    if (idx === firstEmpty) {
                        slotEl.classList.add('active');
                    }
                }
            });

            targetCounter.textContent = 'Đã nạp: ' + filledCount + '/6';

            // Đồng bộ giá trị input submit
            const fullOtp = slotsData.map(v => (v !== null ? v : '')).join('');
            otpCodeInput.value = fullOtp;

            if (filledCount === 6) {
                submitBtn.disabled = false;
                submitBtn.classList.add('ready-pulse');
                gameStatusText.textContent = '🎉 Đã đủ 6 số OTP! Bấm "Xác nhận và tiếp tục" để hoàn tất.';
                slingshotPrompt.textContent = '✨ Đã bắn đủ 6 số! Sẵn sàng xác nhận.';
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.remove('ready-pulse');
                if (firstEmpty !== -1) {
                    slingshotPrompt.textContent = '👉 Đang ngắm bắn vào ô số ' + (firstEmpty + 1);
                }
            }

            // Đồng bộ trạng thái bóng đang nạp trong khay
            ammoButtons.forEach(btn => {
                const num = parseInt(btn.dataset.number, 10);
                if (num === currentLoadedBall) {
                    btn.classList.add('is-loaded');
                } else {
                    btn.classList.remove('is-loaded');
                }
            });
        }

        // Chạm vào ô đã có bóng để xóa quả bóng đó
        targetSlots.forEach((slotEl) => {
            slotEl.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index, 10);
                if (slotsData[idx] !== null) {
                    slotsData[idx] = null;
                    sound.playRemove();
                    updateSlotsUI();
                }
            });
        });

        // Xóa số vừa bắn (Undo)
        document.getElementById('btnUndoSlot').addEventListener('click', function() {
            for (let i = slotsData.length - 1; i >= 0; i--) {
                if (slotsData[i] !== null) {
                    slotsData[i] = null;
                    sound.playRemove();
                    updateSlotsUI();
                    break;
                }
            }
        });

        // Bắn lại từ đầu (Clear All)
        document.getElementById('btnClearAllSlots').addEventListener('click', function() {
            let hadAny = false;
            for (let i = 0; i < slotsData.length; i++) {
                if (slotsData[i] !== null) {
                    slotsData[i] = null;
                    hadAny = true;
                }
            }
            if (hadAny) {
                sound.playRemove();
                updateSlotsUI();
            }
        });

        // 4. CANVAS NÁ THUN & VẬT LÝ BẮN SÚNG CAO SU
        const arena = document.getElementById('slingshotArena');
        const canvas = document.getElementById('slingshotCanvas');
        const ctx = canvas.getContext('2d');

        let dpr = window.devicePixelRatio || 1;
        let cWidth = 560;
        let cHeight = 210;

        let forkBaseX = 280;
        let forkBaseY = 195;
        let leftTip = { x: 236, y: 95 };
        let rightTip = { x: 324, y: 95 };
        let pouchRest = { x: 280, y: 125 };
        let pouchPos = { x: 280, y: 125 };

        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        let flyingBalls = [];
        let particles = [];
        let recoilTime = 0;
        let aimedSlotIndex = -1;

        function resizeCanvas() {
            const rect = arena.getBoundingClientRect();
            cWidth = rect.width;
            cHeight = rect.height;
            dpr = window.devicePixelRatio || 1;

            canvas.width = cWidth * dpr;
            canvas.height = cHeight * dpr;
            ctx.resetTransform();
            ctx.scale(dpr, dpr);

            forkBaseX = cWidth / 2;
            forkBaseY = cHeight - 14;
            const forkSpan = Math.min(46, cWidth * 0.12);
            leftTip = { x: forkBaseX - forkSpan, y: cHeight - 110 };
            rightTip = { x: forkBaseX + forkSpan, y: cHeight - 110 };
            pouchRest = { x: forkBaseX, y: cHeight - 85 };

            if (!isDragging) {
                pouchPos = { x: pouchRest.x, y: pouchRest.y };
            }
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Nạp bóng số
        function loadBallToSlingshot(number) {
            currentLoadedBall = number;
            ammoButtons.forEach(btn => {
                btn.classList.toggle('is-loaded', parseInt(btn.dataset.number, 10) === number);
            });
            slingshotPrompt.textContent = '🏹 Đã nạp bóng số ' + number + ' vào ná!';
        }

        // Bắn bóng vào ô mục tiêu (Thực hiện chu trình vật lý)
        function shootBallToSlot(number, targetIndex) {
            if (targetIndex < 0 || targetIndex >= 6) return;
            isShooting = true;

            const targetEl = targetSlots[targetIndex];
            const targetRect = targetEl.getBoundingClientRect();
            const arenaRect = arena.getBoundingClientRect();

            // Tọa độ đích trên canvas
            const targetCanvasX = (targetRect.left + targetRect.width / 2) - arenaRect.left;
            const targetCanvasY = -12; // Phía trên mép canvas

            const startX = pouchPos.x;
            const startY = pouchPos.y;

            // Đỉnh cong parabol quỹ đạo
            const controlX = (startX + targetCanvasX) / 2 + (targetCanvasX - startX) * 0.2;
            const controlY = Math.min(startY, targetCanvasY) - 50;

            sound.playSnap();
            sound.playWhoosh();
            recoilTime = 1.0;

            flyingBalls.push({
                number: number,
                theme: BALL_THEMES[number],
                startX: startX,
                startY: startY,
                controlX: controlX,
                controlY: controlY,
                targetCanvasX: targetCanvasX,
                targetCanvasY: targetCanvasY,
                targetIndex: targetIndex,
                progress: 0,
                duration: 380, // 380ms bay vút lên
                startTime: performance.now()
            });

            // Đưa túi da về vị trí nghỉ
            pouchPos = { x: pouchRest.x, y: pouchRest.y };
            isDragging = false;
            arena.classList.remove('grabbing');
        }

        // Kích nổ pháo hoa hạt khi bóng trúng ô
        function spawnImpactParticles(x, y, theme) {
            const count = 18;
            for (let i = 0; i < count; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = 1.5 + Math.random() * 4.5;
                particles.push({
                    x: x,
                    y: y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - 1.2,
                    color: i % 2 === 0 ? theme.color : (i % 3 === 0 ? '#ffd700' : '#ffffff'),
                    radius: 2 + Math.random() * 2.5,
                    life: 1.0,
                    decay: 0.03 + Math.random() * 0.03
                });
            }
        }

        // Tự động bắn khi bấm bóng ở chế độ Bắn Nhanh
        function handleBallSelection(number) {
            loadBallToSlingshot(number);
            const nextEmpty = getFirstEmptySlotIndex();

            if (nextEmpty === -1) {
                gameStatusText.textContent = '⚠️ Các ô đã đầy 6 số! Hãy bấm Xác nhận hoặc chạm vào ô để xóa bớt.';
                return;
            }

            if (autoShootMode && !isShooting) {
                // Diễn hoạt kéo nhẹ ná thun lùi lại rồi nhả ra ngay
                const pullBackDistance = 35;
                pouchPos.y = pouchRest.y + pullBackDistance;

                setTimeout(() => {
                    shootBallToSlot(number, nextEmpty);
                }, 90);
            }
        }

        // Gán sự kiện click cho 10 quả bóng ở khay dưới
        ammoButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const num = parseInt(this.dataset.number, 10);
                handleBallSelection(num);
            });
        });

        // 5. TƯƠNG TÁC KÉO THẢ NÁ THUN (MOUSE & TOUCH)
        function getCanvasPoint(evt) {
            const rect = arena.getBoundingClientRect();
            const clientX = evt.touches ? evt.touches[0].clientX : evt.clientX;
            const clientY = evt.touches ? evt.touches[0].clientY : evt.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function isNearPouch(pt) {
            const dx = pt.x - pouchPos.x;
            const dy = pt.y - pouchPos.y;
            return (dx * dx + dy * dy) <= (36 * 36);
        }

        function checkAimTrajectory() {
            // Xác định ô mục tiêu được ngắm
            const dx = pouchRest.x - pouchPos.x;
            const dy = pouchRest.y - pouchPos.y;
            const pullDist = Math.sqrt(dx * dx + dy * dy);

            if (pullDist < 12) {
                targetSlots.forEach(s => s.classList.remove('aim-locked'));
                aimedSlotIndex = -1;
                return;
            }

            // Dự đoán góc bắn sang ngang
            const arenaRect = arena.getBoundingClientRect();
            const aimNormalizedX = pouchRest.x + dx * 2.5;

            let closestSlot = -1;
            let closestDist = 9999;

            targetSlots.forEach((slot, idx) => {
                const sRect = slot.getBoundingClientRect();
                const slotCenterX = (sRect.left + sRect.width / 2) - arenaRect.left;
                const d = Math.abs(aimNormalizedX - slotCenterX);
                if (d < closestDist && d < 80) {
                    closestDist = d;
                    closestSlot = idx;
                }
            });

            targetSlots.forEach((s, idx) => {
                if (idx === closestSlot) {
                    s.classList.add('aim-locked');
                } else {
                    s.classList.remove('aim-locked');
                }
            });

            aimedSlotIndex = closestSlot;
        }

        function onPointerDown(e) {
            const pt = getCanvasPoint(e);
            if (isNearPouch(pt) || pt.y > pouchRest.y - 20) {
                isDragging = true;
                arena.classList.add('grabbing');
                dragOffset.x = pt.x - pouchPos.x;
                dragOffset.y = pt.y - pouchPos.y;
                slingshotPrompt.textContent = '🎯 Kéo lùi để lấy lực và ngắm bắn vào ô OTP!';
            }
        }

        function onPointerMove(e) {
            if (!isDragging) return;
            const pt = getCanvasPoint(e);
            const targetX = pt.x - dragOffset.x;
            const targetY = pt.y - dragOffset.y;

            // Giới hạn khoảng cách kéo ná (tối đa 85px)
            const dx = targetX - pouchRest.x;
            const dy = targetY - pouchRest.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            const maxPull = 85;

            // Chỉ cho phép kéo lùi xuống phía dưới hoặc sang hai bên
            const clampedDy = Math.max(0, dy);

            if (dist > maxPull) {
                const angle = Math.atan2(clampedDy, dx);
                pouchPos.x = pouchRest.x + Math.cos(angle) * maxPull;
                pouchPos.y = pouchRest.y + Math.sin(angle) * maxPull;
            } else {
                pouchPos.x = targetX;
                pouchPos.y = pouchRest.y + clampedDy;
            }

            checkAimTrajectory();
        }

        function onPointerUp() {
            if (!isDragging) return;
            isDragging = false;
            arena.classList.remove('grabbing');

            const dx = pouchRest.x - pouchPos.x;
            const dy = pouchRest.y - pouchPos.y;
            const pullDist = Math.sqrt(dx * dx + dy * dy);

            // Bỏ khóa ngắm
            targetSlots.forEach(s => s.classList.remove('aim-locked'));

            if (pullDist > 18) {
                // Xác định bắn vào ô nào: ô đang ngắm trúng hoặc ô trống đầu tiên
                let targetIdx = aimedSlotIndex;
                if (targetIdx === -1) {
                    targetIdx = getFirstEmptySlotIndex();
                }
                if (targetIdx !== -1) {
                    shootBallToSlot(currentLoadedBall, targetIdx);
                } else {
                    // Các ô đã đầy
                    pouchPos = { x: pouchRest.x, y: pouchRest.y };
                    gameStatusText.textContent = '⚠️ Các ô đã đầy 6 số! Hãy bấm Xác nhận hoặc chạm vào ô để xóa.';
                }
            } else {
                // Thả tay quá nhẹ -> nảy về vị trí ban đầu
                pouchPos = { x: pouchRest.x, y: pouchRest.y };
            }
        }

        arena.addEventListener('mousedown', onPointerDown);
        window.addEventListener('mousemove', onPointerMove);
        window.addEventListener('mouseup', onPointerUp);

        arena.addEventListener('touchstart', onPointerDown, { passive: true });
        window.addEventListener('touchmove', onPointerMove, { passive: true });
        window.addEventListener('touchend', onPointerUp);

        // 6. RENDER VÒNG LẶP CANVAS (ANIMATION LOOP)
        function renderSlingshotFrame(now) {
            ctx.clearRect(0, 0, cWidth, cHeight);

            // Xử lý nảy đàn hồi (recoil) của dây thun sau khi bắn
            let currentPouchX = pouchPos.x;
            let currentPouchY = pouchPos.y;

            if (recoilTime > 0) {
                recoilTime -= 0.05;
                const damp = Math.exp(- (1.0 - recoilTime) * 6);
                const osc = Math.sin((1.0 - recoilTime) * 35) * 14 * damp;
                currentPouchY = pouchRest.y - osc;
            }

            // 6.1. Vẽ dây thun phía sau (từ chạc phải tới túi da)
            ctx.save();
            ctx.lineWidth = Math.max(2.5, 5 - (pouchPos.y - pouchRest.y) * 0.03);
            ctx.strokeStyle = '#c2410c';
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(rightTip.x, rightTip.y);
            ctx.quadraticCurveTo(
                (rightTip.x + currentPouchX) / 2,
                (rightTip.y + currentPouchY) / 2 + 3,
                currentPouchX + 10,
                currentPouchY
            );
            ctx.stroke();
            ctx.restore();

            // 6.2. Vẽ đường ngắm quỹ đạo chấm bi phát sáng (khi đang kéo ná)
            if (isDragging) {
                const pullDx = pouchRest.x - pouchPos.x;
                const pullDy = pouchRest.y - pouchPos.y;
                const pullDist = Math.sqrt(pullDx * pullDx + pullDy * pullDy);

                if (pullDist > 12) {
                    ctx.save();
                    const steps = 18;
                    const vx = pullDx * 0.16;
                    const vy = pullDy * 0.22;
                    let simX = pouchPos.x;
                    let simY = pouchPos.y;
                    let simVy = vy;

                    for (let i = 0; i < steps; i++) {
                        simX += vx;
                        simY -= simVy;
                        simVy -= 0.45; // Trọng lực ngược chiều

                        const alpha = Math.max(0.08, 0.9 - (i / steps));
                        const r = Math.max(1.5, 4.5 - (i / steps) * 2.8);

                        ctx.beginPath();
                        ctx.arc(simX, simY, r, 0, Math.PI * 2);
                        ctx.fillStyle = aimedSlotIndex !== -1 ? 'rgba(249, 115, 22, ' + alpha + ')' : 'rgba(20, 125, 120, ' + alpha + ')';
                        ctx.shadowColor = aimedSlotIndex !== -1 ? '#f97316' : '#147d78';
                        ctx.shadowBlur = 6;
                        ctx.fill();
                    }
                    ctx.restore();
                }
            }

            // 6.3. Vẽ quả bóng nằm trong túi da (nếu không có đạn đang bay từ vị trí này)
            const activeTheme = BALL_THEMES[currentLoadedBall];
            if (!isShooting || recoilTime < 0.2) {
                ctx.save();
                ctx.beginPath();
                ctx.arc(currentPouchX, currentPouchY, 17, 0, Math.PI * 2);
                const ballGrad = ctx.createRadialGradient(
                    currentPouchX - 5, currentPouchY - 5, 2,
                    currentPouchX, currentPouchY, 17
                );
                ballGrad.addColorStop(0, '#ffffff');
                ballGrad.addColorStop(0.2, activeTheme.light);
                ballGrad.addColorStop(0.65, activeTheme.color);
                ballGrad.addColorStop(1, activeTheme.dark);
                ctx.fillStyle = ballGrad;
                ctx.shadowColor = 'rgba(0,0,0,0.35)';
                ctx.shadowBlur = 8;
                ctx.shadowOffsetY = 4;
                ctx.fill();

                // Chữ số trên bóng
                ctx.shadowBlur = 0;
                ctx.shadowOffsetY = 0;
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(currentLoadedBall, currentPouchX, currentPouchY + 0.5);
                ctx.restore();
            }

            // 6.4. Vẽ túi da kẹp bóng (Leather Pouch)
            ctx.save();
            ctx.fillStyle = '#5d4037';
            ctx.strokeStyle = '#3e2723';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.ellipse(currentPouchX, currentPouchY, 20, 10, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.restore();

            // 6.5. Vẽ dây thun phía trước (từ chạc trái tới túi da)
            ctx.save();
            ctx.lineWidth = Math.max(2.5, 5 - (pouchPos.y - pouchRest.y) * 0.03);
            ctx.strokeStyle = '#ea580c';
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(leftTip.x, leftTip.y);
            ctx.quadraticCurveTo(
                (leftTip.x + currentPouchX) / 2,
                (leftTip.y + currentPouchY) / 2 + 3,
                currentPouchX - 10,
                currentPouchY
            );
            ctx.stroke();

            // Đường highlight viền sáng trên dây thun
            ctx.lineWidth = 1;
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
            ctx.stroke();
            ctx.restore();

            // 6.6. Vẽ chạc ná thun bằng gỗ (Y-frame Slingshot)
            ctx.save();
            // Đổ bóng thân ná xuống nền
            ctx.beginPath();
            ctx.ellipse(forkBaseX, forkBaseY + 5, 28, 8, 0, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(0, 0, 0, 0.12)';
            ctx.fill();

            // Tay cầm và 2 nhánh chữ Y
            const woodGrad = ctx.createLinearGradient(forkBaseX - 30, 0, forkBaseX + 30, 0);
            woodGrad.addColorStop(0, '#5c382b');
            woodGrad.addColorStop(0.5, '#8d5b4c');
            woodGrad.addColorStop(1, '#4a2c20');

            ctx.lineWidth = 16;
            ctx.strokeStyle = woodGrad;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            // Nhánh trái
            ctx.beginPath();
            ctx.moveTo(forkBaseX, forkBaseY);
            ctx.quadraticCurveTo(forkBaseX - 10, forkBaseY - 50, leftTip.x, leftTip.y);
            ctx.stroke();

            // Nhánh phải
            ctx.beginPath();
            ctx.moveTo(forkBaseX, forkBaseY);
            ctx.quadraticCurveTo(forkBaseX + 10, forkBaseY - 50, rightTip.x, rightTip.y);
            ctx.stroke();

            // Tay cầm dưới
            ctx.lineWidth = 20;
            ctx.beginPath();
            ctx.moveTo(forkBaseX, forkBaseY - 35);
            ctx.lineTo(forkBaseX, forkBaseY);
            ctx.stroke();

            // Đai da quấn quanh tay cầm
            ctx.lineWidth = 22;
            ctx.strokeStyle = '#3e2723';
            ctx.beginPath();
            ctx.moveTo(forkBaseX, forkBaseY - 24);
            ctx.lineTo(forkBaseX, forkBaseY - 8);
            ctx.stroke();

            // Chốt đinh tán bằng đồng ở 2 đầu ngọn ná
            ctx.fillStyle = '#f59e0b';
            ctx.beginPath();
            ctx.arc(leftTip.x, leftTip.y, 4, 0, Math.PI * 2);
            ctx.arc(rightTip.x, rightTip.y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();

            // 6.7. Diễn hoạt các quả bóng đang bay vút lên (Flying Projectiles)
            for (let i = flyingBalls.length - 1; i >= 0; i--) {
                const fb = flyingBalls[i];
                const elapsed = now - fb.startTime;
                fb.progress = Math.min(1.0, elapsed / fb.duration);

                // Đường cong Bezier bậc 2
                const t = fb.progress;
                const invT = 1 - t;
                const bx = invT * invT * fb.startX + 2 * invT * t * fb.controlX + t * t * fb.targetCanvasX;
                const by = invT * invT * fb.startY + 2 * invT * t * fb.controlY + t * t * fb.targetCanvasY;

                // Tỉ lệ bóng thu nhỏ nhẹ khi bay lên cao tạo hiệu ứng chiều sâu 3D
                const scale = 1.0 - t * 0.15;
                const r = 17 * scale;

                // Vẽ bóng
                ctx.save();
                ctx.beginPath();
                ctx.arc(bx, by, r, 0, Math.PI * 2);
                const fGrad = ctx.createRadialGradient(bx - 4, by - 4, 2, bx, by, r);
                fGrad.addColorStop(0, '#ffffff');
                fGrad.addColorStop(0.2, fb.theme.light);
                fGrad.addColorStop(0.65, fb.theme.color);
                fGrad.addColorStop(1, fb.theme.dark);
                ctx.fillStyle = fGrad;
                ctx.shadowColor = fb.theme.color;
                ctx.shadowBlur = 10;
                ctx.fill();

                ctx.shadowBlur = 0;
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold ' + (15 * scale) + 'px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(fb.number, bx, by + 0.5);
                ctx.restore();

                // Tạo vệt khói/sao lấp lánh đằng sau bóng
                if (Math.random() > 0.4) {
                    particles.push({
                        x: bx + (Math.random() - 0.5) * 6,
                        y: by + (Math.random() - 0.5) * 6,
                        vx: (Math.random() - 0.5) * 1.5,
                        vy: 0.8 + Math.random() * 1.5,
                        color: fb.theme.light,
                        radius: 1.5 + Math.random() * 2,
                        life: 0.8,
                        decay: 0.05
                    });
                }

                // Khi bóng bay tới đích
                if (fb.progress >= 1.0) {
                    spawnImpactParticles(fb.targetCanvasX, 10, fb.theme);
                    sound.playBasketHit(fb.targetIndex);

                    // Cập nhật ô trong DOM
                    slotsData[fb.targetIndex] = fb.number;
                    updateSlotsUI();

                    flyingBalls.splice(i, 1);
                    isShooting = false;

                    // Nếu đã bắn đủ 6 số, phát nhạc chúc mừng
                    if (getFirstEmptySlotIndex() === -1) {
                        setTimeout(() => sound.playWinFanfare(), 150);
                    }
                }
            }

            // 6.8. Cập nhật và vẽ các hạt pháo hoa lấp lánh (Particles)
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.12; // Trọng lực nhẹ
                p.life -= p.decay;

                if (p.life <= 0) {
                    particles.splice(i, 1);
                } else {
                    ctx.save();
                    ctx.globalAlpha = p.life;
                    ctx.fillStyle = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }
            }

            requestAnimationFrame(renderSlingshotFrame);
        }
        requestAnimationFrame(renderSlingshotFrame);

        // 7. BÀN PHÍM VẬT LÝ & DÁN MÃ OTP
        window.addEventListener('keydown', function(e) {
            // Nếu đang gõ trong input text khác thì bỏ qua
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) {
                return;
            }

            if (e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                handleBallSelection(parseInt(e.key, 10));
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                document.getElementById('btnUndoSlot').click();
            } else if (e.key === 'Enter') {
                if (getFirstEmptySlotIndex() === -1) {
                    document.getElementById('otpForm').submit();
                }
            }
        });

        // Hộp dán mã thủ công (Drawer)
        const btnToggleManual = document.getElementById('btnToggleManual');
        const manualInputDrawer = document.getElementById('manualInputDrawer');
        const manualOtpInput = document.getElementById('manualOtpInput');
        const btnApplyManual = document.getElementById('btnApplyManual');

        btnToggleManual.addEventListener('click', function() {
            if (manualInputDrawer.style.display === 'none') {
                manualInputDrawer.style.display = 'block';
                manualOtpInput.focus();
            } else {
                manualInputDrawer.style.display = 'none';
            }
        });

        function fireSeriesOfNumbers(digitString) {
            const digits = digitString.replace(/\D/g, '').slice(0, 6).split('');
            if (digits.length === 0) return;

            // Xóa các ô hiện tại
            for (let i = 0; i < 6; i++) slotsData[i] = null;
            updateSlotsUI();

            // Bắn lần lượt từng bóng với khoảng nghỉ 200ms
            digits.forEach((d, idx) => {
                setTimeout(() => {
                    const num = parseInt(d, 10);
                    loadBallToSlingshot(num);
                    shootBallToSlot(num, idx);
                }, idx * 220);
            });
        }

        btnApplyManual.addEventListener('click', function() {
            fireSeriesOfNumbers(manualOtpInput.value);
            manualInputDrawer.style.display = 'none';
        });

        manualOtpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                btnApplyManual.click();
            }
        });

        // Bắt sự kiện dán trực tiếp trên toàn màn hình
        window.addEventListener('paste', function(e) {
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const clean = pasted.replace(/\D/g, '');
            if (clean.length === 6) {
                e.preventDefault();
                fireSeriesOfNumbers(clean);
            }
        });

        // 8. TÍNH NĂNG TEST TỰ ĐỘNG BẮN MÃ TRONG LOCAL DEV
        const btnAutoDev = document.getElementById('btnAutoFireDevOtp');
        if (btnAutoDev) {
            btnAutoDev.addEventListener('click', function() {
                const devOtp = '<?php echo !empty($pending['dev_otp']) ? addslashes((string)$pending['dev_otp']) : ''; ?>';
                if (devOtp) {
                    fireSeriesOfNumbers(devOtp);
                }
            });
        }

        // Khởi tạo trạng thái ban đầu
        loadBallToSlingshot(0);
        updateSlotsUI();
    })();
    </script>

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
