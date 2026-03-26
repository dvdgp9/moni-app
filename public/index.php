<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Falta vendor/autoload.php. Instala dependencias con Composer (composer install).';
    exit;
}
require $autoload;

use Dotenv\Dotenv;

try {
    if (file_exists($root . '/.env')) {
        $dotenv = Dotenv::createImmutable($root);
        $dotenv->safeLoad();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error cargando .env: ' . htmlspecialchars($e->getMessage());
    exit;
}

$timezone = $_ENV['TIMEZONE'] ?? 'Europe/Madrid';
@date_default_timezone_set($timezone);

if (!function_exists('moni_request_is_https')) {
    function moni_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = strtolower(trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO']));
            if (in_array($proto, ['https', 'https,http'], true) || str_contains($proto, 'https')) {
                return true;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        return false;
    }
}

if (!function_exists('moni_should_force_https')) {
    function moni_should_force_https(): bool
    {
        $force = strtolower((string)($_ENV['FORCE_HTTPS'] ?? ''));
        if (in_array($force, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        $appUrl = trim((string)($_ENV['APP_URL'] ?? ''));
        return $appUrl !== '' && strtolower((string)parse_url($appUrl, PHP_URL_SCHEME)) === 'https';
    }
}

if (PHP_SAPI !== 'cli' && moni_should_force_https() && !moni_request_is_https()) {
    $appUrl = trim((string)($_ENV['APP_URL'] ?? ''));
    $target = $appUrl !== ''
        ? rtrim($appUrl, '/') . ($_SERVER['REQUEST_URI'] ?? '/')
        : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $target, true, 302);
    exit;
}

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => moni_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (moni_request_is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

require_once $root . '/src/bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = rtrim($requestPath, '/') ?: '/';

$legacyRoutes = [
    'home' => 'home',
    'dashboard' => 'dashboard',
    'settings' => 'settings',
    'clients' => 'clients',
    'client_form' => 'client_form',
    'invoices' => 'invoices',
    'invoice_form' => 'invoice_form',
    'invoice_pdf' => 'invoice_pdf',
    'expenses' => 'expenses',
    'expense_form' => 'expense_form',
    'expense_pdf' => 'expense_pdf',
    'suppliers' => 'suppliers',
    'supplier_form' => 'supplier_form',
    'login' => 'login',
    'register' => 'register',
    'logout' => 'logout',
    'profile' => 'profile',
    'reminders' => 'reminders',
    'declaraciones' => 'declaraciones',
];

$pathRoutes = [
    '/' => 'home',
    '/login' => 'login',
    '/register' => 'register',
    '/logout' => 'logout',
    '/dashboard' => 'dashboard',
    '/ajustes' => 'settings',
    '/clientes' => 'clients',
    '/clientes/nuevo' => 'client_form',
    '/clientes/editar' => 'client_form',
    '/facturas' => 'invoices',
    '/facturas/nueva' => 'invoice_form',
    '/facturas/editar' => 'invoice_form',
    '/facturas/pdf' => 'invoice_pdf',
    '/gastos' => 'expenses',
    '/gastos/nuevo' => 'expense_form',
    '/gastos/editar' => 'expense_form',
    '/gastos/pdf' => 'expense_pdf',
    '/proveedores' => 'suppliers',
    '/proveedores/nuevo' => 'supplier_form',
    '/proveedores/editar' => 'supplier_form',
    '/perfil' => 'profile',
    '/notificaciones' => 'reminders',
    '/declaraciones' => 'declaraciones',
];

$page = $pathRoutes[$requestPath] ?? null;

if ($page === null && isset($_GET['page']) && isset($legacyRoutes[$_GET['page']])) {
    $page = $legacyRoutes[$_GET['page']];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $params = $_GET;
        unset($params['page']);
        header('Location: ' . route_path($page, $params), true, 302);
        exit;
    }
}

if ($page === null) {
    http_response_code(404);
    $page = 'home';
}

$authClass = \Moni\Services\AuthService::class;
if (method_exists($authClass, 'autoLoginFromCookie')) {
    $authClass::autoLoginFromCookie();
}

$routes = [
    'home' => $root . '/templates/home.php',
    'dashboard' => $root . '/templates/dashboard.php',
    'settings' => $root . '/templates/settings.php',
    'clients' => $root . '/templates/clients_list.php',
    'client_form' => $root . '/templates/clients_form.php',
    'invoices' => $root . '/templates/invoices_list.php',
    'invoice_form' => $root . '/templates/invoices_form.php',
    'invoice_pdf' => $root . '/templates/invoices_pdf.php',
    'expenses' => $root . '/templates/expenses.php',
    'expense_form' => $root . '/templates/expense_form.php',
    'expense_pdf' => $root . '/templates/expense_pdf.php',
    'suppliers' => $root . '/templates/suppliers_list.php',
    'supplier_form' => $root . '/templates/suppliers_form.php',
    'login' => $root . '/templates/login.php',
    'register' => $root . '/templates/register.php',
    'logout' => $root . '/templates/logout.php',
    'profile' => $root . '/templates/profile.php',
    'reminders' => $root . '/templates/reminders.php',
    'declaraciones' => $root . '/templates/declaraciones.php',
];

$template = $routes[$page] ?? $routes['home'];

$protected = [
    'dashboard',
    'settings', 'clients', 'client_form',
    'invoices', 'invoice_form', 'invoice_pdf',
    'expenses', 'expense_form', 'expense_pdf',
    'suppliers', 'supplier_form',
    'profile', 'reminders', 'declaraciones',
];

if (in_array($page, $protected, true) && empty($_SESSION['user_id'])) {
    \Moni\Support\Flash::add('error', 'Inicia sesión para continuar.');
    $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? route_path('dashboard');
    header('Location: ' . route_path('login'));
    exit;
}

if (!empty($_SESSION['user_id']) && in_array($page, ['login', 'register'], true)) {
    header('Location: ' . route_path('dashboard'));
    exit;
}

if ($page === 'reminders' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['ajax'] ?? '') === '1')) {
    include $template;
    exit;
}

if ($page === 'invoices' && (($_GET['ajax'] ?? '') === '1')) {
    include $template;
    exit;
}

if ($page === 'invoice_pdf' || $page === 'expense_pdf') {
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo 'Inicia sesión para acceder al PDF';
        exit;
    }
    include $template;
    exit;
}

if ($page === 'logout') {
    include $template;
    exit;
}

$layout = in_array($page, ['home', 'login', 'register'], true)
    ? $root . '/templates/layout_public.php'
    : $root . '/templates/layout.php';

include $layout;
