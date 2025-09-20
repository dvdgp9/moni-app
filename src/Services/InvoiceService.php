<?php
declare(strict_types=1);

namespace Moni\Services;

final class InvoiceService
{
    public static function computeTotals(array $items): array
    {
        $base = 0.0;
        $iva = 0.0;
        $irpf = 0.0;
        foreach ($items as $it) {
            $qty = (float)($it['quantity'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $lineBase = $qty * $price;
            $base += $lineBase;
            $iva += $lineBase * ((float)($it['vat_rate'] ?? 0) / 100.0);
            $irpf += $lineBase * ((float)($it['irpf_rate'] ?? 0) / 100.0);
        }
        $total = $base + $iva - $irpf;
        return [
            'base' => round($base, 2),
            'iva' => round($iva, 2),
            'irpf' => round($irpf, 2),
            'total' => round($total, 2),
        ];
    }
}
