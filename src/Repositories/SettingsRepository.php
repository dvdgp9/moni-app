<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class SettingsRepository
{
    public static function get(string $key, ?int $userId = null): ?string
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k AND (user_id <=> :u) LIMIT 1');
        $stmt->execute([':k' => $key, ':u' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['setting_value'] ?? null;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, user_id)
            VALUES (:k, :v, :u)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([':k' => $key, ':v' => $value, ':u' => $userId]);
    }

    public static function all(?int $userId = null): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE (user_id <=> :u)');
        $stmt->execute([':u' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }
}
