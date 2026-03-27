<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class SettingsRepository
{
    private static function effectiveUserId(?int $userId): ?int
    {
        return $userId ?? AuthService::userId();
    }

    public static function get(string $key, ?int $userId = null): ?string
    {
        $pdo = Database::pdo();
        $userId = self::effectiveUserId($userId);
        // Prefer latest row if duplicates exist (historical rows with NULL user_id may exist)
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k AND (user_id <=> :u) ORDER BY id DESC LIMIT 1');
        $stmt->execute([':k' => $key, ':u' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['setting_value'] ?? null;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $pdo = Database::pdo();
        $userId = self::effectiveUserId($userId);
        // Robust upsert: avoid duplicate insert when UPDATE finds row but value is unchanged.
        $stmt = $pdo->prepare('SELECT id FROM settings WHERE setting_key = :k AND (user_id <=> :u) ORDER BY id DESC LIMIT 1');
        $stmt->execute([':k' => $key, ':u' => $userId]);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            $upd = $pdo->prepare('UPDATE settings SET setting_value = :v WHERE id = :id');
            $upd->execute([':v' => $value, ':id' => (int)$existingId]);
            return;
        }
        $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, user_id) VALUES (:k, :v, :u)');
        $ins->execute([':k' => $key, ':v' => $value, ':u' => $userId]);
    }

    public static function all(?int $userId = null): array
    {
        $pdo = Database::pdo();
        $userId = self::effectiveUserId($userId);
        // If duplicates exist, prefer the latest by id
        $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE (user_id <=> :u) ORDER BY id ASC');
        $stmt->execute([':u' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['setting_key']] = $row['setting_value']; // later rows (newer ids) overwrite older
        }
        return $out;
    }
}
