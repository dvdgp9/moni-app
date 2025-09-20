<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Moni\Support\Config;

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

require $root . '/src/bootstrap.php';

// Placeholder: en siguientes pasos leeremos settings y reminder rules desde BD.
$today = new DateTime('today');
$year = (int)$today->format('Y');
$openings = ["$year-01-01", "$year-04-01", "$year-07-01", "$year-10-01"];

if (in_array($today->format('Y-m-d'), $openings, true)) {
    // TODO: consultar destinatario(s) y registrar en reminder_logs para idempotencia
    echo "Hoy es apertura de trimestre: " . $today->format('Y-m-d') . PHP_EOL;
}
