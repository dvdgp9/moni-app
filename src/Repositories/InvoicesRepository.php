<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class InvoicesRepository
{
    public static function all(
        ?string $q = null,
        array $years = [],
        array $quarters = [],
        string $sortBy = 'issue_date',
        string $sortDir = 'desc'
    ): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT
                    i.id,
                    i.invoice_number,
                    i.client_id,
                    i.status,
                    i.issue_date,
                    i.due_date,
                    i.created_at,
                    c.name AS client_name,
                    COALESCE(SUM((it.quantity * it.unit_price) + ((it.quantity * it.unit_price) * (it.vat_rate / 100)) - ((it.quantity * it.unit_price) * (it.irpf_rate / 100))), 0) AS total_amount
                FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                LEFT JOIN invoice_items it ON it.invoice_id = i.id';
        $conds = [];
        $params = [];
        if ($q !== null && trim($q) !== '') {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], trim($q)) . '%';
            $conds[] = '(i.invoice_number LIKE :k OR c.name LIKE :k)';
            $params[':k'] = $like;
        }
        if (!empty($years)) {
            $safeYears = array_values(array_unique(array_filter(array_map('intval', $years), static fn(int $y): bool => $y >= 1900 && $y <= 2100)));
            if (!empty($safeYears)) {
                $yearParts = [];
                foreach ($safeYears as $idx => $year) {
                    $param = ':y' . $idx;
                    $yearParts[] = $param;
                    $params[$param] = $year;
                }
                $conds[] = 'YEAR(i.issue_date) IN (' . implode(',', $yearParts) . ')';
            }
        }
        if (!empty($quarters)) {
            $safeQuarters = array_values(array_unique(array_filter(array_map('intval', $quarters), static fn(int $qv): bool => $qv >= 1 && $qv <= 4)));
            if (!empty($safeQuarters)) {
                $quarterParts = [];
                foreach ($safeQuarters as $idx => $quarter) {
                    $param = ':q' . $idx;
                    $quarterParts[] = $param;
                    $params[$param] = $quarter;
                }
                $conds[] = 'QUARTER(i.issue_date) IN (' . implode(',', $quarterParts) . ')';
            }
        }
        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $sql .= ' GROUP BY i.id, i.invoice_number, i.client_id, i.status, i.issue_date, i.due_date, i.created_at, c.name';

        $sortMap = [
            'invoice_number' => 'i.invoice_number',
            'status' => 'i.status',
            'issue_date' => 'i.issue_date',
            'due_date' => 'i.due_date',
            'amount' => 'total_amount',
            'client_name' => 'c.name',
        ];
        $sortBySql = $sortMap[$sortBy] ?? 'i.issue_date';
        $sortDirSql = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        if ($sortBy === 'due_date') {
            $sql .= ' ORDER BY i.due_date IS NULL ASC, ' . $sortBySql . ' ' . $sortDirSql . ', i.id DESC';
        } elseif ($sortBy === 'client_name') {
            $sql .= ' ORDER BY c.name IS NULL ASC, ' . $sortBySql . ' ' . $sortDirSql . ', i.issue_date DESC';
        } else {
            $sql .= ' ORDER BY ' . $sortBySql . ' ' . $sortDirSql . ', i.id DESC';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function issueYearRange(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT MIN(YEAR(issue_date)) AS min_y, MAX(YEAR(issue_date)) AS max_y FROM invoices');
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $min = isset($row['min_y']) ? (int)$row['min_y'] : 0;
        $max = isset($row['max_y']) ? (int)$row['max_y'] : 0;
        if ($min <= 0 || $max <= 0 || $min > $max) {
            $y = (int)date('Y');
            return [$y];
        }
        return range($min, $max);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, invoice_number, client_id, status, issue_date, due_date, notes FROM invoices WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO invoices (invoice_number, client_id, status, issue_date, due_date, notes) VALUES (:invoice_number, :client_id, :status, :issue_date, :due_date, :notes)');
        $stmt->execute([
            ':invoice_number' => $data['invoice_number'],
            ':client_id' => (int)$data['client_id'],
            ':status' => $data['status'],
            ':issue_date' => $data['issue_date'],
            ':due_date' => $data['due_date'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function createDraft(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO invoices (invoice_number, client_id, status, issue_date, due_date, notes) VALUES ("", :client_id, "draft", :issue_date, :due_date, :notes)');
        $stmt->execute([
            ':client_id' => (int)$data['client_id'],
            ':issue_date' => $data['issue_date'],
            ':due_date' => $data['due_date'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateDraft(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE invoices
            SET client_id = :client_id, issue_date = :issue_date, due_date = :due_date, notes = :notes
            WHERE id = :id AND status = "draft"');
        $stmt->execute([
            ':id' => $id,
            ':client_id' => (int)$data['client_id'],
            ':issue_date' => $data['issue_date'],
            ':due_date' => $data['due_date'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
    }

    public static function setNumberAndStatusIssued(int $id, string $number): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE invoices SET invoice_number = :num, status = "issued" WHERE id = :id AND status = "draft"');
        $stmt->execute([':id' => $id, ':num' => $number]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE invoices SET status = :status WHERE id = :id');
        $stmt->execute([':id' => $id, ':status' => $status]);
    }

    /**
     * Count invoices linked to a given client id.
     */
    public static function countByClient(int $clientId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM invoices WHERE client_id = :cid');
        $stmt->execute([':cid' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }
}
