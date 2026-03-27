<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Database;
use Moni\Repositories\QuotesRepository;
use PDO;
use Throwable;

final class QuoteNumberingService
{
    /**
     * Assign a number in format P-YYYY-NNNN (zero-padded) with yearly reset, atomically.
     * Returns the assigned number (string).
     */
    public static function issue(int $quoteId, string $issueDate): string
    {
        $pdo = Database::pdo();
        $year = (int)substr($issueDate, 0, 4);
        $userId = AuthService::userId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario no autenticado');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT last_number FROM quote_sequences WHERE user_id = :user_id AND seq_year = :y FOR UPDATE');
            $stmt->execute([':user_id' => $userId, ':y' => $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $next = (int)$row['last_number'] + 1;
                $upd = $pdo->prepare('UPDATE quote_sequences SET last_number = :n WHERE user_id = :user_id AND seq_year = :y');
                $upd->execute([':n' => $next, ':user_id' => $userId, ':y' => $year]);
            } else {
                $next = 1;
                $ins = $pdo->prepare('INSERT INTO quote_sequences (user_id, seq_year, last_number) VALUES (:user_id, :y, :n)');
                $ins->execute([':user_id' => $userId, ':y' => $year, ':n' => $next]);
            }

            $number = sprintf('P-%04d-%04d', $year, $next);
            QuotesRepository::setNumberAndStatusSent($quoteId, $number);

            $pdo->commit();
            return $number;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
