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
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        if ($remember) {
            self::issueRememberCookie($userId);
        } else {
            self::clearRememberCookie();
        }
    }

    public static function logout(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['user_id']);
        $_SESSION = [];
        self::clearRememberCookie();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
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
        $key = self::appKey();
        if ($key === null) { return false; }
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$cookie) { return false; }
        $data = json_decode(base64_decode($cookie), true);
        if (!is_array($data)) { return false; }
        $uid = (int)($data['uid'] ?? 0);
        $exp = (int)($data['exp'] ?? 0);
        $sig = (string)($data['sig'] ?? '');
        if ($uid <= 0 || $exp < time()) { return false; }
        $payload = $uid . '|' . $exp;
        $calc = hash_hmac('sha256', $payload, $key);
        if (!hash_equals($calc, $sig)) { return false; }
        // all good, set session
        if (session_status() !== \PHP_SESSION_ACTIVE) { @session_start(); }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
        return true;
    }

    private static function issueRememberCookie(int $userId): void
    {
        $key = self::appKey();
        if ($key === null) {
            return;
        }
        $exp = time() + (self::COOKIE_DAYS * 24 * 60 * 60);
        $payload = $userId . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, $key);
        $value = base64_encode(json_encode(['uid' => $userId, 'exp' => $exp, 'sig' => $sig]));
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $exp,
            'path' => '/',
            'secure' => self::isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function appKey(): ?string
    {
        $k = $_ENV['APP_KEY'] ?? '';
        if ($k !== '') {
            return $k;
        }

        $storageDir = dirname(__DIR__, 2) . '/storage';
        $keyFile = $storageDir . '/app_key';

        if (is_file($keyFile) && is_readable($keyFile)) {
            $stored = trim((string)file_get_contents($keyFile));
            if ($stored !== '') {
                return $stored;
            }
        }

        if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            error_log('[auth] No se pudo crear storage para la clave persistente de APP_KEY.');
            return null;
        }

        try {
            $generated = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            error_log('[auth] No se pudo generar una clave persistente para recordar sesión: ' . $e->getMessage());
            return null;
        }

        if (@file_put_contents($keyFile, $generated . PHP_EOL, LOCK_EX) === false) {
            error_log('[auth] No se pudo guardar storage/app_key para recordar sesión.');
            return null;
        }

        @chmod($keyFile, 0600);
        return $generated;
    }

    private static function clearRememberCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && str_contains(strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']), 'https')) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        return false;
    }
}
