<?php
declare(strict_types=1);

namespace Moni\Services;

final class PdfToImageService
{
    public static function convertFirstPage(string $pdfPath): ?string
    {
        if (!is_file($pdfPath)) {
            return null;
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/expenses';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            return null;
        }

        $targetPath = $storageDir . '/tmp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';

        if (self::convertWithImagick($pdfPath, $targetPath)) {
            return $targetPath;
        }

        if (self::convertWithPdftoppm($pdfPath, $targetPath)) {
            return $targetPath;
        }

        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('imagick') || self::hasPdftoppm();
    }

    private static function convertWithImagick(string $pdfPath, string $targetPath): bool
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($pdfPath . '[0]');
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $ok = $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            return $ok && is_file($targetPath);
        } catch (\Throwable $e) {
            error_log('[PdfToImageService] Imagick fallo: ' . $e->getMessage());
            return false;
        }
    }

    private static function convertWithPdftoppm(string $pdfPath, string $targetPath): bool
    {
        if (!self::hasPdftoppm()) {
            return false;
        }

        try {
            $prefix = preg_replace('/\.jpg$/', '', $targetPath) ?: $targetPath;
            $cmd = 'pdftoppm -jpeg -r 200 -f 1 -l 1 '
                . escapeshellarg($pdfPath)
                . ' '
                . escapeshellarg($prefix)
                . ' 2>/dev/null';

            @shell_exec($cmd);

            $generated = $prefix . '-1.jpg';
            if (is_file($generated)) {
                @rename($generated, $targetPath);
            }

            return is_file($targetPath);
        } catch (\Throwable $e) {
            error_log('[PdfToImageService] pdftoppm fallo: ' . $e->getMessage());
            return false;
        }
    }

    private static function hasPdftoppm(): bool
    {
        try {
            $result = @shell_exec('command -v pdftoppm 2>/dev/null');
            return is_string($result) && trim($result) !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
