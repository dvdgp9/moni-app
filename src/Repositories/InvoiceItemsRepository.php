<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class InvoiceItemsRepository
{
    public static function byInvoice(int $invoiceId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, description, quantity, unit_price, vat_rate, irpf_rate FROM invoice_items WHERE invoice_id = :id ORDER BY id ASC');
        $stmt->execute([':id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteByInvoice(int $invoiceId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = :id');
        $stmt->execute([':id' => $invoiceId]);
    }

    public static function insertMany(int $invoiceId, array $items): void
    {
        if (empty($items)) return;
        $pdo = Database::pdo();
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
