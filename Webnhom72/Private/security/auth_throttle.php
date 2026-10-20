<?php
function authThrottleEmailKey($email)
{
    return hash('sha256', strtolower(trim($email)));
}

function authThrottleIpKey()
{
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function authThrottleIsBlocked($conn, $emailKey, $ipKey)
{
    try {
        $statement = mysqli_prepare($conn, 'SELECT locked_until, TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining_seconds FROM auth_login_attempts WHERE email_key = ? AND ip_key = ? LIMIT 1');
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param($statement, 'ss', $emailKey, $ipKey);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $lockedUntil, $remainingSeconds);
        $found = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        return $found && $lockedUntil !== null && (int)$remainingSeconds > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function authThrottleRemainingSeconds($conn, $emailKey, $ipKey)
{
    try {
        $statement = mysqli_prepare($conn, 'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds FROM auth_login_attempts WHERE email_key = ? AND ip_key = ? LIMIT 1');
        if (!$statement) {
            return 0;
        }
        mysqli_stmt_bind_param($statement, 'ss', $emailKey, $ipKey);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $remainingSeconds);
        $found = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        if (!$found || $remainingSeconds === null) {
            return 0;
        }

        return max(0, (int)$remainingSeconds);
    } catch (Throwable $e) {
        return 0;
    }
}

function authThrottleRecordFailure($conn, $emailKey, $ipKey)
{
    try {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO auth_login_attempts (email_key, ip_key, failed_count, locked_until, updated_at)
             VALUES (?, ?, 1, NULL, NOW())
             ON DUPLICATE KEY UPDATE
                failed_count = IF(updated_at < DATE_SUB(NOW(), INTERVAL 60 SECOND), 1, failed_count + 1),
                locked_until = IF(
                    IF(updated_at < DATE_SUB(NOW(), INTERVAL 60 SECOND), 1, failed_count + 1) >= 5,
                    DATE_ADD(NOW(), INTERVAL 60 SECOND),
                    NULL
                ),
                updated_at = NOW()'
        );
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'ss', $emailKey, $ipKey);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
    } catch (Throwable $e) {
    }
}

function authThrottleClear($conn, $emailKey, $ipKey)
{
    try {
        $statement = mysqli_prepare($conn, 'DELETE FROM auth_login_attempts WHERE email_key = ? AND ip_key = ?');
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'ss', $emailKey, $ipKey);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
    } catch (Throwable $e) {
    }
}

function authRotateRememberToken($conn, $userId)
{
    try {
        $rememberToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rememberToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        $createdAt = date('Y-m-d H:i:s');

        $revokeTokens = mysqli_prepare($conn, 'DELETE FROM auth_tokens WHERE user_id = ?');
        if ($revokeTokens) {
            mysqli_stmt_bind_param($revokeTokens, 'i', $userId);
            mysqli_stmt_execute($revokeTokens);
            mysqli_stmt_close($revokeTokens);
        }

        $tokenStatement = mysqli_prepare($conn, 'INSERT INTO auth_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
        if ($tokenStatement) {
            mysqli_stmt_bind_param($tokenStatement, 'isss', $userId, $tokenHash, $expiresAt, $createdAt);
            mysqli_stmt_execute($tokenStatement);
            mysqli_stmt_close($tokenStatement);
        }

        setcookie('remember_token', $rememberToken, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'httponly' => true,
            'secure' => defined('APP_FORCE_HTTPS') ? APP_FORCE_HTTPS : false,
            'samesite' => 'Lax'
        ]);
    } catch (Throwable $e) {
    }
}
