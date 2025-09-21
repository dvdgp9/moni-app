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
        <a href="/?page=clients">Clientes</a>
        <a href="/?page=invoices">Facturas</a>
        <a href="/?page=reminders">Notificaciones</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <!-- Penúltimo: Ajustes (icon settings-01) -->
          <a href="/?page=settings" title="Ajustes">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
              <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Perfil
          </a>
          <a href="/?page=logout">Salir</a>
        <?php else: ?>
          <!-- Invitado: último a la derecha "Entrar" -->
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
