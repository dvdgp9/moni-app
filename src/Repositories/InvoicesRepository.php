<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class InvoicesRepository
{
    public static function all(?string $q = null, ?string $status = null): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT i.id, i.invoice_number, i.client_id, i.status, i.issue_date, i.due_date, i.created_at, c.name AS client_name
                FROM invoices i LEFT JOIN clients c ON c.id = i.client_id';
        $conds = [];
        $params = [];
        if ($status !== null && in_array($status, ['draft','issued','paid','cancelled'], true)) {
            $conds[] = 'i.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== null && trim($q) !== '') {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], trim($q)) . '%';
            $conds[] = '(i.invoice_number LIKE :k OR c.name LIKE :k)';
            $params[':k'] = $like;
        }
        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY i.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
