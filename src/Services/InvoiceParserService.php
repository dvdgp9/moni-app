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

        // Extract NIF/CIF (Spanish tax ID)
        $nif = self::extractNif($text);
        if ($nif) {
            $result['supplier_nif'] = $nif;
            $result['confidence']['supplier_nif'] = 'high';
        }

        // Extract dates
        $date = self::extractDate($text);
        if ($date) {
            $result['invoice_date'] = $date;
            $result['confidence']['invoice_date'] = 'medium';
        }

        // Extract invoice number
        $invoiceNum = self::extractInvoiceNumber($text);
        if ($invoiceNum) {
            $result['invoice_number'] = $invoiceNum;
            $result['confidence']['invoice_number'] = 'medium';
        }

        // Extract amounts
        $amounts = self::extractAmounts($text);
        if (!empty($amounts)) {
            // Try to identify base, VAT, and total
            $labeled = self::labelAmounts($text, $amounts);
            $result = array_merge($result, $labeled);
        }

        // Try to extract VAT rate
        $vatRate = self::extractVatRate($text);
        if ($vatRate !== null) {
            $result['vat_rate'] = $vatRate;
            $result['confidence']['vat_rate'] = 'high';
        }

        return $result;
    }

    /**
     * Extract Spanish NIF/CIF from text.
     * Formats: B12345678 (CIF), 12345678A (NIF), X1234567A (NIE)
     */
    private static function extractNif(string $text): ?string
    {
        // CIF: Letter + 8 digits (companies)
        if (preg_match('/\b([ABCDEFGHJNPQRSUVW]\d{8})\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        // NIF: 8 digits + letter (individuals)
        if (preg_match('/\b(\d{8}[A-Z])\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        // NIE: X/Y/Z + 7 digits + letter (foreigners)
        if (preg_match('/\b([XYZ]\d{7}[A-Z])\b/i', $text, $m)) {
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
    private static function labelAmounts(string $text, array $amounts): array
    {
        $result = [
            'base_amount' => null,
            'vat_amount' => null,
            'total_amount' => null,
        ];

        if (empty($amounts)) {
            return $result;
        }

        // Look for labeled amounts in text
        $textLower = strtolower($text);

        // Try to find total
        if (preg_match('/total[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if ($val > 0) {
                $result['total_amount'] = $val;
                $result['confidence']['total_amount'] = 'high';
            }
        }

        // Try to find base
        if (preg_match('/base\s*(?:imponible)?[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if ($val > 0) {
                $result['base_amount'] = $val;
                $result['confidence']['base_amount'] = 'high';
            }
        }

        // Try to find IVA amount
        if (preg_match('/(?:cuota\s*)?iva[:\s]*(\d[\d.,]*)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            if ($val > 0 && $val < 1000) { // IVA amount should be reasonable
                $result['vat_amount'] = $val;
                $result['confidence']['vat_amount'] = 'medium';
            }
        }

        // If we don't have labeled values, try to infer from amounts
        if ($result['total_amount'] === null && count($amounts) >= 1) {
            // Largest amount is likely total
            $result['total_amount'] = $amounts[0];
            $result['confidence']['total_amount'] = 'low';
        }

        // Try to calculate missing values
        if ($result['total_amount'] && $result['base_amount'] && !$result['vat_amount']) {
            $result['vat_amount'] = round($result['total_amount'] - $result['base_amount'], 2);
            $result['confidence']['vat_amount'] = 'calculated';
        }

        if ($result['total_amount'] && $result['vat_amount'] && !$result['base_amount']) {
            $result['base_amount'] = round($result['total_amount'] - $result['vat_amount'], 2);
            $result['confidence']['base_amount'] = 'calculated';
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
