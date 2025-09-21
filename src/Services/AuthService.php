<?php
declare(strict_types=1);

namespace Moni\Services;

final class AuthService
{
    public static function login(int $userId): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}
