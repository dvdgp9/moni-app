<?php
declare(strict_types=1);

use Moni\Support\Config;
use Moni\Repositories\SettingsRepository;

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
        'invoice_due_days' => 30,
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

// Cargar overrides desde BD del usuario autenticado cuando exista sesión.
try {
    $raw = SettingsRepository::all(null);
    $over = [];
    if (isset($raw['timezone']) && $raw['timezone'] !== '') {
        $over['settings']['timezone'] = $raw['timezone'];
    }
    if (isset($raw['notify_email']) && $raw['notify_email'] !== '') {
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
    if (isset($raw['invoice_due_days'])) {
        $days = (int)$raw['invoice_due_days'];
        if ($days > 0 && $days <= 90) {
            $over['settings']['invoice_due_days'] = $days;
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

if (!function_exists('route_path')) {
    function route_path(string $name, array $params = []): string
    {
        $path = match ($name) {
            'home' => '/',
            'login' => '/login',
            'register' => '/register',
            'logout' => '/logout',
            'dashboard' => '/dashboard',
            'settings' => '/ajustes',
            'clients' => '/clientes',
            'client_form' => isset($params['id']) ? '/clientes/editar' : '/clientes/nuevo',
            'invoices' => '/facturas',
            'invoice_form' => isset($params['id']) ? '/facturas/editar' : '/facturas/nueva',
            'invoice_pdf' => '/facturas/pdf',
            'expenses' => '/gastos',
            'expense_form' => isset($params['id']) ? '/gastos/editar' : '/gastos/nuevo',
            'expense_pdf' => '/gastos/pdf',
            'suppliers' => '/proveedores',
            'supplier_form' => isset($params['id']) ? '/proveedores/editar' : '/proveedores/nuevo',
            'profile' => '/perfil',
            'reminders' => '/notificaciones',
            'declaraciones' => '/declaraciones',
            'quotes' => '/presupuestos',
            'quote_form' => isset($params['id']) ? '/presupuestos/editar' : '/presupuestos/nuevo',
            'quote_pdf' => '/presupuestos/pdf',
            default => '/dashboard',
        };

        if (!empty($params)) {
            $query = http_build_query($params);
            if ($query !== '') {
                return $path . '?' . $query;
            }
        }

        return $path;
    }
}

if (!function_exists('moni_redirect')) {
    function moni_redirect(string $path, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $path, true, $status);
        }

        $safeUrl = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"><title>Redirigiendo…</title><script>window.location.replace(' . json_encode($path, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script></head><body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;padding:32px;color:#0f172a">Redirigiendo… Si no ocurre automáticamente, <a href="' . $safeUrl . '">continúa aquí</a>.</body></html>';
        exit;
    }
}
