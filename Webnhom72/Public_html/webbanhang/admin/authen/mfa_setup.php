<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');
require_once(__DIR__ . '/../../../../Private/security/mfa.php');

$conn = getDB();
authRequireAdmin($conn);
$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';
$mfa = mfaGetUser($conn, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    try {
        if ($mfa !== null && $mfa['enabled'] && $mfa['secret'] !== false) {
            $error = 'MFA đã được bật cho tài khoản này.';
        } elseif (!isset($_SESSION['mfa_setup_secret'])) {
            $_SESSION['mfa_setup_secret'] = mfaGenerateSecret();
        }

        if ($error === '') {
            $secret = $_SESSION['mfa_setup_secret'];
            if (!mfaVerifyCode($secret, $_POST['code'] ?? '')) {
                $error = 'Mã xác thực không đúng. Vui lòng kiểm tra lại giờ trên điện thoại.';
            } else {
                $ciphertext = mfaEncryptSecret($secret);
                $statement = mysqli_prepare(
                    $conn,
                    'INSERT INTO user_mfa (user_id, secret_ciphertext, enabled, created_at, updated_at)
                     VALUES (?, ?, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE secret_ciphertext = VALUES(secret_ciphertext), enabled = 1, updated_at = NOW()'
                );
                mysqli_stmt_bind_param($statement, 'is', $userId, $ciphertext);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
                $revokeTokens = mysqli_prepare($conn, 'DELETE FROM auth_tokens WHERE user_id = ?');
                mysqli_stmt_bind_param($revokeTokens, 'i', $userId);
                mysqli_stmt_execute($revokeTokens);
                mysqli_stmt_close($revokeTokens);
                unset($_SESSION['mfa_setup_secret']);
                $mfa = ['enabled' => true, 'secret' => $secret];
                $message = 'Đã bật MFA thành công cho tài khoản quản trị.';
            }
        }
    } catch (RuntimeException $exception) {
        error_log('[webbanhang] MFA setup failed: ' . $exception->getMessage());
        $error = 'MFA chưa được cấu hình trên máy chủ: ' . $exception->getMessage();
    }
}

if ($mfa === null || !$mfa['enabled'] || $mfa['secret'] === false) {
    try {
        if (!isset($_SESSION['mfa_setup_secret'])) {
            $_SESSION['mfa_setup_secret'] = mfaGenerateSecret();
        }
        $secret = $_SESSION['mfa_setup_secret'];
        $issuer = 'KhoaiLaptop';
        $label = rawurlencode($issuer . ':' . $_SESSION['email']);
        $otpauthUri = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    } catch (RuntimeException $exception) {
        $error = 'MFA chưa được cấu hình trên máy chủ.';
        $otpauthUri = '';
        $secret = '';
    }
}
?>
<?php require_once(__DIR__ . '/../layouts/header.php'); ?>
<div class="page-heading"><div><p class="eyebrow">BẢO MẬT TÀI KHOẢN</p><h1>MFA quản trị</h1><p>Xác thực bằng ứng dụng Authenticator.</p></div></div>
<?php if ($message !== ''): ?><div class="notice success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($mfa !== null && $mfa['enabled']): ?>
    <div class="notice success">MFA đang được bật cho tài khoản của bạn.</div>
<?php elseif ($otpauthUri !== ''): ?>
    <div class="editor">
        <h2>Thiết lập ứng dụng Authenticator</h2>
        <p>Thêm tài khoản bằng URI bên dưới trong Google Authenticator, Microsoft Authenticator hoặc ứng dụng tương thích TOTP.</p>
        <p><strong>Khóa thủ công:</strong> <?php echo htmlspecialchars($secret, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Provisioning URI:</strong><br><code><?php echo htmlspecialchars($otpauthUri, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <form method="post">
            <?php echo adminCsrfField(); ?>
            <label>Mã 6 chữ số<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
            <div class="form-actions"><button class="primary-button" type="submit">Bật MFA</button></div>
        </form>
    </div>
<?php endif; ?>
<?php require_once(__DIR__ . '/../layouts/footer.php'); ?>
