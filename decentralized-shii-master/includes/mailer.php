<?php
/**
 * Lightweight email sender used across the site (welcome emails, application
 * status updates, withdrawal/wallet confirmations). Uses PHPMailer over SMTP
 * so it works on Railway (no local mail server) as well as cPanel.
 *
 * If SMTP_HOST hasn't been configured yet, send_email() just returns false
 * and logs a note — it never throws or breaks the page that called it.
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send an HTML email. Returns true on success, false if SMTP isn't
 * configured yet or sending failed (never throws).
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (SMTP_HOST === '') {
        error_log('[mailer] SMTP_HOST not set — skipped email "' . $subject . '" to ' . $toEmail);
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_wrap($subject, $htmlBody);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[mailer] send failed to ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    } catch (\Throwable $e) {
        error_log('[mailer] unexpected error: ' . $e->getMessage());
        return false;
    }
}

/** Wraps inner HTML in a simple branded email shell. */
function email_wrap(string $title, string $innerHtml): string {
    $site = e(SITE_NAME);
    return '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6fa;padding:32px 0">'
      . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0">'
      . '<div style="background:#0f172a;padding:22px 28px"><span style="color:#f59e0b;font-weight:700;font-size:18px">' . $site . '</span></div>'
      . '<div style="padding:28px;color:#1e293b;font-size:15px;line-height:1.6">' . $innerHtml . '</div>'
      . '<div style="padding:18px 28px;background:#f4f6fa;color:#64748b;font-size:12.5px">This is an automated message from ' . $site . '. Please do not reply directly to this email.</div>'
      . '</div></div>';
}
