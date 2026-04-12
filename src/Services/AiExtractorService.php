<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Support\Config;

final class AiExtractorService
{
    private const DEFAULT_BASE_URL = 'https://openrouter.ai/api/v1';
    private const DEFAULT_MODEL = 'google/gemini-3.1-flash-lite-preview';
    private const DEFAULT_TIMEOUT = 30;

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function isEnabled(): bool
    {
        return (bool) Config::get('settings.ai_enabled', false) && self::isConfigured();
    }

    public static function extractFromText(string $text, array $categories): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $messages = [
            ['role' => 'system', 'content' => self::buildSystemPrompt($categories)],
            ['role' => 'user', 'content' => "Extrae datos de esta factura/ticket y responde SOLO con JSON válido.\n\nTEXTO:\n" . $text],
        ];

        $parsed = self::requestExtraction($messages);
        return self::normalizeExtraction($parsed, $categories);
    }

    public static function extractFromImagePath(string $imagePath, array $categories): array
    {
        if (!is_file($imagePath)) {
            return [];
        }

        $mimeType = ExpenseDocumentService::mimeTypeForPath($imagePath);
        if (!str_starts_with($mimeType, 'image/')) {
            return [];
        }

        $imageRaw = @file_get_contents($imagePath);
        if ($imageRaw === false || $imageRaw === '') {
            return [];
        }

        $base64 = base64_encode($imageRaw);
        $messages = [
            ['role' => 'system', 'content' => self::buildSystemPrompt($categories)],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Extrae datos de esta factura/ticket y responde SOLO con JSON válido.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $mimeType . ';base64,' . $base64,
                        ],
                    ],
                ],
            ],
        ];

        $parsed = self::requestExtraction($messages);
        return self::normalizeExtraction($parsed, $categories);
    }

    public static function testConnection(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $messages = [
            ['role' => 'system', 'content' => 'Responde SOLO con JSON válido: {"ok": true}'],
            ['role' => 'user', 'content' => 'Devuelve {"ok": true}.'],
        ];

        $payload = self::chatPayload($messages);
        $response = self::httpPost(self::baseUrl() . '/chat/completions', $payload);
        return isset($response['choices'][0]['message']['content']);
    }

    private static function requestExtraction(array $messages): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        $payload = self::chatPayload($messages);
        $response = self::httpPost(self::baseUrl() . '/chat/completions', $payload);
        if (!isset($response['choices'][0]['message']['content'])) {
            return [];
        }

        $content = (string) $response['choices'][0]['message']['content'];
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function chatPayload(array $messages): array
    {
        return [
            'model' => self::model(),
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
        ];
    }

    private static function httpPost(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            error_log('[AiExtractorService] No se pudo inicializar cURL');
            return [];
        }

        $headers = [
            'Authorization: Bearer ' . self::apiKey(),
            'Content-Type: application/json',
            'HTTP-Referer: ' . ((string) Config::get('app_url', 'http://localhost')),
            'X-Title: ' . ((string) Config::get('app_name', 'Moni')),
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::timeout(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            error_log('[AiExtractorService] Respuesta vacía de OpenRouter. Error cURL: ' . $curlError);
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            error_log('[AiExtractorService] JSON inválido de OpenRouter. HTTP ' . $httpCode);
            return [];
        }

        if ($httpCode >= 400) {
            $msg = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : 'Error desconocido';
            error_log('[AiExtractorService] Error OpenRouter HTTP ' . $httpCode . ': ' . $msg);
            return [];
        }

        return $decoded;
    }

    private static function buildSystemPrompt(array $categories): string
    {
        $categoryKeys = array_keys($categories);
        $categoryList = implode(', ', $categoryKeys);

        return "Eres un extractor de datos de facturas y tickets de España. "
            . "Responde SIEMPRE con JSON válido y sin texto adicional. "
            . "Si no sabes un campo, devuelve null. "
            . "Campos requeridos: supplier_name, supplier_nif, invoice_number, invoice_date (YYYY-MM-DD), "
            . "base_amount, vat_rate, vat_amount, total_amount, suggested_category, confidence. "
            . "confidence debe ser un objeto por campo con valores high/medium/low. "
            . "suggested_category debe ser uno de: [" . $categoryList . "] o 'otros'.";
    }

    private static function normalizeExtraction(array $raw, array $categories): array
    {
        if (empty($raw)) {
            return [];
        }

        $allowedCategories = array_keys($categories);
        $out = [
            'supplier_name' => self::normalizeString($raw['supplier_name'] ?? null),
            'supplier_nif' => self::normalizeNif($raw['supplier_nif'] ?? null),
            'invoice_number' => self::normalizeString($raw['invoice_number'] ?? null),
            'invoice_date' => self::normalizeDate($raw['invoice_date'] ?? null),
            'base_amount' => self::normalizeFloat($raw['base_amount'] ?? null),
            'vat_rate' => self::normalizeFloat($raw['vat_rate'] ?? null),
            'vat_amount' => self::normalizeFloat($raw['vat_amount'] ?? null),
            'total_amount' => self::normalizeFloat($raw['total_amount'] ?? null),
            'suggested_category' => self::normalizeCategory($raw['suggested_category'] ?? null, $allowedCategories),
            'confidence' => is_array($raw['confidence'] ?? null) ? $raw['confidence'] : [],
        ];

        return $out;
    }

    private static function normalizeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private static function normalizeNif($value): ?string
    {
        $value = self::normalizeString($value);
        return $value !== null ? strtoupper($value) : null;
    }

    private static function normalizeDate($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private static function normalizeFloat($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(['€', ' '], '', $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function normalizeCategory($value, array $allowed): string
    {
        if (!is_string($value)) {
            return 'otros';
        }

        $value = trim(mb_strtolower($value, 'UTF-8'));
        return in_array($value, $allowed, true) ? $value : 'otros';
    }

    private static function baseUrl(): string
    {
        $fromSettings = trim((string) Config::get('settings.ai_base_url', self::DEFAULT_BASE_URL));
        return $fromSettings !== '' ? rtrim($fromSettings, '/') : self::DEFAULT_BASE_URL;
    }

    private static function model(): string
    {
        $fromSettings = trim((string) Config::get('settings.ai_model', self::DEFAULT_MODEL));
        return $fromSettings !== '' ? $fromSettings : self::DEFAULT_MODEL;
    }

    private static function timeout(): int
    {
        $timeout = (int) Config::get('settings.ai_timeout', self::DEFAULT_TIMEOUT);
        return max(5, min(120, $timeout));
    }

    private static function apiKey(): string
    {
        return trim((string) ($_ENV['OPENROUTER_API_KEY'] ?? ''));
    }
}
