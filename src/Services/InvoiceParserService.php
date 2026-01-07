<?php
declare(strict_types=1);

namespace Moni\Services;

final class InvoiceParserService
{
    /**
     * Parse extracted text and try to identify invoice fields.
     * Returns array with extracted data and confidence indicators.
     */
    public static function parse(string $text): array
    {
        $result = [
            'supplier_name' => null,
            'supplier_nif' => null,
            'invoice_number' => null,
            'invoice_date' => null,
            'base_amount' => null,
            'vat_rate' => null,
            'vat_amount' => null,
            'total_amount' => null,
            'confidence' => [],
            'raw_text' => $text,
        ];

        // 1. Extract NIF/CIF (Spanish tax ID)
        $nif = self::extractNif($text);
        if ($nif) {
            $result['supplier_nif'] = $nif;
            $result['confidence']['supplier_nif'] = 'high';
        }

        // 2. Extract Supplier Name (Try to find it before NIF or at the beginning)
        $supplier = self::extractSupplierName($text, $nif);
        if ($supplier) {
            $result['supplier_name'] = $supplier;
            $result['confidence']['supplier_name'] = 'medium';
        }

        // 3. Extract dates
        $date = self::extractDate($text);
        if ($date) {
            $result['invoice_date'] = $date;
            $result['confidence']['invoice_date'] = 'medium';
        }

        // 4. Extract invoice number
        $invoiceNum = self::extractInvoiceNumber($text);
        if ($invoiceNum) {
            $result['invoice_number'] = $invoiceNum;
            $result['confidence']['invoice_number'] = 'medium';
        }

        // 5. Extract VAT rate first to help with math
        $vatRate = self::extractVatRate($text);
        if ($vatRate !== null) {
            $result['vat_rate'] = $vatRate;
            $result['confidence']['vat_rate'] = 'high';
        }

        // 6. Extract amounts and label them
        $amounts = self::extractAmounts($text);
        if (!empty($amounts)) {
            $labeled = self::labelAmounts($text, $amounts, $result['vat_rate']);
            $result = array_merge($result, $labeled);
        }

        return $result;
    }

    /**
     * Try to identify the supplier name.
     * Often at the very beginning of the text or near the CIF/NIF.
     */
    private static function extractSupplierName(string $text, ?string $nif): ?string
    {
        // Clean text a bit for name extraction
        $lines = explode("\n", str_replace(["\r", "\t"], ["\n", " "], $text));
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines));

        // Strategy 1: If we have a NIF, look at the line before it
        if ($nif) {
            foreach ($lines as $i => $line) {
                if (stripos($line, $nif) !== false) {
                    if ($i > 0) {
                        $candidate = $lines[$i-1];
                        // If line before is short or looks like a label, maybe the line before that
                        if (strlen($candidate) < 3 || stripos($candidate, 'factura') !== false) {
                            if ($i > 1) $candidate = $lines[$i-2];
                        }
                        if (strlen($candidate) > 3) return $candidate;
                    }
                }
            }
        }

        // Strategy 2: First line that isn't a common label
        $ignoredKeywords = ['factura', 'fecha', 'invoice', 'página', 'pág', 'cliente', 'lucushost']; // Special case for brand if needed
        foreach ($lines as $line) {
            $isIgnored = false;
            foreach ($ignoredKeywords as $kw) {
                if (stripos($line, $kw) === 0) { $isIgnored = true; break; }
            }
            if (!$isIgnored && strlen($line) > 3 && !preg_match('/^\d+$/', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Extract Spanish NIF/CIF from text.
     */
    private static function extractNif(string $text): ?string
    {
        // Look for CIF/NIF labels first to be more precise
        if (preg_match('/(?:NIF|CIF|NIF\/CIF|VAT|ID)[:\s]*([ABCDEFGHJNPQRSUVW]\d{8}|\d{8}[A-Z]|[XYZ]\d{7}[A-Z])/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        
        // CIF: Letter + 8 digits
        if (preg_match('/\b([ABCDEFGHJNPQRSUVW]\d{8})\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        // NIF: 8 digits + letter
        if (preg_match('/\b(\d{8}[A-Z])\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    /**
     * Extract date from text. Supports dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd.
     */
    private static function extractDate(string $text): ?string
    {
        // Look for date near keywords
        $datePatterns = [
            // dd/mm/yyyy or dd-mm-yyyy
            '/(?:fecha|date|emisi[oó]n)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i',
            // yyyy-mm-dd
            '/(?:fecha|date|emisi[oó]n)[:\s]*(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/i',
            // Standalone dates
            '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/',
            '/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/',
        ];

        foreach ($datePatterns as $i => $pattern) {
            if (preg_match($pattern, $text, $m)) {
                if ($i <= 1 || $i === 2) {
                    // dd/mm/yyyy format
                    if (strlen($m[1]) === 4) {
                        // yyyy-mm-dd
                        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                    }
                    $day = (int)$m[1];
                    $month = (int)$m[2];
                    $year = (int)$m[3];
                    if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                        return sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                } elseif ($i === 3) {
                    // yyyy-mm-dd
                    return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                }
            }
        }
        return null;
    }

    /**
     * Extract invoice number from text.
     */
    private static function extractInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/(?:factura|invoice|n[uú]mero|n[º°])[:\s]*([A-Z0-9\-\/]+)/i',
            '/(?:fra|fact)[.\s]*n[º°]?[:\s]*([A-Z0-9\-\/]+)/i',
            '/\b(\d{4}[\-\/]\d{3,6})\b/', // Common format: 2024-001234
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $num = trim($m[1]);
                if (strlen($num) >= 3 && strlen($num) <= 30) {
                    return $num;
                }
            }
        }
        return null;
    }

    /**
     * Extract all monetary amounts from text.
     * Returns array of floats.
     */
    private static function extractAmounts(string $text): array
    {
        $amounts = [];

        // Spanish format: 1.234,56 or 1234,56
        if (preg_match_all('/(\d{1,3}(?:\.\d{3})*,\d{2})\s*€?/', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $val = str_replace('.', '', $m);
                $val = str_replace(',', '.', $val);
                $amounts[] = (float)$val;
            }
        }

        // International format: 1,234.56 or 1234.56
        if (preg_match_all('/(\d{1,3}(?:,\d{3})*\.\d{2})\s*€?/', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $val = str_replace(',', '', $m);
                $amounts[] = (float)$val;
            }
        }

        // Simple amounts: 123.45 (ambiguous, could be either format)
        if (preg_match_all('/\b(\d+[.,]\d{2})\b/', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $val = str_replace(',', '.', $m);
                $f = (float)$val;
                if (!in_array($f, $amounts, true)) {
                    $amounts[] = $f;
                }
            }
        }

        // Remove duplicates and sort descending
        $amounts = array_unique($amounts);
        rsort($amounts);

        return array_values($amounts);
    }

    /**
     * Try to label amounts as base, VAT, total based on context and math.
     */
    private static function labelAmounts(string $text, array $amounts, ?float $detectedVatRate): array
    {
        $result = [
            'base_amount' => null,
            'vat_amount' => null,
            'total_amount' => null,
            'confidence' => [],
        ];

        if (empty($amounts)) {
            return $result;
        }

        $textLower = strtolower($text);

        // Strategy 1: Look for explicit labels
        // Total
        if (preg_match('/(?:total|importe|total\s+factura)[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if (in_array($val, $amounts, true)) {
                $result['total_amount'] = $val;
                $result['confidence']['total_amount'] = 'high';
            }
        }

        // Subtotal / Base
        if (preg_match('/(?:sub\s*total|base\s*imponible|suma)[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if (in_array($val, $amounts, true)) {
                $result['base_amount'] = $val;
                $result['confidence']['base_amount'] = 'high';
            }
        }

        // IVA amount
        if (preg_match('/(?:cuota\s*)?iva(?:\s*\d+\s*%)?[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if (in_array($val, $amounts, true)) {
                $result['vat_amount'] = $val;
                $result['confidence']['vat_amount'] = 'medium';
            }
        }

        // Strategy 2: Mathematical verification if we have multiple amounts
        if (count($amounts) >= 2) {
            // Check if any two amounts sum up to a third one
            // Or if an amount * (1 + rate) equals another
            $rate = ($detectedVatRate ?? 21.0) / 100.0;

            foreach ($amounts as $total) {
                foreach ($amounts as $base) {
                    if ($total <= $base) continue;
                    
                    $diff = round($total - $base, 2);
                    // Check if diff is in amounts
                    $hasDiffInAmounts = false;
                    foreach ($amounts as $a) {
                        if (abs($a - $diff) < 0.05) { $hasDiffInAmounts = true; break; }
                    }

                    // Check if base * rate matches diff
                    $expectedVat = round($base * $rate, 2);
                    $mathMatches = abs($expectedVat - $diff) < 0.10;

                    if ($mathMatches || $hasDiffInAmounts) {
                        if ($result['total_amount'] === null || $total === $result['total_amount']) {
                            $result['total_amount'] = $total;
                            $result['base_amount'] = $base;
                            $result['vat_amount'] = $diff;
                            $result['confidence']['total_amount'] = 'high';
                            $result['confidence']['base_amount'] = 'high';
                            $result['confidence']['vat_amount'] = 'calculated';
                            return $result;
                        }
                    }
                }
            }
        }

        // Fallback: Largest is total, second largest is base if it looks like it
        if ($result['total_amount'] === null) {
            $result['total_amount'] = $amounts[0];
            $result['confidence']['total_amount'] = 'low';
        }

        if ($result['base_amount'] === null && count($amounts) > 1) {
            // If we have total and another amount, check if it could be base
            $potentialBase = $amounts[1];
            if ($result['total_amount'] > $potentialBase) {
                $result['base_amount'] = $potentialBase;
                $result['confidence']['base_amount'] = 'low';
            }
        }

        return $result;
    }

    /**
     * Parse amount string to float.
     */
    private static function parseAmount(string $str): float
    {
        // Remove currency symbols and spaces
        $str = preg_replace('/[€$\s]/', '', $str);
        
        // Detect format: if has both . and , check which is decimal
        if (strpos($str, '.') !== false && strpos($str, ',') !== false) {
            // Spanish: 1.234,56 -> 1234.56
            if (strrpos($str, ',') > strrpos($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // International: 1,234.56 -> 1234.56
                $str = str_replace(',', '', $str);
            }
        } elseif (strpos($str, ',') !== false) {
            // Only comma: assume decimal separator (Spanish)
            $str = str_replace(',', '.', $str);
        }

        return (float)$str;
    }

    /**
     * Extract VAT rate from text (common rates: 21%, 10%, 4%).
     */
    private static function extractVatRate(string $text): ?float
    {
        // Look for IVA percentage
        $patterns = [
            '/iva\s*(?:al\s*)?(\d{1,2})\s*%/i',
            '/(\d{1,2})\s*%\s*(?:de\s*)?iva/i',
            '/tipo\s*(?:de\s*)?iva[:\s]*(\d{1,2})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $rate = (float)$m[1];
                // Common Spanish VAT rates
                if (in_array($rate, [21.0, 10.0, 4.0, 0.0], true)) {
                    return $rate;
                }
            }
        }

        // Default to 21% if we found IVA mention but no rate
        if (stripos($text, 'iva') !== false) {
            return 21.0;
        }

        return null;
    }
}
