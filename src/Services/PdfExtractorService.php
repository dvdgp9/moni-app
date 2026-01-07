<?php
declare(strict_types=1);

namespace Moni\Services;

use Smalot\PdfParser\Parser;

final class PdfExtractorService
{
    /**
     * Extract text content from a PDF file.
     * Returns empty string if extraction fails or PDF is scanned/image-based.
     */
    public static function extractText(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            return '';
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();
            
            // Clean up the text: normalize whitespace
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            return $text;
        } catch (\Throwable $e) {
            // PDF might be corrupted or password-protected
            return '';
        }
    }

    /**
     * Check if extracted text seems to contain invoice data.
     * Helps determine if we got useful content or need OCR.
     */
    public static function hasUsefulContent(string $text): bool
    {
        if (strlen($text) < 50) {
            return false;
        }

        // Look for common invoice indicators in Spanish
        $indicators = [
            '/\d+[.,]\d{2}/',           // Amounts like 123.45 or 123,45
            '/iva/i',                    // IVA
            '/factura/i',                // Factura
            '/total/i',                  // Total
            '/[A-Z]\d{8}/',              // CIF format
            '/\d{8}[A-Z]/',              // NIF format
        ];

        $matches = 0;
        foreach ($indicators as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }

        return $matches >= 2;
    }
}
