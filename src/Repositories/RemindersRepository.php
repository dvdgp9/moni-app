<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class RemindersRepository
{
    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id, title, event_date, end_date, recurring, links, enabled, user_id, created_at FROM reminders ORDER BY event_date ASC, title ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $title, string $eventDate, string $recurring = 'yearly', ?int $userId = null, bool $enabled = true): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO reminders (title, event_date, recurring, enabled, user_id) VALUES (:title, :event_date, :recurring, :enabled, :user_id)');
        $stmt->execute([
            ':title' => $title,
            ':event_date' => $eventDate,
            ':recurring' => in_array($recurring, ['none','yearly'], true) ? $recurring : 'yearly',
            ':enabled' => $enabled ? 1 : 0,
            ':user_id' => $userId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM reminders WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function setEnabled(int $id, bool $enabled): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE reminders SET enabled = :e WHERE id = :id');
        $stmt->execute([':e' => $enabled ? 1 : 0, ':id' => $id]);
    }

    /**
     * Bulk enable/disable by IDs
     */
    public static function setEnabledMany(array $ids, bool $enabled): void
    {
        if (empty($ids)) return;
        $pdo = Database::pdo();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE reminders SET enabled = ? WHERE id IN ($in)");
        $params = [$enabled ? 1 : 0];
        foreach ($ids as $id) { $params[] = (int)$id; }
        $stmt->execute($params);
    }
}
