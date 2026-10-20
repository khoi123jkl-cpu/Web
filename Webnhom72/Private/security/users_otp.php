<?php
require_once(__DIR__ . '/../env_loader.php');

function userOtpGenerateCode()
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function userOtpBuildEmailHtml($code, $purpose = 'login', $fullname = '')
{
    $displayName = htmlspecialchars($fullname !== '' ? $fullname : 'Quý khách', ENT_QUOTES, 'UTF-8');
    $title = ($purpose === 'register')
        ? 'Xác thực đăng ký tài khoản'
        : 'Xác thực đăng nhập tài khoản';

    $actionText = ($purpose === 'register')
        ? 'hoàn tất đăng ký tài khoản mới'
        : 'đăng nhập vào hệ thống';

    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f7f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #182329;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f7f6; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 540px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06); border: 1px solid #dbe5e4; overflow: hidden;" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="background: linear-gradient(135deg, #147d78 0%, #0b5c5a 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: 1px;">KHOAILAPTOP STORE</h1>
                            <p style="margin: 6px 0 0 0; color: #dce9e5; font-size: 13px; text-transform: uppercase; letter-spacing: 0.1em;">Hệ thống bảo mật xác thực (OTP)</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 40px;">
                            <h2 style="margin: 0 0 16px 0; color: #182329; font-size: 20px; font-weight: 600;">{$title}</h2>
                            <p style="margin: 0 0 18px 0; font-size: 15px; line-height: 1.6; color: #506166;">
                                Xin chào <strong>{$displayName}</strong>,
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #506166;">
                                Bạn vừa thực hiện yêu cầu {$actionText} tại <strong>KhoaiLaptop Store</strong>. Vui lòng sử dụng mã xác thực gồm 6 chữ số dưới đây để tiếp tục:
                            </p>
                            <div style="text-align: center; margin: 30px 0;">
                                <div style="display: inline-block; background: #f0f7f6; border: 2px dashed #147d78; border-radius: 12px; padding: 18px 36px; text-align: center;">
                                    <span style="font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #147d78; font-family: 'Courier New', Courier, monospace;">{$code}</span>
                                </div>
                            </div>

                            <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 1.5; color: #d9534f; font-weight: 600; text-align: center;">
                                ⏱ Mã OTP có hiệu lực trong vòng 5 phút.
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 13px; line-height: 1.6; color: #718087; text-align: center;">
                                Tuyệt đối không chia sẻ mã này với bất kỳ ai để đảm bảo an toàn cho tài khoản của bạn.
                            </p>
                            
                            <hr style="border: none; border-top: 1px solid #eef2f2; margin: 24px 0;">

                            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #9aa7a8;">
                                Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này hoặc liên hệ ngay với quản trị viên để được hỗ trợ.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #f8faf9; padding: 20px 40px; text-align: center; border-top: 1px solid #dbe5e4;">
                            <p style="margin: 0; font-size: 12px; color: #8da1a1;">
                                © 2026 KhoaiLaptop Store. Bảo lưu mọi quyền.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function userOtpSendSmtpSocket($host, $port, $secure, $user, $pass, $from, $fromName, $to, $subject, $htmlBody)
{
    $timeout = 8;
    $isSsl = ($secure === 'ssl' || (int)$port === 465);
    $socketAddress = ($isSsl ? 'ssl://' : '') . $host . ':' . $port;

    $sslOptions = [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'peer_name'         => $host,
        'allow_self_signed' => false,
    ];
    $caFile = (string)appEnv('SMTP_CAFILE', '');
    if ($caFile !== '' && is_readable($caFile)) {
        $sslOptions['cafile'] = $caFile;
    }
    $context = stream_context_create(['ssl' => $sslOptions]);

    $socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log("[KhoaiLaptop SMTP] Lỗi kết nối tới $socketAddress: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $read = function () use ($socket) {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $send = function ($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $initResp = $read();
    if (substr($initResp, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $clientHost = gethostname() ?: 'localhost';
    $send('EHLO ' . $clientHost);

    if (!$isSsl) {
        $tlsResp = $send('STARTTLS');
        if (substr($tlsResp, 0, 3) !== '220') {
            fclose($socket);
            return false;
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);
            return false;
        }

        $send('EHLO ' . $clientHost);
    }

    $authResp = $send('AUTH LOGIN');
    if (substr($authResp, 0, 3) !== '334') {
        fclose($socket);
        return false;
    }

    $userResp = $send(base64_encode($user));
    if (substr($userResp, 0, 3) !== '334') {
        fclose($socket);
        return false;
    }

    $passResp = $send(base64_encode(str_replace(' ', '', $pass)));
    if (substr($passResp, 0, 3) !== '235') {
        error_log('[KhoaiLaptop SMTP] Xác thực tài khoản SMTP thất bại: ' . trim($passResp));
        fclose($socket);
        return false;
    }

    $fromResp = $send('MAIL FROM:<' . $from . '>');
    if (substr($fromResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $rcptResp = $send('RCPT TO:<' . $to . '>');
    if (substr($rcptResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $dataResp = $send('DATA');
    if (substr($dataResp, 0, 3) !== '354') {
        fclose($socket);
        return false;
    }

    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64'
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.";
    $sendResp = $send($message);
    $send('QUIT');
    fclose($socket);

    return substr($sendResp, 0, 3) === '250';
}

function userOtpSendSmtp($to, $subject, $htmlBody)
{
    $host = (string)appEnv('SMTP_HOST', 'smtp.gmail.com');
    $port = (int)appEnv('SMTP_PORT', 587);
    $user = (string)appEnv('SMTP_USER', '');
    $pass = (string)appEnv('SMTP_PASS', '');
    $from = (string)appEnv('SMTP_FROM', $user);
    $fromName = (string)appEnv('SMTP_FROM_NAME', 'KhoaiLaptop Store');
    $secure = strtolower((string)appEnv('SMTP_SECURE', 'tls'));

    if ($user === '' || $pass === '') {
        error_log('[KhoaiLaptop SMTP] Chưa cấu hình SMTP_USER/SMTP_PASS trong config.env.php');
        return false;
    }
    if ($from === '') {
        $from = $user;
    }

    $sent = userOtpSendSmtpSocket($host, $port, $secure, $user, $pass, $from, $fromName, $to, $subject, $htmlBody);
    if ($sent) {
        return true;
    }

    $fallbackPort = ($port === 465) ? 587 : 465;
    $fallbackSecure = ($fallbackPort === 465) ? 'ssl' : 'tls';

    error_log("[KhoaiLaptop SMTP] Cổng $port ($secure) gặp sự cố, tự động chuyển đổi thử cổng $fallbackPort ($fallbackSecure)...");
    return userOtpSendSmtpSocket($host, $fallbackPort, $fallbackSecure, $user, $pass, $from, $fromName, $to, $subject, $htmlBody);
}

function userOtpSendEmail($to, $code, $purpose = 'login', $fullname = '')
{
    $subject = ($purpose === 'register')
        ? 'KhoaiLaptop - Mã OTP hoàn tất đăng ký tài khoản'
        : 'KhoaiLaptop - Mã OTP xác thực đăng nhập';

    $htmlBody = userOtpBuildEmailHtml($code, $purpose, $fullname);

    $sent = userOtpSendSmtp($to, $subject, $htmlBody);

    if (!$sent) {
        $from = (string)appEnv('SMTP_FROM', (string)appEnv('SMTP_USER', 'noreply@khoailaptop.com'));
        $fromName = (string)appEnv('SMTP_FROM_NAME', 'KhoaiLaptop Store');
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion()
        ];
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    }

    $statusText = $sent ? 'Gửi thành công' : 'Gửi thất bại qua SMTP/mail()';
    error_log("[KhoaiLaptop OTP] Mục đích: $purpose | Email: $to | Trạng thái: $statusText");

    return $sent;
}
