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
        $todayStr = $today->format('Y-m-d');
        $due = [];

        // 1) Read enabled reminders from DB (single source of truth)
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT title, event_date, end_date, recurring FROM reminders WHERE enabled = 1');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string)$row['event_date'];
            $end  = isset($row['end_date']) ? (string)$row['end_date'] : '';
            $rec = (string)($row['recurring'] ?? 'yearly');
            if ($rec === 'yearly') {
                // Yearly recurrence: interpret start and optional end within the current year window
                $startMD = substr($date, 5); // MM-DD
                $start = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $startMD);
                if ($end) {
                    $endMD = substr($end, 5);
                    $endDt = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $endMD);
                    // Handle wrap-around ranges (e.g., Dec -> Jan)
                    if ($endDt < $start) {
                        // if today is in Jan and range wraps, move start to previous year
                        $start->modify('-1 year');
                    }
                    if ($today >= $start && $today <= $endDt) {
                        $due[] = (string)$row['title'];
                    }
                } else {
                    // No end_date: use the start day only
                    if ($start->format('Y-m-d') === $todayStr) {
                        $due[] = (string)$row['title'];
                    }
                }
            } else { // one-off
                if ($end) {
                    $start = new DateTime($date);
                    $endDt = new DateTime($end);
                    if ($today >= $start && $today <= $endDt) {
                        $due[] = (string)$row['title'];
                    }
                } elseif ($date === $todayStr) {
                    $due[] = (string)$row['title'];
                }
            }
        }

        // 2) Backward-compat: settings.custom_dates (if someone no usa la tabla)
        $custom = (array)Config::get('settings.custom_dates', []);
        foreach ($custom as $d) {
            if ($d === $todayStr) {
                $due[] = 'Recordatorio personalizado (' . $d . ')';
            }
        }

        // Deduplicate by title
        return array_values(array_unique($due));
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
