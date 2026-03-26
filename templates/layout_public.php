<?php
use Moni\Support\Config;

$root = dirname(__DIR__);
$view = $template;
$brandDir = $root . '/public/assets/brand';
$logoPath = null;
foreach (['/logo.svg', '/logo.png'] as $candidate) {
    if (file_exists($brandDir . $candidate)) {
        $logoPath = '/assets/brand' . $candidate;
        break;
    }
}
$faviconPng = file_exists($brandDir . '/favicon.png') ? '/assets/brand/favicon.png' : null;
$faviconIco = file_exists($brandDir . '/favicon.ico') ? '/assets/brand/favicon.ico' : null;
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=4" />
  <?php if ($faviconPng): ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $faviconPng ?>" />
  <?php endif; ?>
  <?php if ($faviconIco): ?>
    <link rel="icon" href="<?= $faviconIco ?>" />
  <?php endif; ?>
</head>
<body class="public-body<?= $page === 'home' ? ' public-home-body' : '' ?>">
  <header class="public-header">
    <div class="container public-header-inner">
      <a class="brand-link" href="<?= route_path('home') ?>">
        <?php if ($logoPath): ?>
          <img class="brand-logo" src="<?= $logoPath ?>" alt="<?= htmlspecialchars(Config::get('app_name', 'Moni')) ?>" />
        <?php else: ?>
          <span class="brand"><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></span>
        <?php endif; ?>
      </a>
      <nav class="public-nav">
        <a href="<?= route_path('home') ?>#funciones">Funciones</a>
        <a href="<?= route_path('home') ?>#beneficios">Beneficios</a>
        <a href="<?= route_path('home') ?>#precios">Precios</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a class="btn btn-secondary btn-sm" href="<?= route_path('dashboard') ?>">Ir a la app</a>
        <?php else: ?>
          <a href="<?= route_path('login') ?>">Entrar</a>
          <a class="btn btn-sm" href="<?= route_path('register') ?>">Crear cuenta gratis</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="public-main<?= $page === 'home' ? ' public-main-home' : '' ?>">
    <?php include $view; ?>
  </main>

  <footer class="public-footer">
    <div class="container public-footer-inner">
      <div>
        <strong><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></strong>
        <p>Herramienta sencilla para autónomos que quieren facturas, gastos y recordatorios en orden.</p>
      </div>
      <div class="public-footer-links">
        <a href="<?= route_path('login') ?>">Acceder</a>
        <a href="<?= route_path('register') ?>">Crear cuenta</a>
        <a href="<?= route_path('home') ?>#precios">Beta gratuita</a>
      </div>
    </div>
  </footer>
</body>
</html>
