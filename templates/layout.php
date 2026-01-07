<?php
use Moni\Support\Config;
$root = dirname(__DIR__);
$view = $template;
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
// Brand assets autodiscovery
$brandDir = $root . '/public/assets/brand';
$logoPath = null;
foreach (['/logo.svg','/logo.png'] as $p) { if (file_exists($brandDir . $p)) { $logoPath = '/assets/brand' . $p; break; } }
$faviconSvg = file_exists($brandDir . '/favicon.svg') ? '/assets/brand/favicon.svg' : null;
$faviconPng = file_exists($brandDir . '/favicon.png') ? '/assets/brand/favicon.png' : null;
$faviconIco = file_exists($brandDir . '/favicon.ico') ? '/assets/brand/favicon.ico' : null;
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=3">
  <?php if ($faviconSvg): ?>
    <link rel="icon" type="image/svg+xml" href="<?= $faviconSvg ?>">
  <?php endif; ?>
  <?php if ($faviconPng): ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $faviconPng ?>">
  <?php endif; ?>
  <?php if ($faviconIco): ?>
    <link rel="icon" href="<?= $faviconIco ?>">
  <?php endif; ?>
</head>
<body>
  <header class="app-header">
    <div class="container">
      <a class="brand-link" href="/?page=dashboard">
        <?php if ($logoPath): ?>
          <img class="brand-logo" src="<?= $logoPath ?>" alt="<?= htmlspecialchars(Config::get('app_name', 'Moni')) ?>" />
        <?php else: ?>
          <span class="brand"><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></span>
        <?php endif; ?>
      </a>
      <nav class="nav">
        <a href="/?page=dashboard" class="<?= ($page==='dashboard')?'active':'' ?>">Dashboard</a>
        <a href="/?page=clients" class="<?= ($page==='clients'||$page==='client_form')?'active':'' ?>">Clientes</a>
        <a href="/?page=invoices" class="<?= ($page==='invoices'||$page==='invoice_form')?'active':'' ?>">Facturas</a>
        <a href="/?page=expenses" class="<?= ($page==='expenses'||$page==='expense_form')?'active':'' ?>">Gastos</a>
        <a href="/?page=declaraciones" class="<?= ($page==='declaraciones')?'active':'' ?>">Declaraciones</a>
        <a href="/?page=reminders" class="<?= ($page==='reminders')?'active':'' ?>">Notificaciones</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <!-- Penúltimo: Ajustes (icon settings-01) -->
          <a href="/?page=settings" title="Ajustes" class="<?= ($page==='settings')?'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
              <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M19.4 15.5c.18-.32.6-.46.94-.32l.26.1a2 2 0 0 0 2.68-1.88v-2a2 2 0 0 0-2.68-1.88l-.26.1c-.34.14-.76 0-.94-.32a7.7 7.7 0 0 0-1.34-1.34c-.32-.18-.46-.6-.32-.94l.1-.26A2 2 0 0 0 16.4 1h-2a2 2 0 0 0-1.88 2.68l.1.26c.14.34 0 .76-.32.94-.47.4-.93.87-1.34 1.34-.18.32-.6.46-.94.32l-.26-.1A2 2 0 0 0 3 7.6v2c0 .9.57 1.63 1.4 1.88l.26.1c.34.14.46.6.28.92-.18.46-.28.94-.28 1.5 0 .5.1 1.02.28 1.5.18.32.06.78-.28.92l-.26.1A2 2 0 0 0 3 20.4v2a2 2 0 0 0 2.68 1.88l.26-.1c.34-.14.76 0 .94.32.41.47.87.93 1.34 1.34.32.18.46.6.32.94l-.1.26A2 2 0 0 0 9.6 27h2a2 2 0 0 0 1.88-2.68l-.1-.26c-.14-.34 0-.76.32-.94.47-.41.93-.87 1.34-1.34.18-.32.6-.46.94-.32l.26.1A2 2 0 0 0 23 20.4v-2a2 2 0 0 0-2.68-1.88l-.26.1c-.34.14-.76 0-.94-.32-.41-.47-.87-.93-1.34-1.34Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
          <!-- Último a la derecha: Perfil (icon user-01) y Salir -->
          <a href="/?page=profile" style="margin-left:auto" title="Perfil" class="<?= ($page==='profile')?'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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
