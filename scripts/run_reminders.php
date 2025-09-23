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

// Ensure log directory exists
$logDir = $root . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/reminders.log';

$buffer = '';
try {
    $result = ReminderService::runForToday();

    $buffer .= 'Reminders run @ ' . date('c') . PHP_EOL;
    $buffer .= 'Sent: ' . count($result['sent']) . PHP_EOL;
    if (!empty($result['sent'])) {
        foreach ($result['sent'] as $t) {
            $buffer .= "  - $t" . PHP_EOL;
        }
    }
    $buffer .= 'Skipped (already sent): ' . count($result['skipped']) . PHP_EOL;
    $buffer .= 'Errors: ' . count($result['errors']) . PHP_EOL;
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $e) {
            $buffer .= "  - $e" . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    $buffer .= 'FATAL: ' . $e->getMessage() . PHP_EOL;
}

// Output to CLI and append to log file for cron visibility
echo $buffer;
@file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "]\n" . $buffer . "\n", FILE_APPEND);
