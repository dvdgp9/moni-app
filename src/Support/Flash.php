<?php
declare(strict_types=1);

namespace Moni\Support;

final class Flash
{
    private const KEY = '_flash_messages';

    public static function add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION[self::KEY][$type][] = $message;
    }

    public static function getAll(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $all = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $all;
    }
}
