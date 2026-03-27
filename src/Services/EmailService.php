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

    /**
     * Send a branded quote email with HTML + plain text parts.
     * $data keys: brandName, appUrl, quoteNumber, clientName, total, validUntil, publicUrl,
     * senderName, senderEmail, platformName
     */
    public static function sendQuote(string $to, string $subject, array $data): bool
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

        $senderName = (string)($data['senderName'] ?? '');
        $senderEmail = (string)($data['senderEmail'] ?? '');
        $platformName = (string)($data['platformName'] ?? (Config::get('app_name') ?: 'Moni'));

        if (!empty($cfg['from_address'])) {
            $fromName = $senderName !== '' ? ($senderName . ' vía ' . $platformName) : (string)($cfg['from_name'] ?? $platformName);
            $mail->setFrom((string)$cfg['from_address'], $fromName);
            if ($senderEmail !== '') {
                $mail->addReplyTo($senderEmail, $senderName !== '' ? $senderName : $platformName);
            }
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;

        $brandName = (string)($data['brandName'] ?? (Config::get('app_name') ?: 'Moni'));
        $appUrl = (string)($data['appUrl'] ?? (Config::get('app_url') ?: '#'));
        $quoteNumber = (string)($data['quoteNumber'] ?? '');
        $clientName = (string)($data['clientName'] ?? '');
        $total = (string)($data['total'] ?? '');
        $validUntil = (string)($data['validUntil'] ?? '');
        $publicUrl = (string)($data['publicUrl'] ?? '');
        $senderName = (string)($data['senderName'] ?? $brandName);
        $senderEmail = (string)($data['senderEmail'] ?? '');
        $platformName = (string)($data['platformName'] ?? (Config::get('app_name') ?: 'Moni'));

        $html = self::renderTemplate(__DIR__ . '/../../templates/emails/quote.php', compact('brandName', 'appUrl', 'quoteNumber', 'clientName', 'total', 'validUntil', 'publicUrl', 'senderName', 'senderEmail', 'platformName'));
        $text = self::renderTemplate(__DIR__ . '/../../templates/emails/quote.txt.php', compact('brandName', 'appUrl', 'quoteNumber', 'clientName', 'total', 'validUntil', 'publicUrl', 'senderName', 'senderEmail', 'platformName'));

        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        if (!$mail->send()) {
            throw new RuntimeException('Error SMTP: ' . $mail->ErrorInfo);
        }
        return true;
    }

    /**
     * Send a notification to the professional when a quote has been accepted or rejected.
     * $data keys: senderName, senderEmail, platformName, quoteNumber, clientName, statusLabel,
     * statusMessage, publicUrl, rejectionReason, actedAt, appUrl
     */
    public static function sendQuoteStatusNotification(string $to, string $subject, array $data): bool
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

        $platformName = (string)($data['platformName'] ?? (Config::get('app_name') ?: 'Moni'));
        if (!empty($cfg['from_address'])) {
            $mail->setFrom((string)$cfg['from_address'], $platformName);
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;

        $senderName = (string)($data['senderName'] ?? '');
        $senderEmail = (string)($data['senderEmail'] ?? '');
        $quoteNumber = (string)($data['quoteNumber'] ?? '');
        $clientName = (string)($data['clientName'] ?? '');
        $statusLabel = (string)($data['statusLabel'] ?? '');
        $statusMessage = (string)($data['statusMessage'] ?? '');
        $publicUrl = (string)($data['publicUrl'] ?? '');
        $rejectionReason = (string)($data['rejectionReason'] ?? '');
        $actedAt = (string)($data['actedAt'] ?? '');
        $appUrl = (string)($data['appUrl'] ?? (Config::get('app_url') ?: '#'));

        $html = self::renderTemplate(__DIR__ . '/../../templates/emails/quote_status.php', compact(
            'senderName',
            'senderEmail',
            'platformName',
            'quoteNumber',
            'clientName',
            'statusLabel',
            'statusMessage',
            'publicUrl',
            'rejectionReason',
            'actedAt',
            'appUrl'
        ));
        $text = self::renderTemplate(__DIR__ . '/../../templates/emails/quote_status.txt.php', compact(
            'senderName',
            'senderEmail',
            'platformName',
            'quoteNumber',
            'clientName',
            'statusLabel',
            'statusMessage',
            'publicUrl',
            'rejectionReason',
            'actedAt',
            'appUrl'
        ));

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
