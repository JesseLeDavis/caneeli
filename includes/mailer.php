<?php
// Transactional email helper (SMTP via PHPMailer).
//
// send_mail() is intentionally fail-safe: if SMTP isn't configured, or sending
// throws, it logs and returns false instead of bubbling an exception. Callers
// (the Stripe webhook, the admin fulfill flow) must never break because an
// email couldn't go out.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a single HTML email.
 *
 * @param string      $toEmail Recipient address
 * @param string      $toName  Recipient display name
 * @param string      $subject Subject line
 * @param string      $html    HTML body (full document)
 * @param string      $altText Plain-text fallback (auto-derived if empty)
 * @param string|null $bcc     Optional BCC address
 * @return bool  true on success, false if not configured or send failed
 */
function send_mail($toEmail, $toName, $subject, $html, $altText = '', $bcc = null)
{
    $toEmail = trim((string) $toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("send_mail: missing/invalid recipient for subject \"$subject\"");
        return false;
    }
    if (SMTP_HOST === '' || MAIL_FROM_EMAIL === '') {
        error_log("send_mail: SMTP not configured — skipping email \"$subject\" to $toEmail");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        if ($bcc) {
            $mail->addBCC($bcc);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $altText !== ''
            ? $altText
            : trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('send_mail failed: ' . $mail->ErrorInfo);
        return false;
    } catch (\Throwable $e) {
        error_log('send_mail failed: ' . $e->getMessage());
        return false;
    }
}
