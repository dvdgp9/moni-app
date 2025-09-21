<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class UsersRepository
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function updateProfile(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE users SET name=:name, company_name=:company_name, nif=:nif, address=:address, phone=:phone,
            billing_email=:billing_email, iban=:iban, logo_url=:logo_url, color_primary=:color_primary, color_accent=:color_accent
            WHERE id=:id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'] ?? '',
            ':company_name' => $data['company_name'] ?? null,
            ':nif' => $data['nif'] ?? null,
            ':address' => $data['address'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':billing_email' => $data['billing_email'] ?? null,
            ':iban' => $data['iban'] ?? null,
            ':logo_url' => $data['logo_url'] ?? null,
            ':color_primary' => $data['color_primary'] ?? null,
            ':color_accent' => $data['color_accent'] ?? null,
        ]);
    }

    public static function existsByEmail(string $email): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(string $email, string $password, ?string $name = null): int
    {
        $pdo = Database::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (:email, :hash, :name, :ts)');
        $stmt->execute([
            ':email' => $email,
            ':hash' => $hash,
            ':name' => $name,
            ':ts' => date('Y-m-d H:i:s'),
        ]);
        return (int)$pdo->lastInsertId();
    }
}
