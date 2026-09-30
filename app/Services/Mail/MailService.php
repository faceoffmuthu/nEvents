<?php

declare(strict_types=1);

namespace NEvents\Services\Mail;

use NEvents\Core\Application;
use NEvents\Core\Log;
use NEvents\Core\View;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Centralized outbound mail. Every email the app sends (verification,
 * password reset, and any future notification email) goes through here —
 * SMTP configuration lives in one place, not duplicated per call site.
 *
 * Never claims success unless PHPMailer's send() actually accepted the
 * message for delivery. Never throws to the caller — failures are logged
 * and reported back as a bool so controllers can show an honest message.
 */
class MailService
{
    public function sendVerificationEmail(array $user, string $rawToken): bool
    {
        $app       = Application::getInstance();
        $ttlHours  = (int) $app->config('app.auth.verify_token_ttl_hours', 24);
        $appUrl    = rtrim((string) $app->config('app.url', ''), '/');
        $appName   = (string) $app->config('app.name', 'N Events');
        $verifyUrl = $appUrl . '/verify-email/' . rawurlencode($rawToken);

        Log::get()->info('verification_email_requested', ['email' => $this->mask($user['email'])]);

        $view = new View();
        $html = $view->render('emails.verify-email', [
            'name'      => $user['name'],
            'verifyUrl' => $verifyUrl,
            'ttlHours'  => $ttlHours,
            'appName'   => $appName,
        ]);
        $text = "Hi {$user['name']},\n\n"
            . "Welcome to {$appName}! Please verify your email address by opening this link:\n"
            . "{$verifyUrl}\n\n"
            . "This link expires in {$ttlHours} hours.\n\n"
            . "If you did not create this account, you can safely ignore this email.\n";

        return $this->send(
            $user['email'],
            $user['name'],
            "Verify your email — {$appName}",
            $html,
            $text,
            successEvent: 'verification_email_sent',
            failureEvent: 'verification_email_failed'
        );
    }

    public function sendPasswordResetEmail(array $user, string $rawToken): bool
    {
        $app      = Application::getInstance();
        $appUrl   = rtrim((string) $app->config('app.url', ''), '/');
        $appName  = (string) $app->config('app.name', 'N Events');
        $resetUrl = $appUrl . '/reset-password/' . rawurlencode($rawToken);

        Log::get()->info('password_reset_email_requested', ['email' => $this->mask($user['email'])]);

        $view = new View();
        $html = $view->render('emails.reset-password', [
            'name'     => $user['name'],
            'resetUrl' => $resetUrl,
            'appName'  => $appName,
        ]);
        $text = "Hi {$user['name']},\n\n"
            . "We received a request to reset your {$appName} password. Open this link to choose a new one:\n"
            . "{$resetUrl}\n\n"
            . "This link expires in 1 hour. If you did not request this, you can safely ignore this email.\n";

        return $this->send(
            $user['email'],
            $user['name'],
            "Reset your password — {$appName}",
            $html,
            $text,
            successEvent: 'password_reset_email_sent',
            failureEvent: 'password_reset_email_failed'
        );
    }

    /**
     * Digests, reminders, event updates and submission-status emails. Called
     * only from the background queue (NotificationService::deliverDue) — never
     * inside a normal web request.
     */
    public function sendNotification(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        return $this->send($toEmail, $toName, $subject, $html, $text,
            successEvent: 'notification_email_sent',
            failureEvent: 'notification_email_failed');
    }

    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $text,
        string $successEvent,
        string $failureEvent
    ): bool {
        $cfg = Application::getInstance()->config('app.mail', []);

        if (empty($cfg['host']) || empty($cfg['username'])) {
            Log::get()->warning($failureEvent, [
                'email'  => $this->mask($toEmail),
                'reason' => 'SMTP is not configured (MAIL_HOST/MAIL_USERNAME empty)',
            ]);
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->Port       = $cfg['port'];
            $mail->SMTPSecure = match ($cfg['encryption']) {
                'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
                'tls'   => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom($cfg['from_address'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;

            $mail->send();
            Log::get()->info($successEvent, ['email' => $this->mask($toEmail)]);
            return true;
        } catch (PHPMailerException) {
            // $mail->ErrorInfo is PHPMailer's own diagnostic text (host/auth/network
            // failures) — never contains the password, safe to log.
            Log::get()->error($failureEvent, ['email' => $this->mask($toEmail), 'error' => $mail->ErrorInfo]);
            return false;
        } catch (\Throwable $e) {
            Log::get()->error($failureEvent, ['email' => $this->mask($toEmail), 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function mask(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('*', max(1, mb_strlen($local) - mb_strlen($visible))) . $domain;
    }
}
