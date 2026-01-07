<?php
declare(strict_types=1);

namespace Moni\Services;

use DateTime;
use Moni\Database;
use PDO;

final class TaxQuarterService
{
    public static function quarterRange(int $year, int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $ranges = [
            1 => ['start' => "$year-01-01", 'end' => "$year-03-31"],
            2 => ['start' => "$year-04-01", 'end' => "$year-06-30"],
            3 => ['start' => "$year-07-01", 'end' => "$year-09-30"],
            4 => ['start' => "$year-10-01", 'end' => "$year-12-31"],
        ];
        return $ranges[$quarter];
    }

    /**
     * Summarize sales for 303/130 using invoices in range and status issued/paid.
     * Returns:
     * - base_total: float (sum of lines base)
     * - iva_total: float (sum of lines base * vat_rate)
     * - irpf_total: float (sum of lines base * irpf_rate)
     * - by_vat: array<rate => [base, iva]>
     */
    public static function summarizeSales(int $year, int $quarter): array
    {
        $range = self::quarterRange($year, $quarter);
        $pdo = Database::pdo();
        // Join invoices and items, restricted by date and status
        $sql = 'SELECT it.quantity, it.unit_price, it.vat_rate, it.irpf_rate
                FROM invoice_items it
                INNER JOIN invoices i ON i.id = it.invoice_id
                WHERE i.status IN (\'issued\', \'paid\')
                  AND i.issue_date >= :start AND i.issue_date <= :end';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $range['start'], ':end' => $range['end']]);
        $base = 0.0; $iva = 0.0; $irpf = 0.0;
        $byVat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $qty = (float)($row['quantity'] ?? 0);
            $price = (float)($row['unit_price'] ?? 0);
            $rateVat = (float)($row['vat_rate'] ?? 0);
            $rateIrpf = (float)($row['irpf_rate'] ?? 0);
            $lineBase = $qty * $price;
            $lineIva = $lineBase * ($rateVat / 100.0);
            $lineIrpf = $lineBase * ($rateIrpf / 100.0);
            $base += $lineBase; $iva += $lineIva; $irpf += $lineIrpf;
            $key = number_format($rateVat, 2, '.', '');
            if (!isset($byVat[$key])) { $byVat[$key] = ['base' => 0.0, 'iva' => 0.0]; }
            $byVat[$key]['base'] += $lineBase;
            $byVat[$key]['iva'] += $lineIva;
        }
        // Round at the end to avoid drift
        foreach ($byVat as $k => $v) {
            $byVat[$k]['base'] = round($v['base'], 2);
            $byVat[$k]['iva'] = round($v['iva'], 2);
        }
        return [
            'base_total' => round($base, 2),
            'iva_total' => round($iva, 2),
            'irpf_total' => round($irpf, 2),
            'by_vat' => $byVat,
            'range' => $range,
        ];
    }

    /**
     * Cumulative summary from Jan 1 to the end of the selected quarter (YTD) for 130.
     * Returns base_total_ytd, iva_total_ytd, irpf_total_ytd and range_ytd.
     */
    public static function summarizeSalesYTD(int $year, int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $endMap = [
            1 => "$year-03-31",
            2 => "$year-06-30",
            3 => "$year-09-30",
            4 => "$year-12-31",
        ];
        $start = "$year-01-01";
        $end = $endMap[$quarter];
        $pdo = Database::pdo();
        $sql = 'SELECT it.quantity, it.unit_price, it.vat_rate, it.irpf_rate
                FROM invoice_items it
                INNER JOIN invoices i ON i.id = it.invoice_id
                WHERE i.status IN (\'issued\', \'paid\')
                  AND i.issue_date >= :start AND i.issue_date <= :end';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $base = 0.0; $iva = 0.0; $irpf = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $qty = (float)($row['quantity'] ?? 0);
            $price = (float)($row['unit_price'] ?? 0);
            $rateVat = (float)($row['vat_rate'] ?? 0);
            $rateIrpf = (float)($row['irpf_rate'] ?? 0);
            $lineBase = $qty * $price;
            $base += $lineBase;
            $iva += $lineBase * ($rateVat / 100.0);
            $irpf += $lineBase * ($rateIrpf / 100.0);
        }
        return [
            'base_total_ytd' => round($base, 2),
            'iva_total_ytd' => round($iva, 2),
            'irpf_total_ytd' => round($irpf, 2),
            'range_ytd' => ['start' => $start, 'end' => $end],
        ];
    }

    /**
     * Summarize expenses (purchases/costs) for a quarter.
     * Returns base_total, vat_total (deductible), by_vat breakdown.
     */
    public static function summarizeExpenses(int $year, int $quarter): array
    {
        $range = self::quarterRange($year, $quarter);
        $pdo = Database::pdo();
        
        $sql = 'SELECT base_amount, vat_rate, vat_amount
                FROM expenses
                WHERE invoice_date >= :start AND invoice_date <= :end';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $range['start'], ':end' => $range['end']]);
        
        $base = 0.0;
        $vat = 0.0;
        $byVat = [];
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lineBase = (float)($row['base_amount'] ?? 0);
            $lineVat = (float)($row['vat_amount'] ?? 0);
            $rateVat = (float)($row['vat_rate'] ?? 0);
            
            $base += $lineBase;
            $vat += $lineVat;
            
            $key = number_format($rateVat, 2, '.', '');
            if (!isset($byVat[$key])) {
                $byVat[$key] = ['base' => 0.0, 'vat' => 0.0];
            }
            $byVat[$key]['base'] += $lineBase;
            $byVat[$key]['vat'] += $lineVat;
        }
        
        foreach ($byVat as $k => $v) {
            $byVat[$k]['base'] = round($v['base'], 2);
            $byVat[$k]['vat'] = round($v['vat'], 2);
        }
        
        return [
            'base_total' => round($base, 2),
            'vat_total' => round($vat, 2),
            'by_vat' => $byVat,
            'range' => $range,
        ];
    }

    /**
     * Summarize expenses YTD (year to date) for modelo 130.
     */
    public static function summarizeExpensesYTD(int $year, int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $endMap = [
            1 => "$year-03-31",
            2 => "$year-06-30",
            3 => "$year-09-30",
            4 => "$year-12-31",
        ];
        $start = "$year-01-01";
        $end = $endMap[$quarter];
        
        $pdo = Database::pdo();
        $sql = 'SELECT SUM(base_amount) as base_total, SUM(vat_amount) as vat_total
                FROM expenses
                WHERE invoice_date >= :start AND invoice_date <= :end';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'base_total_ytd' => round((float)($row['base_total'] ?? 0), 2),
            'vat_total_ytd' => round((float)($row['vat_total'] ?? 0), 2),
            'range_ytd' => ['start' => $start, 'end' => $end],
        ];
    }
}
