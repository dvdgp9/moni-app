<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use PDO;

final class InvoicesRepository
{
    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT i.id, i.invoice_number, i.client_id, i.status, i.issue_date, i.due_date, i.created_at, c.name AS client_name
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            ORDER BY i.created_at DESC');
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
        $stmt = $pdo->prepare('UPDATE invoices SET client_id = :client_id, issue_date = :issue_date, due_date = :due_date, notes = :notes WHERE id = :id AND status = "draft"');
        $stmt->execute([
            ':id' => $id,
            ':client_id' => (int)$data['client_id'],
            ':issue_date' => $data['issue_date'],
            ':due_date' => $data['due_date'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
    }
}
