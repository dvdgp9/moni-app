<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class SuppliersRepository
{
    private static function currentUserId(): int
    {
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }
        return $userId;
    }

    public static function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return trim($name);
    }

    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, nif, default_category, default_vat_rate, notes, last_used_at, created_at
            FROM suppliers
            WHERE user_id = :user_id
            ORDER BY COALESCE(last_used_at, created_at) DESC, name ASC
        ');
        $stmt->execute([':user_id' => self::currentUserId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function recent(int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("
            SELECT id, name, nif, default_category, default_vat_rate, notes, last_used_at, created_at
            FROM suppliers
            WHERE user_id = :user_id
            ORDER BY COALESCE(last_used_at, created_at) DESC, name ASC
            LIMIT {$limit}
        ");
        $stmt->execute([':user_id' => self::currentUserId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, nif, normalized_name, default_category, default_vat_rate, notes, last_used_at, created_at
            FROM suppliers
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findMatch(?string $name, ?string $nif): ?array
    {
        $pdo = Database::pdo();
        $userId = self::currentUserId();
        $cleanNif = strtoupper(trim((string)$nif));
        if ($cleanNif !== '') {
            $stmt = $pdo->prepare('
                SELECT id, name, nif, normalized_name, default_category, default_vat_rate, notes, last_used_at, created_at
                FROM suppliers
                WHERE user_id = :user_id AND nif = :nif
                ORDER BY COALESCE(last_used_at, created_at) DESC
                LIMIT 1
            ');
            $stmt->execute([':user_id' => $userId, ':nif' => $cleanNif]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $normalized = self::normalizeName((string)$name);
        if ($normalized === '') {
            return null;
        }

        $stmt = $pdo->prepare('
            SELECT id, name, nif, normalized_name, default_category, default_vat_rate, notes, last_used_at, created_at
            FROM suppliers
            WHERE user_id = :user_id AND normalized_name = :normalized
            ORDER BY COALESCE(last_used_at, created_at) DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId, ':normalized' => $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function ensureFromExpense(array $expense, ?int $selectedSupplierId, bool $syncDetails = true): ?int
    {
        $supplierName = trim((string)($expense['supplier_name'] ?? ''));
        $supplierNif = strtoupper(trim((string)($expense['supplier_nif'] ?? '')));
        $category = (string)($expense['category'] ?? 'otros');
        $vatRate = (float)($expense['vat_rate'] ?? 21);
        $now = date('Y-m-d H:i:s');

        if ($supplierName === '') {
            return null;
        }

        $pdo = Database::pdo();
        $supplier = null;

        if (($selectedSupplierId ?? 0) > 0) {
            $supplier = self::find((int)$selectedSupplierId);
            if ($supplier === null) {
                throw new \RuntimeException('Proveedor no encontrado o sin acceso.');
            }
        } else {
            $supplier = self::findMatch($supplierName, $supplierNif);
        }

        if ($supplier) {
            $stmt = $pdo->prepare('
                UPDATE suppliers
                SET
                    name = :name,
                    nif = :nif,
                    normalized_name = :normalized_name,
                    default_category = :default_category,
                    default_vat_rate = :default_vat_rate,
                    last_used_at = :last_used_at
                WHERE id = :id AND user_id = :user_id
            ');
            $stmt->execute([
                ':id' => (int)$supplier['id'],
                ':user_id' => self::currentUserId(),
                ':name' => $syncDetails ? $supplierName : (string)$supplier['name'],
                ':nif' => $syncDetails ? ($supplierNif !== '' ? $supplierNif : null) : ($supplier['nif'] ?: null),
                ':normalized_name' => $syncDetails ? self::normalizeName($supplierName) : (string)$supplier['normalized_name'],
                ':default_category' => $syncDetails ? $category : (string)$supplier['default_category'],
                ':default_vat_rate' => $syncDetails ? $vatRate : (float)$supplier['default_vat_rate'],
                ':last_used_at' => $now,
            ]);
            return (int)$supplier['id'];
        }

        if (!$syncDetails) {
            return null;
        }

        $stmt = $pdo->prepare('
            INSERT INTO suppliers
            (user_id, name, nif, normalized_name, default_category, default_vat_rate, notes, last_used_at)
            VALUES
            (:user_id, :name, :nif, :normalized_name, :default_category, :default_vat_rate, :notes, :last_used_at)
        ');
        $stmt->execute([
            ':user_id' => self::currentUserId(),
            ':name' => $supplierName,
            ':nif' => $supplierNif !== '' ? $supplierNif : null,
            ':normalized_name' => self::normalizeName($supplierName),
            ':default_category' => $category,
            ':default_vat_rate' => $vatRate,
            ':notes' => null,
            ':last_used_at' => $now,
        ]);

        return (int)$pdo->lastInsertId();
    }
}
