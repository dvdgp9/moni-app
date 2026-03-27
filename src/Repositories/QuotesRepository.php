<?php
declare(strict_types=1);

namespace Moni\Repositories;

use Moni\Database;
use Moni\Services\AuthService;
use PDO;

final class QuotesRepository
{
    private static function currentUserId(): int
    {
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }
        return $userId;
    }

    private static function assertOwnedClientId(int $clientId): void
    {
        if (ClientsRepository::find($clientId) === null) {
            throw new \RuntimeException('Cliente no encontrado');
        }
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function all(
        ?string $q = null,
        array $statuses = [],
        string $sortBy = 'issue_date',
        string $sortDir = 'desc'
    ): array {
        $pdo = Database::pdo();
        $userId = self::currentUserId();
        $sql = 'SELECT
                    q.id,
                    q.quote_number,
                    q.client_id,
                    q.status,
                    q.token,
                    q.issue_date,
                    q.valid_until,
                    q.converted_invoice_id,
                    q.created_at,
                    c.name AS client_name,
                    COALESCE(SUM((qi.quantity * qi.unit_price) + ((qi.quantity * qi.unit_price) * (qi.vat_rate / 100)) - ((qi.quantity * qi.unit_price) * (qi.irpf_rate / 100))), 0) AS total_amount
                FROM quotes q
                LEFT JOIN clients c ON c.id = q.client_id AND c.user_id = q.user_id
                LEFT JOIN quote_items qi ON qi.quote_id = q.id';
        $conds = ['q.user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($q !== null && trim($q) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($q)) . '%';
            $conds[] = '(q.quote_number LIKE :k OR c.name LIKE :k)';
            $params[':k'] = $like;
        }

        if (!empty($statuses)) {
            $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];
            $safe = array_values(array_intersect($statuses, $allowed));
            if (!empty($safe)) {
                $parts = [];
                foreach ($safe as $idx => $s) {
                    $p = ':st' . $idx;
                    $parts[] = $p;
                    $params[$p] = $s;
                }
                $conds[] = 'q.status IN (' . implode(',', $parts) . ')';
            }
        }

        $sql .= ' WHERE ' . implode(' AND ', $conds);
        $sql .= ' GROUP BY q.id, q.quote_number, q.client_id, q.status, q.token, q.issue_date, q.valid_until, q.converted_invoice_id, q.created_at, c.name';

        $sortMap = [
            'quote_number' => 'q.quote_number',
            'status' => 'q.status',
            'issue_date' => 'q.issue_date',
            'valid_until' => 'q.valid_until',
            'amount' => 'total_amount',
            'client_name' => 'c.name',
        ];
        $sortBySql = $sortMap[$sortBy] ?? 'q.issue_date';
        $sortDirSql = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY ' . $sortBySql . ' ' . $sortDirSql . ', q.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, quote_number, client_id, status, token, issue_date, valid_until, notes,
                accepted_at, rejected_at, rejection_reason, converted_invoice_id
            FROM quotes
            WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find a quote by its public token. No authentication required.
     */
    public static function findByToken(string $token): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT q.*, c.name AS client_name, c.nif AS client_nif,
                c.email AS client_email, c.phone AS client_phone, c.address AS client_address
            FROM quotes q
            LEFT JOIN clients c ON c.id = q.client_id AND c.user_id = q.user_id
            WHERE q.token = :token');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        self::assertOwnedClientId((int)$data['client_id']);
        $stmt = $pdo->prepare('INSERT INTO quotes (user_id, quote_number, client_id, status, token, issue_date, valid_until, notes)
            VALUES (:user_id, :quote_number, :client_id, :status, :token, :issue_date, :valid_until, :notes)');
        $stmt->execute([
            ':user_id' => self::currentUserId(),
            ':quote_number' => $data['quote_number'] ?? null,
            ':client_id' => (int)$data['client_id'],
            ':status' => $data['status'] ?? 'draft',
            ':token' => $data['token'] ?? self::generateToken(),
            ':issue_date' => $data['issue_date'],
            ':valid_until' => $data['valid_until'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        self::assertOwnedClientId((int)$data['client_id']);
        $stmt = $pdo->prepare('UPDATE quotes
            SET client_id = :client_id, issue_date = :issue_date, valid_until = :valid_until, notes = :notes
            WHERE id = :id AND user_id = :user_id AND status IN ("draft","sent")');
        $stmt->execute([
            ':id' => $id,
            ':user_id' => self::currentUserId(),
            ':client_id' => (int)$data['client_id'],
            ':issue_date' => $data['issue_date'],
            ':valid_until' => $data['valid_until'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
    }

    public static function setNumberAndStatusSent(int $id, string $number): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes
            SET quote_number = :num, status = "sent"
            WHERE id = :id AND user_id = :user_id AND status = "draft"');
        $stmt->execute([':id' => $id, ':num' => $number, ':user_id' => self::currentUserId()]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes SET status = :status WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':status' => $status, ':user_id' => self::currentUserId()]);
    }

    /**
     * Accept a quote via public token (no auth needed).
     */
    public static function acceptByToken(string $token): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes SET status = "accepted", accepted_at = NOW()
            WHERE token = :token AND status = "sent"');
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reject a quote via public token (no auth needed).
     */
    public static function rejectByToken(string $token, ?string $reason = null): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes SET status = "rejected", rejected_at = NOW(), rejection_reason = :reason
            WHERE token = :token AND status = "sent"');
        $stmt->execute([':token' => $token, ':reason' => $reason]);
        return $stmt->rowCount() > 0;
    }

    public static function markConverted(int $id, int $invoiceId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes SET status = "converted", converted_invoice_id = :inv_id
            WHERE id = :id AND user_id = :user_id AND status = "accepted"');
        $stmt->execute([':id' => $id, ':inv_id' => $invoiceId, ':user_id' => self::currentUserId()]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM quotes WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => self::currentUserId()]);
    }

    public static function countByClient(int $clientId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM quotes WHERE client_id = :cid AND user_id = :user_id');
        $stmt->execute([':cid' => $clientId, ':user_id' => self::currentUserId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }
}
