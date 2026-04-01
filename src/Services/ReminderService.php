<?php
declare(strict_types=1);

namespace Moni\Services;

use DateTime;
use Moni\Database;
use Moni\Support\Config;
use PDO;

final class ReminderService
{
    /**
     * Send reminders for all users for today.
     * In multi-user mode, each user uses their own settings + reminders.
     */
    public static function runForToday(): array
    {
        $results = ['sent' => [], 'skipped' => [], 'errors' => []];
        $pdo = Database::pdo();
        $reminderLogsHasTitle = self::reminderLogsHasTitleColumn($pdo);

        $usersStmt = $pdo->query('SELECT id FROM users ORDER BY id ASC');
        $users = $usersStmt ? $usersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($users as $u) {
            $userId = (int)($u['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $settings = self::loadUserSettings($pdo, $userId);
            $enabled = self::toBool($settings['reminders_enabled'] ?? null, true);
            if (!$enabled) {
                continue;
            }

            $notify = trim((string)($settings['notify_email'] ?? ''));
            if ($notify === '' || filter_var($notify, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $tz = trim((string)($settings['timezone'] ?? ''));
            if ($tz === '') {
                $tz = (string)Config::get('settings.timezone', 'Europe/Madrid');
            }
            @date_default_timezone_set($tz);

            $today = new DateTime('today');
            $todayStr = $today->format('Y-m-d');

            $remindersStmt = $pdo->prepare('SELECT id, title, event_date, end_date, recurring, links FROM reminders WHERE enabled = 1 AND user_id = :uid');
            $remindersStmt->execute([':uid' => $userId]);
            $reminders = $remindersStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reminders as $rowInfo) {
                if (!self::isReminderDueToday($rowInfo, $today, $todayStr)) {
                    continue;
                }

                $title = (string)($rowInfo['title'] ?? 'Recordatorio');
                $rid = (int)($rowInfo['id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }

                if (self::alreadySent($pdo, $rid, $notify, $today, $todayStr, (string)($rowInfo['recurring'] ?? 'yearly'), !empty($rowInfo['end_date']))) {
                    $results['skipped'][] = '[u:' . $userId . '] ' . $title;
                    continue;
                }

                try {
                    $range = self::formatRangeForEmail((string)$rowInfo['event_date'], $rowInfo['end_date'] ?? null);
                    $links = [];
                    if (!empty($rowInfo['links'])) {
                        $decoded = json_decode((string)$rowInfo['links'], true);
                        if (is_array($decoded)) {
                            $links = $decoded;
                        }
                    }

                    $payload = [
                        'title' => $title,
                        'range' => $range,
                        'links' => $links,
                        'brandName' => (string)Config::get('app_name', 'Moni'),
                        'appUrl' => (string)Config::get('app_url', '#'),
                    ];
                    $subject = 'Recordatorio: ' . $title;
                    EmailService::sendReminder($notify, $subject, $payload);

                    if ($reminderLogsHasTitle) {
                        $ins = $pdo->prepare('INSERT INTO reminder_logs (reminder_id, title, event_date, sent_to) VALUES (:rid, :t, :d, :to)');
                        $ins->execute([':rid' => $rid, ':t' => $title, ':d' => $todayStr, ':to' => $notify]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO reminder_logs (reminder_id, event_date, sent_to) VALUES (:rid, :d, :to)');
                        $ins->execute([':rid' => $rid, ':d' => $todayStr, ':to' => $notify]);
                    }

                    $results['sent'][] = '[u:' . $userId . '] ' . $title;
                } catch (\Throwable $e) {
                    $results['errors'][] = '[u:' . $userId . '] ' . $title . ' => ' . $e->getMessage();
                }
            }
        }

        return $results;
    }

    private static function loadUserSettings(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value
             FROM settings
             WHERE user_id = :uid
               AND setting_key IN ('notify_email', 'reminders_enabled', 'timezone')"
        );
        $stmt->execute([':uid' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    private static function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return $default;
    }

    private static function isReminderDueToday(array $row, DateTime $today, string $todayStr): bool
    {
        $date = (string)($row['event_date'] ?? '');
        $end = isset($row['end_date']) ? (string)$row['end_date'] : '';
        $rec = (string)($row['recurring'] ?? 'yearly');
        if ($date === '') {
            return false;
        }

        if ($rec === 'yearly') {
            $startMD = substr($date, 5);
            $start = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $startMD);
            if (!$start) {
                return false;
            }
            if ($end !== '') {
                $endMD = substr($end, 5);
                $endDt = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $endMD);
                if (!$endDt) {
                    return false;
                }
                if ($endDt < $start) {
                    $start->modify('-1 year');
                }
                return $today >= $start && $today <= $endDt;
            }
            return $start->format('Y-m-d') === $todayStr;
        }

        if ($end !== '') {
            try {
                $start = new DateTime($date);
                $endDt = new DateTime($end);
            } catch (\Throwable) {
                return false;
            }
            return $today >= $start && $today <= $endDt;
        }

        return $date === $todayStr;
    }

    private static function alreadySent(PDO $pdo, int $reminderId, string $notify, DateTime $today, string $todayStr, string $recurring, bool $hasRange): bool
    {
        if (!$hasRange) {
            $stmt = $pdo->prepare('SELECT id FROM reminder_logs WHERE reminder_id = :rid AND event_date = :d AND sent_to = :to LIMIT 1');
            $stmt->execute([':rid' => $reminderId, ':d' => $todayStr, ':to' => $notify]);
            return (bool)$stmt->fetchColumn();
        }

        if ($recurring === 'yearly') {
            $year = $today->format('Y');
            $stmt = $pdo->prepare('SELECT id FROM reminder_logs WHERE reminder_id = :rid AND sent_to = :to AND event_date LIKE :year LIMIT 1');
            $stmt->execute([':rid' => $reminderId, ':to' => $notify, ':year' => $year . '-%']);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT id FROM reminder_logs WHERE reminder_id = :rid AND sent_to = :to LIMIT 1');
        $stmt->execute([':rid' => $reminderId, ':to' => $notify]);
        return (bool)$stmt->fetchColumn();
    }

    private static function formatRangeForEmail(string $eventDate, ?string $endDate): string
    {
        try {
            $start = new DateTime($eventDate);
            if (!empty($endDate)) {
                $end = new DateTime((string)$endDate);
                return $start->format('d/m') . ' — ' . $end->format('d/m');
            }
            return $start->format('d/m');
        } catch (\Throwable) {
            return $eventDate;
        }
    }

    private static function reminderLogsHasTitleColumn(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM reminder_logs LIKE 'title'");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return (bool)$row;
        } catch (\Throwable) {
            return false;
        }
    }
}
