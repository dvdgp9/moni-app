<?php
use Moni\Support\Config;
$root = dirname(__DIR__);
$view = $template;
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=3">
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
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a href="/?page=profile" style="margin-left:auto">Perfil</a>
          <a href="/?page=logout">Salir</a>
        <?php else: ?>
          <a href="/?page=login" style="margin-left:auto">Entrar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container">
    <div class="main-container fade-in-up">
      <?php include $view; ?>
    </div>
  </main>
  <footer class="app-footer">
    <div class="container">© <?= date('Y') ?> Moni</div>
  </footer>
</body>
</html>
