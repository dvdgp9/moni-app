<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Support\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

final class EmailService
{
    public static function sendTest(string $to, string $subject = 'Prueba de email', string $body = 'Hola, este es un email de prueba de Moni.'): bool
    {
        $mail = new PHPMailer(true);
        $cfg = Config::get('mail');
        $debug = Config::get('debug');

        $mail->isSMTP();
        $mail->Host = (string)$cfg['host'];
        $mail->Port = (int)$cfg['port'];
        $mail->SMTPAuth = !empty($cfg['username']);
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
        }
        // Normalizar cifrado según puerto
        $enc = strtolower((string)($cfg['encryption'] ?? ''));
        if ($mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL implícito
        } elseif ($mail->Port === 587 || $enc === 'tls' || $enc === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // Timeouts razonables
        $mail->Timeout = 15; // segundos
        $mail->SMTPKeepAlive = false;
        $mail->CharSet = 'UTF-8';

        // Debug de SMTP si está activado APP_DEBUG
        $mail->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        if ($debug) {
            // Relajar verificación solo en debug para diagnosticar certificados (opcional)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }
        if (!empty($cfg['from_address'])) {
            $mail->setFrom((string)$cfg['from_address'], (string)($cfg['from_name'] ?? ''));
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = nl2br($body);
        $mail->AltBody = strip_tags($body);

        if (!$mail->send()) {
            throw new RuntimeException('Error SMTP: ' . $mail->ErrorInfo);
        }
        return true;
    }

    /**
     * Send a branded reminder email with HTML + plain text parts.
     * $data keys: title, range, links(array[label,url]), brandName, appUrl
     */
    public static function sendReminder(string $to, string $subject, array $data): bool
    {
        $mail = new PHPMailer(true);
        $cfg = Config::get('mail');
        $debug = Config::get('debug');

        $mail->isSMTP();
        $mail->Host = (string)$cfg['host'];
        $mail->Port = (int)$cfg['port'];
        $mail->SMTPAuth = !empty($cfg['username']);
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
        }
        $enc = strtolower((string)($cfg['encryption'] ?? ''));
        if ($mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($mail->Port === 587 || $enc === 'tls' || $enc === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;

        if (!empty($cfg['from_address'])) {
            $mail->setFrom((string)$cfg['from_address'], (string)($cfg['from_name'] ?? ''));
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;

        // Render templates
        $brandName = (string)($data['brandName'] ?? (Config::get('app_name') ?: 'Moni'));
        $appUrl = (string)($data['appUrl'] ?? (Config::get('app_url') ?: '#'));
        $title = (string)($data['title'] ?? '');
        $range = (string)($data['range'] ?? '');
        $links = (array)($data['links'] ?? []);

        $html = self::renderTemplate(__DIR__ . '/../../templates/emails/reminder.php', compact('brandName','appUrl','title','range','links'));
        $text = self::renderTemplate(__DIR__ . '/../../templates/emails/reminder.txt.php', compact('brandName','appUrl','title','range','links'));

        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        if (!$mail->send()) {
            throw new RuntimeException('Error SMTP: ' . $mail->ErrorInfo);
        }
        return true;
    }

    private static function renderTemplate(string $file, array $vars): string
    {
        if (!is_file($file)) {
            return '';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
