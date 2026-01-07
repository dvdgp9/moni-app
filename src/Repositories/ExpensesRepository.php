<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class ExpensesRepository
{
    public static function all(?int $year = null, ?string $category = null): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        if ($year !== null) {
            $sql .= ' AND YEAR(invoice_date) = :year';
            $params[':year'] = $year;
        }

        if ($category !== null && $category !== '') {
            $sql .= ' AND category = :category';
            $params[':category'] = $category;
        }

        $sql .= ' ORDER BY invoice_date DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO expenses 
            (supplier_name, supplier_nif, invoice_number, invoice_date, base_amount, vat_rate, vat_amount, total_amount, category, pdf_path, notes, status)
            VALUES 
            (:supplier_name, :supplier_nif, :invoice_number, :invoice_date, :base_amount, :vat_rate, :vat_amount, :total_amount, :category, :pdf_path, :notes, :status)
        ');
        $stmt->execute([
            ':supplier_name' => $data['supplier_name'],
            ':supplier_nif' => $data['supplier_nif'] ?? null,
            ':invoice_number' => $data['invoice_number'] ?? null,
            ':invoice_date' => $data['invoice_date'],
            ':base_amount' => (float)($data['base_amount'] ?? 0),
            ':vat_rate' => (float)($data['vat_rate'] ?? 21),
            ':vat_amount' => (float)($data['vat_amount'] ?? 0),
            ':total_amount' => (float)($data['total_amount'] ?? 0),
            ':category' => $data['category'] ?? 'otros',
            ':pdf_path' => $data['pdf_path'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':status' => $data['status'] ?? 'pending',
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            UPDATE expenses SET
                supplier_name = :supplier_name,
                supplier_nif = :supplier_nif,
                invoice_number = :invoice_number,
                invoice_date = :invoice_date,
                base_amount = :base_amount,
                vat_rate = :vat_rate,
                vat_amount = :vat_amount,
                total_amount = :total_amount,
                category = :category,
                notes = :notes,
                status = :status
            WHERE id = :id
        ');
        return $stmt->execute([
            ':id' => $id,
            ':supplier_name' => $data['supplier_name'],
            ':supplier_nif' => $data['supplier_nif'] ?? null,
            ':invoice_number' => $data['invoice_number'] ?? null,
            ':invoice_date' => $data['invoice_date'],
            ':base_amount' => (float)($data['base_amount'] ?? 0),
            ':vat_rate' => (float)($data['vat_rate'] ?? 21),
            ':vat_amount' => (float)($data['vat_amount'] ?? 0),
            ':total_amount' => (float)($data['total_amount'] ?? 0),
            ':category' => $data['category'] ?? 'otros',
            ':notes' => $data['notes'] ?? null,
            ':status' => $data['status'] ?? 'pending',
        ]);
    }

    public static function delete(int $id): bool
    {
        // First get the record to delete the PDF file
        $expense = self::find($id);
        if ($expense && !empty($expense['pdf_path'])) {
            $fullPath = dirname(__DIR__, 2) . '/' . $expense['pdf_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public static function validate(int $id): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE expenses SET status = :status WHERE id = :id');
        return $stmt->execute([':id' => $id, ':status' => 'validated']);
    }

    /**
     * Get summary of expenses for a date range.
     */
    public static function summarize(string $startDate, string $endDate): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT 
                SUM(base_amount) as base_total,
                SUM(vat_amount) as vat_total,
                SUM(total_amount) as total_amount,
                COUNT(*) as count
            FROM expenses 
            WHERE invoice_date >= :start AND invoice_date <= :end
        ');
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'base_total' => round((float)($row['base_total'] ?? 0), 2),
            'vat_total' => round((float)($row['vat_total'] ?? 0), 2),
            'total_amount' => round((float)($row['total_amount'] ?? 0), 2),
            'count' => (int)($row['count'] ?? 0),
        ];
    }

    /**
     * Get VAT breakdown by rate for a date range.
     */
    public static function summarizeByVat(string $startDate, string $endDate): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT 
                vat_rate,
                SUM(base_amount) as base,
                SUM(vat_amount) as vat
            FROM expenses 
            WHERE invoice_date >= :start AND invoice_date <= :end
            GROUP BY vat_rate
            ORDER BY vat_rate DESC
        ');
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rate = number_format((float)$row['vat_rate'], 2, '.', '');
            $result[$rate] = [
                'base' => round((float)$row['base'], 2),
                'vat' => round((float)$row['vat'], 2),
            ];
        }
        return $result;
    }

    /**
     * Get years that have expenses.
     */
    public static function getYears(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT DISTINCT YEAR(invoice_date) as y FROM expenses ORDER BY y DESC');
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'y');
    }

    /**
     * Get all categories used.
     */
    public static function getCategories(): array
    {
        return [
            'suministros' => 'Suministros (luz, agua, gas, internet)',
            'material' => 'Material de oficina',
            'servicios' => 'Servicios profesionales',
            'transporte' => 'Transporte y desplazamientos',
            'software' => 'Software y suscripciones',
            'profesionales' => 'Servicios profesionales (gestoría, etc.)',
            'otros' => 'Otros gastos',
        ];
    }
}
