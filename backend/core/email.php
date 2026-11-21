<?php
// ============================================================
// MARVELOUS HOTEL — Email Utility
// includes/email.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Send email using PHP mail() or SMTP via settings table / environment.
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = '', ?string &$errorMessage = null): bool {
    $fromEmail = getSetting('mail_from_email', getSetting('smtp_username', 'noreply@marveloushotel.com'));
    $fromName  = getSetting('mail_from_name', SITE_NAME);
    $mailer    = getSetting('mail_driver', 'smtp');

    if ($mailer === 'smtp' || !empty(getSetting('smtp_username', ''))) {
        return sendEmailSMTP($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName, $errorMessage);
    }

    $headers = [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
    ];

    // Use PHP mail()
    $headerStr = implode("\r\n", $headers);
    $res = @mail($to, $subject, $htmlBody, $headerStr);
    if (!$res) {
        $errorMessage = 'PHP mail() transport failed. Please configure Gmail SMTP in Settings.';
    }
    return $res;
}

/**
 * Resolve effective SMTP configuration from environment or settings table.
 */
function getSmtpConfig(): array {
    $host       = trim((string)(getenv('SMTP_HOST') ?: getSetting('smtp_host', 'smtp.gmail.com')));
    $port       = (int)(getenv('SMTP_PORT') ?: getSetting('smtp_port', 587));
    $username   = trim((string)(getenv('SMTP_USER') ?: getSetting('smtp_username', '')));
    $password   = (string)(getenv('SMTP_PASS') ?: getSetting('smtp_password', ''));
    $encryption = strtolower(trim((string)(getenv('SMTP_ENCRYPTION') ?: getSetting('smtp_encryption', 'tls'))));
    // Strip spaces in Google App Password (e.g. 'abcd efgh ijkl mnop' -> 'abcdefghijklmnop')
    $cleanPass  = str_replace([' ', "\t", "\r", "\n"], '', $password);

    return [
        'host'       => $host,
        'port'       => $port,
        'username'   => $username,
        'password'   => $cleanPass,
        'encryption' => $encryption,
    ];
}

/**
 * Determine whether SMTP credentials are actively configured to send verification emails.
 */
function isSmtpConfigured(): bool {
    $cfg = getSmtpConfig();
    return ($cfg['host'] !== '' && $cfg['username'] !== '' && $cfg['password'] !== '');
}

/**
 * Send email via SMTP using settings from database or environment.
 */
function sendEmailSMTP(string $to, string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName, ?string &$errorMessage = null): bool {
    $cfg           = getSmtpConfig();
    $host          = $cfg['host'];
    $port          = $cfg['port'];
    $username      = $cfg['username'];
    $cleanPassword = $cfg['password'];
    $encryption    = $cfg['encryption'];

    if (!$host || !$username || !$cleanPassword) {
        $errorMessage = 'SMTP configuration incomplete. Please set Gmail address and Google App Password.';
        error_log($errorMessage);
        return false;
    }

    // Autoload PHPMailer via Composer or direct source inclusion
    if (file_exists(SITE_ROOT . '/vendor/autoload.php')) {
        require_once SITE_ROOT . '/vendor/autoload.php';
    } else {
        $phpmailerPath = SITE_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        if (file_exists($phpmailerPath)) {
            require_once $phpmailerPath;
            require_once SITE_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once SITE_ROOT . '/vendor/phpmailer/phpmailer/src/Exception.php';
        }
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $errorMessage = 'PHPMailer library is not installed.';
        error_log($errorMessage);
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $cleanPassword;

        if ($encryption === 'ssl' || $port === 465) {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = $port;
        $mail->Timeout = 12;

        // Resolve clean sender email (prefer valid domain or authenticated Gmail)
        $actualFrom = (!empty($fromEmail) && strpos($fromEmail, '@') !== false && strpos($fromEmail, 'localhost') === false) 
                      ? $fromEmail 
                      : $username;

        $mail->setFrom($actualFrom, $fromName ?: SITE_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
        error_log('SMTP Error: ' . $errorMessage);
        return false;
    }
}

/**
 * Generate password reset email HTML.
 */
function emailTemplateResetPassword(string $resetLink, string $userName, int $expiryHours = 1): string {
    $siteName = SITE_NAME;
    $siteUrl  = SITE_URL;
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Reset - {$siteName}</title>
</head>
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f4f4f4;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <tr>
      <td style="background:linear-gradient(135deg,#c9a84c,#b8943f);padding:30px;text-align:center;">
        <h1 style="margin:0;color:#0f172a;font-family:Georgia,serif;font-size:24px;font-weight:700;">{$siteName}</h1>
        <p style="margin:8px 0 0;color:#1e293b;font-size:12px;text-transform:uppercase;letter-spacing:1px;">& Dormitory</p>
      </td>
    </tr>
    <tr>
      <td style="padding:30px;">
        <h2 style="margin:0 0 16px;color:#1e293b;font-size:20px;">Password Reset Request</h2>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">Hello <strong>{$userName}</strong>,</p>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">We received a request to reset your password. Click the button below to create a new password:</p>
        <div style="text-align:center;margin:24px 0;">
          <a href="{$resetLink}" style="display:inline-block;background:linear-gradient(135deg,#c9a84c,#b8943f);color:#0f172a;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(201,168,76,0.3);">Reset My Password</a>
        </div>
        <p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.6;">This link expires in <strong>{$expiryHours} hour</strong> and can only be used once.</p>
        <p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.6;">If you didn't request this, please ignore this email or contact support.</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
        <p style="margin:0;color:#94a3b8;font-size:12px;">If the button doesn't work, copy this link:<br><a href="{$resetLink}" style="color:#c9a84c;word-break:break-all;">{$resetLink}</a></p>
      </td>
    </tr>
    <tr>
      <td style="background:#f8fafc;padding:16px 30px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">{$siteName} | <a href="{$siteUrl}" style="color:#c9a84c;">{$siteName}</a></p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Generate email verification email HTML.
 */
function emailTemplateVerifyEmail(string $verifyLink, string $userName): string {
    $siteName = SITE_NAME;
    $siteUrl  = SITE_URL;
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email - {$siteName}</title>
</head>
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f4f4f4;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <tr>
      <td style="background:linear-gradient(135deg,#c9a84c,#b8943f);padding:30px;text-align:center;">
        <h1 style="margin:0;color:#0f172a;font-family:Georgia,serif;font-size:24px;font-weight:700;">{$siteName}</h1>
        <p style="margin:8px 0 0;color:#1e293b;font-size:12px;text-transform:uppercase;letter-spacing:1px;">& Dormitory</p>
      </td>
    </tr>
    <tr>
      <td style="padding:30px;">
        <h2 style="margin:0 0 16px;color:#1e293b;font-size:20px;">Verify Your Email Address</h2>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">Welcome, <strong>{$userName}</strong>!</p>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">Thank you for registering. Please verify your email address by clicking the button below:</p>
        <div style="text-align:center;margin:24px 0;">
          <a href="{$verifyLink}" style="display:inline-block;background:linear-gradient(135deg,#c9a84c,#b8943f);color:#0f172a;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(201,168,76,0.3);">Verify Email</a>
        </div>
        <p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.6;">If you didn't create this account, please ignore this email.</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
        <p style="margin:0;color:#94a3b8;font-size:12px;">If the button doesn't work, copy this link:<br><a href="{$verifyLink}" style="color:#c9a84c;word-break:break-all;">{$verifyLink}</a></p>
      </td>
    </tr>
    <tr>
      <td style="background:#f8fafc;padding:16px 30px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">{$siteName} | <a href="{$siteUrl}" style="color:#c9a84c;">{$siteName}</a></p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Generate generic notification email HTML.
 */
function emailTemplateNotification(string $title, string $message, string $userName, string $actionUrl = '', string $actionText = ''): string {
    $siteName = SITE_NAME;
    $btnHtml = $actionUrl && $actionText ? <<<BTN
    <div style="text-align:center;margin:24px 0;">
      <a href="{$actionUrl}" style="display:inline-block;background:linear-gradient(135deg,#c9a84c,#b8943f);color:#0f172a;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">{$actionText}</a>
    </div>
BTN : '';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} - {$siteName}</title>
</head>
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f4f4f4;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <tr>
      <td style="background:linear-gradient(135deg,#c9a84c,#b8943f);padding:30px;text-align:center;">
        <h1 style="margin:0;color:#0f172a;font-family:Georgia,serif;font-size:24px;font-weight:700;">{$siteName}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:30px;">
        <h2 style="margin:0 0 16px;color:#1e293b;font-size:20px;">{$title}</h2>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">Hello <strong>{$userName}</strong>,</p>
        <p style="margin:0 0 16px;color:#475569;line-height:1.6;">{$message}</p>
        {$btnHtml}
      </td>
    </tr>
    <tr>
      <td style="background:#f8fafc;padding:16px 30px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">{$siteName}</p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}