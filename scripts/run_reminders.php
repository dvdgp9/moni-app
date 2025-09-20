<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Moni\Support\Config;
use Moni\Services\ReminderService;

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

require $root . '/src/bootstrap.php';

$result = ReminderService::runForToday();

echo 'Reminders run @ ' . date('c') . PHP_EOL;
echo 'Sent: ' . count($result['sent']) . PHP_EOL;
if (!empty($result['sent'])) {
    foreach ($result['sent'] as $t) {
        echo "  - $t" . PHP_EOL;
    }
}
echo 'Skipped (already sent): ' . count($result['skipped']) . PHP_EOL;
echo 'Errors: ' . count($result['errors']) . PHP_EOL;
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $e) {
        echo "  - $e" . PHP_EOL;
    }
}
