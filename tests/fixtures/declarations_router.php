<?php
/** Isolated HTTP fixture: real entrypoint, layout and tax services, no production database. */
declare(strict_types=1);

namespace Moni\Repositories {
    final class SettingsRepository
    {
        public static function get(string $key, ?int $userId = null): ?string
        {
            return $_SESSION['test_settings'][$key] ?? null;
        }
        public static function set(string $key, ?string $value, ?int $userId = null): void
        {
            $_SESSION['test_settings'][$key] = $value;
        }
        public static function all(?int $userId = null): array
        {
            return $_SESSION['test_settings'] ?? [];
        }
    }
}

namespace {
    if (PHP_SAPI !== 'cli-server' || getenv('MONI_HTTP_TEST') !== '1') {
        http_response_code(404);
        exit;
    }
    $root = dirname(__DIR__, 2);
    if (str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/assets/')) {
        return false;
    }
    require $root . '/vendor/autoload.php';
    session_start();
    $_SESSION['user_id'] = 42;
    $_SESSION['test_settings'] ??= ['tax_models' => '["303","130","390"]'];
    // Let the real entrypoint configure and reopen the session.
    session_write_close();
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users(id INTEGER,name TEXT,nif TEXT);
        CREATE TABLE invoices(id INTEGER,user_id INTEGER,status TEXT,issue_date TEXT);
        CREATE TABLE invoice_items(invoice_id INTEGER,quantity REAL,unit_price REAL,vat_rate REAL,irpf_rate REAL);
        CREATE TABLE expenses(id INTEGER,user_id INTEGER,invoice_date TEXT,base_amount REAL,vat_rate REAL,vat_amount REAL,status TEXT,category TEXT,supplier_id INTEGER);');
    $pdo->exec("INSERT INTO users VALUES (42,'Perfil de prueba','');
        INSERT INTO invoices VALUES (1,42,'issued','2026-09-20');
        INSERT INTO invoice_items VALUES (1,1,1995,21,15);");
    (new \ReflectionProperty(\Moni\Database::class, 'pdo'))->setValue(null, $pdo);
    $_ENV['APP_URL'] = 'http://' . $_SERVER['HTTP_HOST'];
    $_ENV['FORCE_HTTPS'] = 'false';
    require $root . '/public/index.php';
}
