<?php
declare(strict_types=1);

namespace Moni\Services;

use DateInterval;
use DateTime;
use Moni\Database;
use Moni\Support\Config;
use PDO;

final class ReminderService
{
    /**
     * Returns list of titles for events due today (quarter openings + custom dates)
     */
    public static function getDueEventsForToday(): array
    {
        if (!Config::get('settings.reminders_enabled', true)) {
            return [];
        }
        $tz = Config::get('settings.timezone', 'Europe/Madrid');
        @date_default_timezone_set($tz);
        $today = new DateTime('today');
        $y = (int)$today->format('Y');

        // Quarterly openings: 1 Jan, 1 Apr, 1 Jul, 1 Oct
        $quarters = ["$y-01-01" => 'Inicio trimestre Q1', "$y-04-01" => 'Inicio trimestre Q2', "$y-07-01" => 'Inicio trimestre Q3', "$y-10-01" => 'Inicio trimestre Q4'];

        $due = [];
        $todayStr = $today->format('Y-m-d');
        if (isset($quarters[$todayStr])) {
            $due[] = $quarters[$todayStr];
        }

        // Custom dates from settings in DB (array of YYYY-MM-DD)
        $custom = (array)Config::get('settings.custom_dates', []);
        foreach ($custom as $d) {
            if ($d === $todayStr) {
                $due[] = 'Recordatorio personalizado (' . $d . ')';
            }
        }

        // Also read table reminders (optional yearly recurring)
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT title, event_date, recurring FROM reminders');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = $row['event_date'];
            $rec = $row['recurring'] ?? 'yearly';
            if ($rec === 'yearly') {
                // Compare month-day
                $md = substr($date, 5);
                if ($md === substr($todayStr, 5)) {
                    $due[] = (string)$row['title'];
                }
            } elseif ($date === $todayStr) {
                $due[] = (string)$row['title'];
            }
        }

        return $due;
    }

    /**
     * Send reminders for today to configured notify email, if not already sent (idempotent by event title+date+recipient)
     */
    public static function runForToday(): array
    {
        $results = ['sent' => [], 'skipped' => [], 'errors' => []];
        $notify = (string)Config::get('settings.notify_email', '');
        if ($notify === '') {
            return $results; // nothing to send
        }

        $tz = Config::get('settings.timezone', 'Europe/Madrid');
        @date_default_timezone_set($tz);
        $today = new DateTime('today');
        $todayStr = $today->format('Y-m-d');

        $events = self::getDueEventsForToday();
        $pdo = Database::pdo();

        foreach ($events as $title) {
            // Check if already sent today for this recipient
            $stmt = $pdo->prepare('SELECT id FROM reminder_logs WHERE event_date = :d AND sent_to = :to AND reminder_id IS NULL AND title = :t LIMIT 1');
            // In case older schema lacks title column, fallback logic with reminder_id NULL and event_date+recipient
            try {
                $stmt->execute([':d' => $todayStr, ':to' => $notify, ':t' => $title]);
                $exists = $stmt->fetchColumn();
            } catch (\Throwable $e) {
                $stmt2 = $pdo->prepare('SELECT id FROM reminder_logs WHERE event_date = :d AND sent_to = :to LIMIT 1');
                $stmt2->execute([':d' => $todayStr, ':to' => $notify]);
                $exists = $stmt2->fetchColumn();
            }

            if ($exists) {
                $results['skipped'][] = $title;
                continue;
            }

            $subject = 'Recordatorio fiscal: ' . $title;
            $body = "Hola,\n\nRecuerda: $title.\nFecha: $todayStr.\n\n— Moni";
            try {
                EmailService::sendTest($notify, $subject, nl2br($body));
                $ins = $pdo->prepare('INSERT INTO reminder_logs (reminder_id, event_date, sent_to) VALUES (NULL, :d, :to)');
                $ins->execute([':d' => $todayStr, ':to' => $notify]);
                $results['sent'][] = $title;
            } catch (\Throwable $e) {
                $results['errors'][] = $title . ' => ' . $e->getMessage();
            }
        }

        return $results;
    }
}
