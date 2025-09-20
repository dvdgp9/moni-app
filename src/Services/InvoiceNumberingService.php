<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Database;
use Moni\Repositories\InvoicesRepository;
use PDO;
use Throwable;

final class InvoiceNumberingService
{
    /**
     * Assign a number in format YYYY-NNNN (zero-padded) with yearly reset, atomically.
     * Returns the assigned number (string).
     */
    public static function issue(int $invoiceId, string $issueDate): string
    {
        $pdo = Database::pdo();
        $year = (int)substr($issueDate, 0, 4);

        try {
            $pdo->beginTransaction();

            // Lock row for this year
            $stmt = $pdo->prepare('SELECT last_number FROM invoice_sequences WHERE seq_year = :y FOR UPDATE');
            $stmt->execute([':y' => $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $next = (int)$row['last_number'] + 1;
                $upd = $pdo->prepare('UPDATE invoice_sequences SET last_number = :n WHERE seq_year = :y');
                $upd->execute([':n' => $next, ':y' => $year]);
            } else {
                $next = 1;
                $ins = $pdo->prepare('INSERT INTO invoice_sequences (seq_year, last_number) VALUES (:y, :n)');
                $ins->execute([':y' => $year, ':n' => $next]);
            }

            $number = sprintf('%04d-%04d', $year, $next);
            InvoicesRepository::setNumberAndStatusIssued($invoiceId, $number);

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
