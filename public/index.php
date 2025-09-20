<?php
declare(strict_types=1);

$root = dirname(__DIR__);

// Ensure Composer autoload exists
$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Falta vendor/autoload.php. Instala dependencias con Composer (composer install).';
    exit;
}
require $autoload;

use Dotenv\Dotenv;

// Load .env if present
try {
    if (file_exists($root . '/.env')) {
        $dotenv = Dotenv::createImmutable($root);
        $dotenv->safeLoad();
    }
} catch (Throwable $e) {
    // If dotenv fails very early, show basic hint
    http_response_code(500);
    echo 'Error cargando .env: ' . htmlspecialchars($e->getMessage());
    exit;
}

$timezone = $_ENV['TIMEZONE'] ?? 'Europe/Madrid';
@date_default_timezone_set($timezone);

require_once $root . '/src/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';

$routes = [
    'dashboard' => $root . '/templates/dashboard.php',
    'settings'  => $root . '/templates/settings.php',
];

$template = $routes[$page] ?? $routes['dashboard'];

include $root . '/templates/layout.php';
