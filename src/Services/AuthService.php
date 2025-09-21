<?php
declare(strict_types=1);

namespace Moni\Services;

final class AuthService
{
    private const COOKIE_NAME = 'moni_remember';
    private const COOKIE_DAYS = 30; // 30 days

    public static function login(int $userId, bool $remember = false): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = $userId;
        if ($remember) {
            self::issueRememberCookie($userId);
        }
    }

    public static function logout(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['user_id']);
        // clear remember cookie
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function userId(): ?int
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function autoLoginFromCookie(): bool
    {
        if (self::userId() !== null) { return true; }
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$cookie) { return false; }
        $data = json_decode(base64_decode($cookie), true);
        if (!is_array($data)) { return false; }
        $uid = (int)($data['uid'] ?? 0);
        $exp = (int)($data['exp'] ?? 0);
        $sig = (string)($data['sig'] ?? '');
        if ($uid <= 0 || $exp < time()) { return false; }
        $key = self::appKey();
        $payload = $uid . '|' . $exp;
        $calc = hash_hmac('sha256', $payload, $key);
        if (!hash_equals($calc, $sig)) { return false; }
        // all good, set session
        if (session_status() !== \PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION['user_id'] = $uid;
        return true;
    }

    private static function issueRememberCookie(int $userId): void
    {
        $exp = time() + (self::COOKIE_DAYS * 24 * 60 * 60);
        $payload = $userId . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, self::appKey());
        $value = base64_encode(json_encode(['uid' => $userId, 'exp' => $exp, 'sig' => $sig]));
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $exp,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function appKey(): string
    {
        $k = $_ENV['APP_KEY'] ?? '';
        if ($k === '') {
            $k = hash('sha256', __FILE__ . php_uname());
        }
        return $k;
    }
}
