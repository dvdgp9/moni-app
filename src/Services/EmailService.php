<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Support\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class EmailService
{
    public static function sendTest(string $to, string $subject = 'Prueba de email', string $body = 'Hola, este es un email de prueba de Moni.'): bool
    {
        $mail = new PHPMailer(true);
        $cfg = Config::get('mail');

        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->SMTPAuth = !empty($cfg['username']);
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
        }
        $enc = strtolower((string)$cfg['encryption']);
        if (in_array($enc, ['tls', 'starttls'], true)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['from_address'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = nl2br($body);
        $mail->AltBody = strip_tags($body);

        return $mail->send();
    }
}
