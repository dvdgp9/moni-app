<?php
declare(strict_types=1);

namespace Moni\Services;

final class ExpenseDocumentService
{
    private const ALLOWED_MIME_MAP = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    public static function storeUploaded(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error al subir el archivo.');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Archivo subido no valido.');
        }

        $mimeType = self::detectMimeFromUploadedFile($tmpPath);
        if (!isset(self::ALLOWED_MIME_MAP[$mimeType])) {
            throw new \RuntimeException('El archivo debe ser PDF o imagen JPG, PNG, WEBP o HEIC.');
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/expenses';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento del documento.');
        }

        $extension = self::ALLOWED_MIME_MAP[$mimeType];
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $storageDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new \RuntimeException('Error al guardar el archivo.');
        }

        return [
            'relative_path' => 'storage/expenses/' . $filename,
            'mime_type' => $mimeType,
            'document_kind' => self::isImageMime($mimeType) ? 'image' : 'pdf',
            'original_name' => (string)($file['name'] ?? $filename),
        ];
    }

    public static function mimeTypeForPath(string $fullPath): string
    {
        if (!is_file($fullPath)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }
        $mimeType = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
        finfo_close($finfo);

        return is_string($mimeType) ? $mimeType : 'application/octet-stream';
    }

    public static function isImagePath(string $relativePath): bool
    {
        $fullPath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        return self::isImageMime(self::mimeTypeForPath($fullPath));
    }

    private static function detectMimeFromUploadedFile(string $tmpPath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new \RuntimeException('No se pudo inspeccionar el archivo.');
        }
        $mimeType = finfo_file($finfo, $tmpPath) ?: '';
        finfo_close($finfo);

        return is_string($mimeType) ? $mimeType : '';
    }

    private static function isImageMime(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }
}
