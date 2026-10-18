<?php
require_once(__DIR__ . '/../env_loader.php');

function mfaEncryptionKey()
{
    $key = appEnv('WEBBANHANG_MFA_KEY');
    if ($key === null || $key === false || strlen($key) < 32) {
        throw new RuntimeException('WEBBANHANG_MFA_KEY must contain at least 32 characters.');
    }

    return hash('sha256', $key, true);
}

function mfaBase32Encode($value)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    for ($i = 0, $length = strlen($value); $i < $length; $i++) {
        $binary .= str_pad(decbin(ord($value[$i])), 8, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    for ($i = 0, $length = strlen($binary); $i < $length; $i += 5) {
        $chunk = substr($binary, $i, 5);
        $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }

    return $encoded;
}

function mfaBase32Decode($value)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value = strtoupper(trim($value));
    $value = strtoupper(str_replace([' ', '-', '=', "\r", "\n", "\t"], '', trim((string)$value)));
    $binary = '';
    for ($i = 0, $length = strlen($value); $i < $length; $i++) {
        $position = strpos($alphabet, $value[$i]);
        if ($position === false) {
            return false;
        }
        $binary .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }

    $decoded = '';
    for ($i = 0, $length = strlen($binary) - 7; $i <= $length; $i += 8) {
        $decoded .= chr(bindec(substr($binary, $i, 8)));
    }

    return $decoded;
}

function mfaEncryptSecret($secret)
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', mfaEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt MFA secret.');
    }

    return base64_encode($iv . $tag . $ciphertext);
}

function mfaDecryptSecret($value)
{
    $payload = base64_decode($value, true);
    if ($payload === false || strlen($payload) < 28) {
        return false;
    }

    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    return openssl_decrypt($ciphertext, 'aes-256-gcm', mfaEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
}

function mfaGenerateSecret()
{
    return mfaBase32Encode(random_bytes(20));
}

function mfaCodeForSecret($secret, $time = null)
{
    $decoded = mfaBase32Decode($secret);
    if ($decoded === false) {
        return false;
    }

    $counter = (int)floor(($time === null ? time() : $time) / 30);
    $counterBytes = pack('N*', 0, $counter);
    $hash = hash_hmac('sha1', $counterBytes, $decoded, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);

    return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

function mfaVerifyCode($secret, $code)
{
    if (!is_string($secret) || $secret === '') {
        return false;
    }

    $code = trim((string)$code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $time = time();
    // Mo rong cua so thoi gian tu -4 den +4 chu ky (lech toi da +-120 giay / 2 phut) de tranh sai lech gio he thong/dien thoai
    for ($offset = -4; $offset <= 4; $offset++) {
        if (hash_equals((string)mfaCodeForSecret($secret, $time + ($offset * 30)), $code)) {
            return true;
        }
    }

    return false;
}

function mfaGetUser($conn, $userId)
{
    $statement = mysqli_prepare($conn, 'SELECT secret_ciphertext, enabled FROM user_mfa WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $secretCiphertext, $enabled);
    $found = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    if (!$found) {
        return null;
    }

    return [
        'secret' => mfaDecryptSecret($secretCiphertext),
        'enabled' => (int)$enabled === 1
    ];
}
