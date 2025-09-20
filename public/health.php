<?php
declare(strict_types=1);

$root = dirname(__DIR__);

// Autoload
$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Falta vendor/autoload.php. Ejecuta composer install en el servidor.';
    exit;
}
require $autoload;

use Dotenv\Dotenv;
use Moni\Database;
use Moni\Support\Config;

// Cargar .env
if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

require $root . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
echo "APP_ENV=" . ($_ENV['APP_ENV'] ?? 'unknown') . "\n";
echo "APP_DEBUG=" . (Config::get('debug') ? 'true' : 'false') . "\n";
echo "TIMEZONE=" . (Config::get('timezone') ?? 'n/a') . "\n";

// Probar PDO
try {
    $pdo = Database::pdo();
    $stmt = $pdo->query('SELECT 1');
    echo "DB: OK (SELECT 1 => " . $stmt->fetchColumn() . ")\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB: ERROR => " . $e->getMessage() . "\n";
}

// Salida de variables DB para verificación (sin contraseñas)
$dbCfg = Config::get('db');
echo "DB_HOST=" . $dbCfg['host'] . "; DB_PORT=" . $dbCfg['port'] . "; DB_NAME=" . $dbCfg['database'] . "; DB_USER=" . $dbCfg['username'] . "\n";
