<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class InvoiceItemsRepository
{
    private static function currentUserId(): int
    {
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }
        return $userId;
    }

    public static function byInvoice(int $invoiceId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT it.id, it.description, it.quantity, it.unit_price, it.vat_rate, it.irpf_rate
            FROM invoice_items it
            INNER JOIN invoices i ON i.id = it.invoice_id
            WHERE it.invoice_id = :id AND i.user_id = :user_id
            ORDER BY it.id ASC');
        $stmt->execute([':id' => $invoiceId, ':user_id' => self::currentUserId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteByInvoice(int $invoiceId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE it FROM invoice_items it
            INNER JOIN invoices i ON i.id = it.invoice_id
            WHERE it.invoice_id = :id AND i.user_id = :user_id');
        $stmt->execute([':id' => $invoiceId, ':user_id' => self::currentUserId()]);
    }

    public static function insertMany(int $invoiceId, array $items): void
    {
        if (empty($items)) return;
        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT 1 FROM invoices WHERE id = :id AND user_id = :user_id LIMIT 1');
        $check->execute([':id' => $invoiceId, ':user_id' => self::currentUserId()]);
        if (!$check->fetchColumn()) {
            throw new \RuntimeException('Factura no accesible');
        }
        $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, vat_rate, irpf_rate)
            VALUES (:invoice_id, :description, :quantity, :unit_price, :vat_rate, :irpf_rate)');
        foreach ($items as $it) {
            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':description' => (string)$it['description'],
                ':quantity' => (float)$it['quantity'],
                ':unit_price' => (float)$it['unit_price'],
                ':vat_rate' => (float)$it['vat_rate'],
                ':irpf_rate' => (float)$it['irpf_rate'],
            ]);
        }
    }
}
