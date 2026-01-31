<?php
// Minimal SMTP mailer (STARTTLS/SSL) for admin alerts.

function smtp_send($host, $port, $user, $pass, $fromEmail, $fromName, $toEmail, $subject, $body, $secure = '') {
    $port = intval($port);
    if ($port <= 0) {
        return [false, 'Invalid port'];
    }

    $useSsl = ($secure === 'ssl' || $port === 465);
    $useTls = ($secure === 'tls' || $port === 587);
    $connectHost = $useSsl ? "ssl://{$host}" : $host;

    $fp = @fsockopen($connectHost, $port, $errno, $errstr, 10);
    if (!$fp) {
        return [false, "Connect failed: {$errstr} ({$errno})"];
    }

    $resp = smtp_read($fp);
    if (strpos($resp, '220') !== 0) {
        fclose($fp);
        return [false, "Bad greeting: {$resp}"];
    }

    $domain = 'brokeant.com';
    smtp_write($fp, "EHLO {$domain}");
    $resp = smtp_read($fp);
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [false, "EHLO failed: {$resp}"];
    }

    if ($useTls) {
        smtp_write($fp, "STARTTLS");
        $resp = smtp_read($fp);
        if (strpos($resp, '220') !== 0) {
            fclose($fp);
            return [false, "STARTTLS failed: {$resp}"];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return [false, "TLS negotiation failed"];
        }
        smtp_write($fp, "EHLO {$domain}");
        $resp = smtp_read($fp);
        if (strpos($resp, '250') !== 0) {
            fclose($fp);
            return [false, "EHLO after TLS failed: {$resp}"];
        }
    }

    if ($user !== '' && $pass !== '') {
        smtp_write($fp, "AUTH LOGIN");
        $resp = smtp_read($fp);
        if (strpos($resp, '334') !== 0) {
            fclose($fp);
            return [false, "AUTH LOGIN failed: {$resp}"];
        }
        smtp_write($fp, base64_encode($user));
        $resp = smtp_read($fp);
        if (strpos($resp, '334') !== 0) {
            fclose($fp);
            return [false, "AUTH user failed: {$resp}"];
        }
        smtp_write($fp, base64_encode($pass));
        $resp = smtp_read($fp);
        if (strpos($resp, '235') !== 0) {
            fclose($fp);
            return [false, "AUTH pass failed: {$resp}"];
        }
    }

    smtp_write($fp, "MAIL FROM:<{$fromEmail}>");
    $resp = smtp_read($fp);
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [false, "MAIL FROM failed: {$resp}"];
    }

    smtp_write($fp, "RCPT TO:<{$toEmail}>");
    $resp = smtp_read($fp);
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        fclose($fp);
        return [false, "RCPT TO failed: {$resp}"];
    }

    smtp_write($fp, "DATA");
    $resp = smtp_read($fp);
    if (strpos($resp, '354') !== 0) {
        fclose($fp);
        return [false, "DATA failed: {$resp}"];
    }

    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $headers = [];
    $headers[] = "From: {$safeFromName} <{$fromEmail}>";
    $headers[] = "To: <{$toEmail}>";
    $headers[] = "Subject: {$safeSubject}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $msg = str_replace("\n.", "\n..", $msg);
    $msg = str_replace("\r\n.\r\n", "\r\n..\r\n", $msg);

    fwrite($fp, $msg . "\r\n.\r\n");
    $resp = smtp_read($fp);
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [false, "Message send failed: {$resp}"];
    }

    smtp_write($fp, "QUIT");
    fclose($fp);
    return [true, 'OK'];
}

function smtp_write($fp, $line) {
    fwrite($fp, $line . "\r\n");
}

function smtp_read($fp) {
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 515);
        if ($line === false) {
            break;
        }
        $data .= $line;
        // Multi-line responses end when 4th char is a space
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return trim($data);
}

function send_admin_alert($subject, $body) {
    $to = getenv('ADMIN_ALERT_EMAIL') ?: 'sales@brokeant.com';
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'sales@brokeant.com';
    $fromName = getenv('SMTP_FROM_NAME') ?: 'BrokeAnt';
    $host = getenv('SMTP_HOST') ?: '';
    $port = getenv('SMTP_PORT') ?: '';
    $user = getenv('SMTP_USER') ?: '';
    $pass = getenv('SMTP_PASS') ?: '';
    $secure = getenv('SMTP_SECURE') ?: '';

    if ($host && $port && $user && $pass) {
        smtp_send($host, $port, $user, $pass, $fromEmail, $fromName, $to, $subject, $body, $secure);
        return;
    }

    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    @mail($to, $subject, $body, $headers);
}
