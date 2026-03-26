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

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
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
    'expenses', 'expense_form',
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

if ($page === 'invoice_pdf') {
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
