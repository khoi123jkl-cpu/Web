<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/auth_throttle.php');
require_once(__DIR__ . '/../../../../Private/security/mfa.php');

$pending = $_SESSION['mfa_pending'] ?? null;
if (!is_array($pending) || empty($pending['user_id'])) {
    header('Location: admin_login.php');
    exit;
}

$conn = getDB();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $mfa = mfaGetUser($conn, (int)$pending['user_id']);
    if ($mfa === null || !$mfa['enabled'] || !mfaVerifyCode($mfa['secret'], $_POST['code'] ?? '')) {
        $error = 'Mã xác thực không đúng hoặc đã hết hạn.';
    } else {
        unset($_SESSION['mfa_pending']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$pending['user_id'];
        $_SESSION['fullname'] = $pending['fullname'];
        $_SESSION['email'] = $pending['email'];
        $_SESSION['role_id'] = 1;
        $_SESSION['auth_updated_at'] = $pending['auth_updated_at'];
        if (!empty($pending['remember'])) {
            authRotateRememberToken($conn, (int)$pending['user_id']);
        }
        header('Location: ../index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Xác thực MFA</title><link rel="stylesheet" href="../../assets/css/adminlogin.css?v=2"></head>
<body><div class="container"><div class="login-form"><form method="post">
<?php echo adminCsrfField(); ?><p class="login-eyebrow">XÁC THỰC HAI LỚP</p><h2 class="login-title">Nhập mã xác thực</h2>
<?php if ($error !== ''): ?><p class="login-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<div class="input-box"><input inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" autocomplete="one-time-code" required><label>Mã 6 chữ số</label></div>
<button class="login-btn" type="submit">Xác nhận</button>
</form></div></div></body></html>
