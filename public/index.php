<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
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
