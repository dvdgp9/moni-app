<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class RemindersRepository
{
    private static function currentUserId(): int
    {
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }
        return $userId;
    }

    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, title, event_date, end_date, recurring, links, mandatory, enabled, user_id, created_at
            FROM reminders
            WHERE user_id = :user_id
            ORDER BY event_date ASC, title ASC');
        $stmt->execute([':user_id' => self::currentUserId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $title, string $eventDate, string $recurring = 'yearly', ?int $userId = null, bool $enabled = true, ?string $endDate = null, ?string $links = null, bool $mandatory = false): int
    {
        $pdo = Database::pdo();
        $effectiveUserId = $userId ?? self::currentUserId();
        $stmt = $pdo->prepare('INSERT INTO reminders (title, event_date, end_date, recurring, links, mandatory, enabled, user_id) VALUES (:title, :event_date, :end_date, :recurring, :links, :mandatory, :enabled, :user_id)');
        $stmt->execute([
            ':title' => $title,
            ':event_date' => $eventDate,
            ':end_date' => $endDate ?: null,
            ':recurring' => in_array($recurring, ['none','yearly'], true) ? $recurring : 'yearly',
            ':links' => $links ?: null,
            ':mandatory' => $mandatory ? 1 : 0,
            ':enabled' => $enabled ? 1 : 0,
            ':user_id' => $effectiveUserId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM reminders WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
    }

    public static function setEnabled(int $id, bool $enabled): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE reminders SET enabled = :e WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':e' => $enabled ? 1 : 0, ':id' => $id, ':user_id' => self::currentUserId()]);
    }

    /**
     * Bulk enable/disable by IDs
     */
    public static function setEnabledMany(array $ids, bool $enabled): void
    {
        if (empty($ids)) return;
        $pdo = Database::pdo();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE reminders SET enabled = ? WHERE user_id = ? AND id IN ($in)");
        $params = [$enabled ? 1 : 0, self::currentUserId()];
        foreach ($ids as $id) { $params[] = (int)$id; }
        $stmt->execute($params);
    }
}
