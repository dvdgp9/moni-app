<?php
use Moni\Support\Config;
$root = dirname(__DIR__);
$view = $template;
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=2">
</head>
<body>
  <header class="app-header">
    <div class="container">
      <div class="brand"><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></div>
      <nav class="nav">
        <a href="/?page=dashboard">Dashboard</a>
        <a href="/?page=settings">Ajustes</a>
        <a href="/?page=clients">Clientes</a>
        <a href="/?page=invoices">Facturas</a>
        <a class="disabled" title="Próximamente">Declaraciones</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <?php include $view; ?>
  </main>
  <footer class="app-footer">
    <div class="container">© <?= date('Y') ?> Moni</div>
  </footer>
</body>
</html>
