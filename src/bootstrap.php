<?php
declare(strict_types=1);

use Moni\Support\Config;
use Moni\Repositories\SettingsRepository;

require_once __DIR__ . '/support/Config.php';

Config::init([
    'app_name' => $_ENV['APP_NAME'] ?? 'Moni',
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'timezone' => $_ENV['TIMEZONE'] ?? 'Europe/Madrid',
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'moni',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Moni',
    ],
    'settings' => [
        'reminders_enabled' => true,
        'notify_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
        'timezone' => $_ENV['TIMEZONE'] ?? 'Europe/Madrid',
        'custom_dates' => [], // array de strings YYYY-MM-DD
    ],
]);

// Toggle PHP error display based on debug mode
if (Config::get('debug')) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '0');
}

// Cargar overrides desde BD (single-user por ahora => user_id null)
try {
    $raw = SettingsRepository::all(null);
    $over = [];
    if (isset($raw['timezone']) && $raw['timezone'] !== '') {
        $over['settings']['timezone'] = $raw['timezone'];
    }
    if (isset($raw['notify_email'])) {
        $over['settings']['notify_email'] = $raw['notify_email'];
    }
    if (isset($raw['reminders_enabled'])) {
        $over['settings']['reminders_enabled'] = $raw['reminders_enabled'] === '1' || $raw['reminders_enabled'] === 'true';
    }
    if (isset($raw['reminder_custom_dates'])) {
        $decoded = json_decode($raw['reminder_custom_dates'] ?: '[]', true);
        if (is_array($decoded)) {
            $over['settings']['custom_dates'] = $decoded;
        }
    }
    if (!empty($over)) {
        Config::merge($over);
    }
} catch (Throwable $e) {
    // Silenciar errores de settings en producción
}

// Ajustar zona horaria final según ajustes
$timezone = Config::get('settings.timezone', Config::get('timezone', 'Europe/Madrid'));
@date_default_timezone_set($timezone);
