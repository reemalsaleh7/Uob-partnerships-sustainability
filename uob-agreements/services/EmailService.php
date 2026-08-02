<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config
            ?? require __DIR__ . '/../../config/email.php';
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): bool {

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log(
                'EmailService: Invalid recipient email: '
                . $toEmail
            );

            return false;
        }

        if (
            !isset($this->config['enabled'])
            || !$this->config['enabled']
        ) {
            error_log(
                'EmailService: Email sending is disabled.'
            );

            return false;
        }

        if (
            empty($this->config['host'])
            || empty($this->config['username'])
            || empty($this->config['password'])
            || empty($this->config['from_email'])
        ) {
            error_log(
                'EmailService: SMTP configuration is incomplete.'
            );

            return false;
        }

        $mail = new PHPMailer(true);

        try {

            // Use SMTP
            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            
            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer SMTP [$level]: $str");
            };
            // Gmail SMTP server
            $mail->Host = $this->config['host'];

            // Gmail SMTP port
            $mail->Port = (int) (
                $this->config['port'] ?? 587
            );

            // Enable SMTP authentication
            $mail->SMTPAuth = true;

            // Sender Gmail account
            $mail->Username =
                $this->config['username'];

            // Gmail App Password
            $mail->Password =
                $this->config['password'];

            // Encryption
            $encryption = strtolower(
                (string) (
                    $this->config['encryption']
                    ?? 'tls'
                )
            );

            if ($encryption === 'tls') {

                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_STARTTLS;

            } elseif ($encryption === 'ssl') {

                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_SMTPS;
            }

            // Support Arabic and other Unicode text
            $mail->CharSet = 'UTF-8';

            // Sender
            $mail->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
                    ?? 'UOB Partnerships & Sustainability'
            );

            // Receiver
            $mail->addAddress(
                $toEmail,
                $toName
            );

            // HTML email
            $mail->isHTML(true);

            // Subject
            $mail->Subject = $subject;

            // HTML body
            $mail->Body = $htmlBody;

            // Plain-text fallback
            $mail->AltBody =
                $textBody
                ?? strip_tags(
                    str_replace(
                        [
                            '<br>',
                            '<br/>',
                            '<br />'
                        ],
                        PHP_EOL,
                        $htmlBody
                    )
                );

            // Send email
            $mail->send();

            error_log(
                'EmailService: Email sent successfully to '
                . $toEmail
            );

            return true;

        } catch (Exception $e) {

            error_log(
                'EmailService: Failed to send email to '
                . $toEmail
                . ' - '
                . $e->getMessage()
            );

            return false;
        }
    }
}