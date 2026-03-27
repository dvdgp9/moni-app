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
$navGroups = [
  [
    'label' => 'Ventas',
    'items' => [
      ['page' => ['clients', 'client_form'], 'href' => route_path('clients'), 'label' => 'Clientes'],
      ['page' => ['invoices', 'invoice_form'], 'href' => route_path('invoices'), 'label' => 'Facturas'],
      ['page' => ['quotes', 'quote_form'], 'href' => route_path('quotes'), 'label' => 'Presupuestos'],
    ],
  ],
  [
    'label' => 'Compras',
    'items' => [
      ['page' => ['expenses', 'expense_form'], 'href' => route_path('expenses'), 'label' => 'Gastos'],
      ['page' => ['suppliers', 'supplier_form'], 'href' => route_path('suppliers'), 'label' => 'Proveedores'],
    ],
  ],
  [
    'label' => 'Fiscal',
    'items' => [
      ['page' => ['declaraciones'], 'href' => route_path('declaraciones'), 'label' => 'Declaraciones'],
      ['page' => ['reminders'], 'href' => route_path('reminders'), 'label' => 'Notificaciones'],
    ],
  ],
];
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=4">
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
      <a class="brand-link" href="<?= route_path('dashboard') ?>">
        <?php if ($logoPath): ?>
          <img class="brand-logo" src="<?= $logoPath ?>" alt="<?= htmlspecialchars(Config::get('app_name', 'Moni')) ?>" />
        <?php else: ?>
          <span class="brand"><?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></span>
        <?php endif; ?>
      </a>
      <nav class="nav">
        <a href="<?= route_path('dashboard') ?>" class="<?= ($page==='dashboard')?'active':'' ?>">Dashboard</a>
        <?php foreach ($navGroups as $group): ?>
          <?php $isGroupActive = false; ?>
          <?php foreach ($group['items'] as $item): ?>
            <?php if (in_array($page, $item['page'], true)) { $isGroupActive = true; break; } ?>
          <?php endforeach; ?>
          <div class="nav-dropdown <?= $isGroupActive ? 'active' : '' ?>">
            <button type="button" class="nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
              <span><?= htmlspecialchars($group['label']) ?></span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <div class="nav-dropdown-menu">
              <?php foreach ($group['items'] as $item): ?>
                <a href="<?= $item['href'] ?>" class="<?= in_array($page, $item['page'], true) ? 'active' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <span class="nav-spacer"></span>
          <!-- Penúltimo: Ajustes (icon settings-01) -->
          <a href="<?= route_path('settings') ?>" title="Ajustes" class="<?= ($page==='settings')?'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
              <path d="M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M19.14 12.94a7.99 7.99 0 0 0 .05-.94c0-.32-.02-.63-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.28 7.28 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 13.9 2h-3.8a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.12.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.71 8.48a.5.5 0 0 0 .12.64l2.03 1.58c-.03.31-.05.62-.05.94s.02.63.05.94l-2.03 1.58a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.72 1.63.94l.36 2.54a.5.5 0 0 0 .49.42h3.8a.5.5 0 0 0 .49-.42l.36-2.54c.58-.23 1.12-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
          <!-- Último a la derecha: Perfil (icon user-01) y Salir -->
          <a href="<?= route_path('profile') ?>" title="Perfil" class="<?= ($page==='profile')?'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
          <a href="<?= route_path('logout') ?>">Salir</a>
        <?php else: ?>
          <!-- Invitado: último a la derecha "Entrar" -->
          <span class="nav-spacer"></span>
          <a href="<?= route_path('login') ?>">Entrar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container">
    <div class="main-container<?= $page === 'invoices' ? '' : ' fade-in-up' ?>">
      <?php include $view; ?>
    </div>
  </main>
  <footer class="app-footer">
    <div class="container">© <?= date('Y') ?> Moni</div>
  </footer>
</body>
</html>
