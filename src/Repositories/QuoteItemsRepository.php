<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class QuoteItemsRepository
{
    private static function currentUserId(): int
    {
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }
        return $userId;
    }

    public static function byQuote(int $quoteId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT qi.id, qi.description, qi.quantity, qi.unit_price, qi.vat_rate, qi.irpf_rate
            FROM quote_items qi
            INNER JOIN quotes q ON q.id = qi.quote_id
            WHERE qi.quote_id = :id AND q.user_id = :user_id
            ORDER BY qi.id ASC');
        $stmt->execute([':id' => $quoteId, ':user_id' => self::currentUserId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get items by quote ID without requiring authentication (for public view).
     */
    public static function byQuotePublic(int $quoteId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT qi.id, qi.description, qi.quantity, qi.unit_price, qi.vat_rate, qi.irpf_rate
            FROM quote_items qi
            WHERE qi.quote_id = :id
            ORDER BY qi.id ASC');
        $stmt->execute([':id' => $quoteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteByQuote(int $quoteId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE qi FROM quote_items qi
            INNER JOIN quotes q ON q.id = qi.quote_id
            WHERE qi.quote_id = :id AND q.user_id = :user_id');
        $stmt->execute([':id' => $quoteId, ':user_id' => self::currentUserId()]);
    }

    public static function insertMany(int $quoteId, array $items): void
    {
        if (empty($items)) return;
        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT 1 FROM quotes WHERE id = :id AND user_id = :user_id LIMIT 1');
        $check->execute([':id' => $quoteId, ':user_id' => self::currentUserId()]);
        if (!$check->fetchColumn()) {
            throw new \RuntimeException('Presupuesto no accesible');
        }
        $stmt = $pdo->prepare('INSERT INTO quote_items (quote_id, description, quantity, unit_price, vat_rate, irpf_rate)
            VALUES (:quote_id, :description, :quantity, :unit_price, :vat_rate, :irpf_rate)');
        foreach ($items as $it) {
            $stmt->execute([
                ':quote_id' => $quoteId,
                ':description' => (string)$it['description'],
                ':quantity' => (float)$it['quantity'],
                ':unit_price' => (float)$it['unit_price'],
                ':vat_rate' => (float)$it['vat_rate'],
                ':irpf_rate' => (float)$it['irpf_rate'],
            ]);
        }
    }
}
