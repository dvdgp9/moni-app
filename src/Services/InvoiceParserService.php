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
        // Limpiamos el texto de ruidos comunes al inicio
        $cleanText = preg_replace('/^(PAGADA|VENCIDA|COBRADA|EMITIDA|FACTURA|FRA|RECIBO)\s+/i', '', trim($text));
        
        // Estrategia 0: Si tenemos el NIF, buscar el nombre que está justo antes o en la misma línea
        if ($nif) {
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                if (stripos($line, $nif) !== false) {
                    // Si el NIF está en una línea, el nombre suele estar en esa misma línea al principio 
                    // o en la línea inmediatamente superior.
                    $cleanLine = preg_replace('/(CIF|NIF|NIF\/CIF)[:\s]*' . preg_quote($nif, '/') . '.*$/i', '', $line);
                    $cleanLine = trim(preg_replace('/^(PAGADA|VENCIDA|COBRADA|EMITIDA|FACTURA|FRA|RECIBO)\s+/i', '', trim($cleanLine)));
                    
                    if (strlen($cleanLine) > 4 && !self::isTechnicalDescription($cleanLine)) {
                        return $cleanLine;
                    }
                }
            }
        }

        // Estrategia 1 (PRIORITY): Buscar en el bloque superior del documento (primeros 500 caracteres)
        $header = substr($text, 0, 500);
        $lines = explode("\n", $header);
        $lines = array_map('trim', array_filter($lines));

        foreach ($lines as $line) {
            // Saltamos líneas que son claramente etiquetas o datos irrelevantes
            if (strlen($line) < 4 || strlen($line) > 80) continue;
            if (preg_match('/^(factura|fecha|invoice|n[uú]mero|nº|página|pág|tel|www|http|@|cliente|facturado|dirección|cp|provincia|fecha|vencimiento)/i', $line)) continue;
            if (preg_match('/^\d+[\/\-]\d+/', $line)) continue; // Fechas
            if (preg_match('/^\d+[.,]\d{2}/', $line)) continue; // Importes
            if (self::isTechnicalDescription($line)) continue;

            // El primer candidato serio en el header suele ser el proveedor
            $name = preg_replace('/^(PAGADA|VENCIDA|COBRADA|EMITIDA|FACTURA|FRA|RECIBO)\s+/i', '', $line);
            return trim($name);
        }

        return null;
    }

    /**
     * Check if a string looks like a technical product description
     * (many uppercase acronyms, technical terms, etc.)
     */
    private static function isTechnicalDescription(string $text): bool
    {
        // If has many consecutive uppercase words or acronyms, likely technical
        $upperWords = preg_match_all('/\b[A-Z]{2,}\b/', $text);
        if ($upperWords > 3) return true;
        
        // Common technical terms to exclude
        $techTerms = ['Windows', 'Remote', 'Desktop', 'License', 'Server', 'Cloud', 'API', 'SDK', 'ALNG', 'MVL', 'SPLA', 'RDS', 'VPS'];
        foreach ($techTerms as $term) {
            if (stripos($text, $term) !== false) return true;
        }
        
        return false;
    }

    /**
     * Extract Spanish NIF/CIF from text (SUPPLIER's NIF, not client's).
     * Prioritizes NIFs that appear in the first part of the document (supplier info).
     */
    private static function extractNif(string $text): ?string
    {
        $potentialNifs = [];
        
        // Buscamos todos los NIF/CIF con su posición
        if (preg_match_all('/\b([ABCDEFGHJNPQRSUVW]\d{8}|\d{8}[A-Z])\b/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $nif = strtoupper($match[0]);
                $pos = $match[1];
                
                // Contexto extendido antes
                $contextBefore = substr($text, max(0, $pos - 150), 150);

                // Si detectamos palabras de cliente/receptor muy cerca ANTES del NIF, es el receptor
                // "Facturado a:", "Cliente:", "Datos de facturación", etc.
                $isClient = preg_match('/(facturado\s+a|cliente|destinatario|datos\s+de\s+fact|receptor|dirección\s+de\s+fact|población|vencimiento)/i', $contextBefore);
                
                if (!$isClient) {
                    // Si no parece cliente, lo guardamos con su posición
                    if (!isset($potentialNifs[$nif])) {
                        $potentialNifs[$nif] = $pos;
                    }
                }
            }
        }

        if (empty($potentialNifs)) {
            // Si no hemos encontrado ninguno "limpio", probamos con todos pero priorizando la posición 0
            if (preg_match_all('/\b([ABCDEFGHJNPQRSUVW]\d{8}|\d{8}[A-Z])\b/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $nif = strtoupper($match[0]);
                    if (!isset($potentialNifs[$nif])) $potentialNifs[$nif] = $match[1];
                }
            }
        }
        
        if (empty($potentialNifs)) return null;
        
        // El proveedor siempre es el primero que aparece en la parte superior del documento
        asort($potentialNifs);
        return array_key_first($potentialNifs);
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
