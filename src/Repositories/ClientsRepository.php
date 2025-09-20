<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class ClientsRepository
{
    public static function all(?string $q = null): array
    {
        $pdo = Database::pdo();
        if ($q !== null && trim($q) !== '') {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], trim($q)) . '%';
            $stmt = $pdo->prepare('SELECT id, name, nif, email, phone, address, default_vat, default_irpf, created_at
                FROM clients
                WHERE name LIKE :k OR nif LIKE :k OR email LIKE :k OR phone LIKE :k
                ORDER BY created_at DESC');
            $stmt->execute([':k' => $like]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $pdo->query('SELECT id, name, nif, email, phone, address, default_vat, default_irpf, created_at FROM clients ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, name, nif, email, phone, address, default_vat, default_irpf FROM clients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO clients (name, nif, email, phone, address, default_vat, default_irpf) VALUES (:name, :nif, :email, :phone, :address, :vat, :irpf)');
        $stmt->execute([
            ':name' => $data['name'],
            ':nif' => $data['nif'] ?? null,
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':address' => $data['address'] ?? null,
            ':vat' => (float)($data['default_vat'] ?? 21),
            ':irpf' => (float)($data['default_irpf'] ?? 15),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE clients SET name = :name, nif = :nif, email = :email, phone = :phone, address = :address, default_vat = :vat, default_irpf = :irpf WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':nif' => $data['nif'] ?? null,
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':address' => $data['address'] ?? null,
            ':vat' => (float)($data['default_vat'] ?? 21),
            ':irpf' => (float)($data['default_irpf'] ?? 15),
        ]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
