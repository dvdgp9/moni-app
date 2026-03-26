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

    public static function all(?string $q = null): array
    {
        $pdo = Database::pdo();
        $sql = '
            SELECT
                s.id,
                s.name,
                s.nif,
                s.default_category,
                s.default_vat_rate,
                s.notes,
                s.last_used_at,
                s.created_at,
                COUNT(e.id) AS expense_count,
                COALESCE(SUM(e.total_amount), 0) AS total_spend,
                MAX(e.invoice_date) AS last_expense_date
            FROM suppliers s
            LEFT JOIN expenses e ON e.supplier_id = s.id AND e.user_id = s.user_id
            WHERE s.user_id = :user_id
        ';
        $params = [':user_id' => self::currentUserId()];
        if ($q !== null && trim($q) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($q)) . '%';
            $sql .= ' AND (s.name LIKE :q OR s.nif LIKE :q)';
            $params[':q'] = $like;
        }
        $sql .= '
            GROUP BY s.id, s.name, s.nif, s.default_category, s.default_vat_rate, s.notes, s.last_used_at, s.created_at
            ORDER BY COALESCE(s.last_used_at, MAX(e.invoice_date), s.created_at) DESC, s.name ASC
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('El nombre del proveedor es obligatorio.');
        }
        $stmt = $pdo->prepare('
            INSERT INTO suppliers
            (user_id, name, nif, normalized_name, default_category, default_vat_rate, notes, last_used_at)
            VALUES
            (:user_id, :name, :nif, :normalized_name, :default_category, :default_vat_rate, :notes, :last_used_at)
        ');
        $stmt->execute([
            ':user_id' => self::currentUserId(),
            ':name' => $name,
            ':nif' => ($nif = strtoupper(trim((string)($data['nif'] ?? '')))) !== '' ? $nif : null,
            ':normalized_name' => self::normalizeName($name),
            ':default_category' => (string)($data['default_category'] ?? 'otros'),
            ':default_vat_rate' => (float)($data['default_vat_rate'] ?? 21),
            ':notes' => ($notes = trim((string)($data['notes'] ?? ''))) !== '' ? $notes : null,
            ':last_used_at' => null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('El nombre del proveedor es obligatorio.');
        }
        $stmt = $pdo->prepare('
            UPDATE suppliers
            SET
                name = :name,
                nif = :nif,
                normalized_name = :normalized_name,
                default_category = :default_category,
                default_vat_rate = :default_vat_rate,
                notes = :notes
            WHERE id = :id AND user_id = :user_id
        ');
        $stmt->execute([
            ':id' => $id,
            ':user_id' => self::currentUserId(),
            ':name' => $name,
            ':nif' => ($nif = strtoupper(trim((string)($data['nif'] ?? '')))) !== '' ? $nif : null,
            ':normalized_name' => self::normalizeName($name),
            ':default_category' => (string)($data['default_category'] ?? 'otros'),
            ':default_vat_rate' => (float)($data['default_vat_rate'] ?? 21),
            ':notes' => ($notes = trim((string)($data['notes'] ?? ''))) !== '' ? $notes : null,
        ]);
    }

    public static function countExpenses(int $id): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE supplier_id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
        return (int)$stmt->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
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
