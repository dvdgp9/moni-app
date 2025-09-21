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
        $stmt = $pdo->query('SELECT id, title, event_date, recurring, user_id, created_at FROM reminders ORDER BY event_date ASC, title ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $title, string $eventDate, string $recurring = 'yearly', ?int $userId = null): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO reminders (title, event_date, recurring, user_id) VALUES (:title, :event_date, :recurring, :user_id)');
        $stmt->execute([
            ':title' => $title,
            ':event_date' => $eventDate,
            ':recurring' => in_array($recurring, ['none','yearly'], true) ? $recurring : 'yearly',
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
}
