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
        // Strategy 1: Look for company name patterns near CIF
        // Common Spanish company suffixes: S.L., S.L.U., S.A., S.COOP
        if (preg_match('/([A-ZÁÉÍÓÚÑ][A-Za-záéíóúñ\s]+(?:S\.?L\.?U?\.?|S\.?A\.?|S\.?COOP\.?))/u', $text, $m)) {
            $name = trim($m[1]);
            if (strlen($name) > 5 && strlen($name) < 100) {
                return $name;
            }
        }

        // Strategy 2: If we have a NIF, look for text immediately before it
        if ($nif) {
            // Pattern: "Company Name CIF: B12345678" or "Company Name\nCIF B12345678"
            $pattern = '/([A-ZÁÉÍÓÚÑ][A-Za-záéíóúñ\s.,]+?)\s*(?:CIF|NIF|NIF\/CIF)?[:\s]*' . preg_quote($nif, '/') . '/ui';
            if (preg_match($pattern, $text, $m)) {
                $name = trim($m[1]);
                // Remove trailing labels
                $name = preg_replace('/\s*(CIF|NIF|C\/|Calle|Avda|Tel|www).*$/i', '', $name);
                $name = trim($name);
                if (strlen($name) > 3 && strlen($name) < 100) {
                    return $name;
                }
            }
        }

        // Strategy 3: Split into segments and find first one that looks like a company name
        $segments = preg_split('/[\n\r]+/', $text);
        $segments = array_map('trim', $segments);
        $segments = array_filter($segments);
        
        foreach ($segments as $segment) {
            // Skip if too short, too long, or looks like a label/date/amount
            if (strlen($segment) < 5 || strlen($segment) > 80) continue;
            if (preg_match('/^(factura|fecha|invoice|cliente|total|iva|base|pagado|vencimiento)/i', $segment)) continue;
            if (preg_match('/^\d+[\/\-]\d+/', $segment)) continue; // Dates
            if (preg_match('/^\d+[.,]\d{2}\s*€?$/', $segment)) continue; // Amounts
            
            // Looks like it could be a name
            if (preg_match('/^[A-ZÁÉÍÓÚÑ]/u', $segment)) {
                return $segment;
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

        // Strategy 1: Look for explicit labels with amounts
        // Find ALL labeled amounts first, then pick the right ones
        
        // TOTAL - usually the largest and labeled "Total"
        // Look for the LAST occurrence of "Total" followed by amount (final total)
        if (preg_match_all('/(?<!sub\s)total[:\s]*(\d[\d.,]*\s*€?)/i', $text, $matches, PREG_SET_ORDER)) {
            // Take the last match (usually the final total)
            $lastMatch = end($matches);
            $val = self::parseAmount($lastMatch[1]);
            foreach ($amounts as $a) {
                if (abs($a - $val) < 0.05) {
                    $result['total_amount'] = $a;
                    $result['confidence']['total_amount'] = 'high';
                    break;
                }
            }
        }

        // SUBTOTAL / BASE - look for "Sub Total", "Subtotal", "Base imponible"
        if (preg_match('/(?:sub\s*total|subtotal|base\s*imponible)[:\s]*(\d[\d.,]*\s*€?)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            foreach ($amounts as $a) {
                if (abs($a - $val) < 0.05) {
                    $result['base_amount'] = $a;
                    $result['confidence']['base_amount'] = 'high';
                    break;
                }
            }
        }

        // IVA amount - look for "X% IVA: amount" or "IVA: amount"
        if (preg_match('/\d+(?:[.,]\d+)?\s*%\s*iva[:\s]*(\d[\d.,]*\s*€?)/i', $text, $m)) {
            $val = self::parseAmount($m[1]);
            foreach ($amounts as $a) {
                if (abs($a - $val) < 0.05) {
                    $result['vat_amount'] = $a;
                    $result['confidence']['vat_amount'] = 'high';
                    break;
                }
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
        // Look for IVA percentage - support decimals like 21.00%
        $patterns = [
            '/(\d{1,2})(?:[.,]\d+)?\s*%\s*(?:de\s*)?iva/i',   // "21.00% IVA" or "21% IVA"
            '/iva\s*(?:al\s*)?(\d{1,2})(?:[.,]\d+)?\s*%/i',   // "IVA al 21%" or "IVA 21.00%"
            '/tipo\s*(?:de\s*)?iva[:\s]*(\d{1,2})/i',          // "Tipo IVA: 21"
            '/iva\s*[(](\d{1,2})(?:[.,]\d+)?\s*%[)]/i',        // "IVA (21%)"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $rate = (int)$m[1]; // Take integer part
                // Common Spanish VAT rates
                if (in_array($rate, [21, 10, 4, 0], true)) {
                    return (float)$rate;
                }
            }
        }

        // Check for IVA mention with amount - implies there IS IVA
        if (preg_match('/iva[:\s]+\d/', $text, $m)) {
            return 21.0; // Default rate
        }

        return null;
    }
}
