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
$isLoggedIn = !empty($_SESSION['user_id']);
$mobileMorePages = ['clients', 'client_form', 'quotes', 'quote_form', 'suppliers', 'supplier_form', 'declaraciones', 'reminders', 'settings', 'profile'];
$mobileNav = [
  ['label' => 'Inicio', 'href' => route_path('dashboard'), 'active' => $page === 'dashboard', 'icon' => 'home'],
  ['label' => 'Facturas', 'href' => route_path('invoices'), 'active' => in_array($page, ['invoices', 'invoice_form'], true), 'icon' => 'invoice'],
  ['label' => 'Crear', 'href' => '#mobileCreateSheet', 'active' => false, 'button' => 'create', 'icon' => 'plus'],
  ['label' => 'Gastos', 'href' => route_path('expenses'), 'active' => in_array($page, ['expenses', 'expense_form'], true), 'icon' => 'expense'],
  ['label' => 'Mas', 'href' => '#mobileMoreSheet', 'active' => in_array($page, $mobileMorePages, true), 'button' => 'more', 'icon' => 'more'],
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
        <?php if ($isLoggedIn): ?>
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
  <?php if ($isLoggedIn): ?>
    <div class="mobile-sheet-overlay" data-mobile-close hidden></div>
    <nav class="mobile-bottom-nav" aria-label="Navegacion movil">
      <?php foreach ($mobileNav as $item): ?>
        <?php ob_start(); ?>
          <?php if ($item['icon'] === 'home'): ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.8 12 4l8 6.8V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1v-9.2Z"/></svg>
          <?php elseif ($item['icon'] === 'invoice'): ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v16l-3-1.6-2.7 1.6-2.6-1.6L8 21l-3-1.6V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
          <?php elseif ($item['icon'] === 'expense'): ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M7 13h.01M17 13h.01"/></svg>
          <?php elseif ($item['icon'] === 'more'): ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h.01M12 12h.01M19 12h.01"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          <?php endif; ?>
        <?php $mobileIcon = (string)ob_get_clean(); ?>
        <?php if (!empty($item['button'])): ?>
          <button
            type="button"
            class="mobile-nav-item mobile-nav-<?= htmlspecialchars($item['button']) ?> <?= $item['active'] ? 'active' : '' ?>"
            data-mobile-sheet="<?= htmlspecialchars($item['button']) ?>"
            aria-expanded="false"
            aria-controls="mobile<?= ucfirst((string)$item['button']) ?>Sheet"
          >
            <span class="mobile-nav-icon" aria-hidden="true"><?= $mobileIcon ?></span>
            <span><?= htmlspecialchars($item['label']) ?></span>
          </button>
        <?php else: ?>
          <a class="mobile-nav-item <?= $item['active'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
            <span class="mobile-nav-icon" aria-hidden="true"><?= $mobileIcon ?></span>
            <span><?= htmlspecialchars($item['label']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <section id="mobileCreateSheet" class="mobile-sheet" aria-label="Crear" hidden>
      <div class="mobile-sheet-handle" aria-hidden="true"></div>
      <div class="mobile-sheet-head">
        <strong>Crear rapido</strong>
        <button type="button" class="mobile-sheet-close" data-mobile-close aria-label="Cerrar">×</button>
      </div>
      <div class="mobile-sheet-grid mobile-create-grid">
        <a href="<?= route_path('invoice_form') ?>"><span>Factura</span><small>Cobrar trabajo</small></a>
        <a href="<?= route_path('quote_form') ?>"><span>Presupuesto</span><small>Enviar oferta</small></a>
        <a href="<?= route_path('expense_form') ?>"><span>Gasto</span><small>Registrar compra</small></a>
        <a href="<?= route_path('client_form') ?>"><span>Cliente</span><small>Nuevo contacto</small></a>
      </div>
    </section>

    <section id="mobileMoreSheet" class="mobile-sheet" aria-label="Mas opciones" hidden>
      <div class="mobile-sheet-handle" aria-hidden="true"></div>
      <div class="mobile-sheet-head">
        <strong>Mas opciones</strong>
        <button type="button" class="mobile-sheet-close" data-mobile-close aria-label="Cerrar">×</button>
      </div>
      <div class="mobile-more-groups">
        <div class="mobile-more-group">
          <span class="mobile-more-kicker">Ventas</span>
          <div class="mobile-more-list">
            <a href="<?= route_path('clients') ?>" class="<?= in_array($page, ['clients', 'client_form'], true) ? 'active' : '' ?>"><span>Clientes</span><small>Agenda comercial</small></a>
            <a href="<?= route_path('quotes') ?>" class="<?= in_array($page, ['quotes', 'quote_form'], true) ? 'active' : '' ?>"><span>Presupuestos</span><small>Ofertas y conversiones</small></a>
          </div>
        </div>
        <div class="mobile-more-group">
          <span class="mobile-more-kicker">Compras</span>
          <div class="mobile-more-list">
            <a href="<?= route_path('suppliers') ?>" class="<?= in_array($page, ['suppliers', 'supplier_form'], true) ? 'active' : '' ?>"><span>Proveedores</span><small>Contactos y gastos</small></a>
          </div>
        </div>
        <div class="mobile-more-group">
          <span class="mobile-more-kicker">Fiscal</span>
          <div class="mobile-more-list">
            <a href="<?= route_path('declaraciones') ?>" class="<?= $page === 'declaraciones' ? 'active' : '' ?>"><span>Declaraciones</span><small>Trimestre e impuestos</small></a>
            <a href="<?= route_path('reminders') ?>" class="<?= $page === 'reminders' ? 'active' : '' ?>"><span>Notificaciones</span><small>Avisos importantes</small></a>
          </div>
        </div>
        <div class="mobile-more-group">
          <span class="mobile-more-kicker">Cuenta</span>
          <div class="mobile-more-list">
            <a href="<?= route_path('settings') ?>" class="<?= $page === 'settings' ? 'active' : '' ?>"><span>Ajustes</span><small>Preferencias</small></a>
            <a href="<?= route_path('profile') ?>" class="<?= $page === 'profile' ? 'active' : '' ?>"><span>Perfil</span><small>Datos fiscales</small></a>
            <a href="<?= route_path('logout') ?>" class="mobile-more-logout"><span>Salir</span><small>Cerrar sesion</small></a>
          </div>
        </div>
      </div>
    </section>

    <script>
    (function () {
      const overlay = document.querySelector('.mobile-sheet-overlay');
      const triggers = document.querySelectorAll('[data-mobile-sheet]');
      const closeEls = document.querySelectorAll('[data-mobile-close]');
      const sheets = {
        create: document.getElementById('mobileCreateSheet'),
        more: document.getElementById('mobileMoreSheet')
      };

      function closeSheets() {
        Object.values(sheets).forEach(function (sheet) {
          if (sheet) sheet.hidden = true;
        });
        triggers.forEach(function (trigger) {
          trigger.setAttribute('aria-expanded', 'false');
        });
        if (overlay) overlay.hidden = true;
        document.body.classList.remove('mobile-sheet-open');
      }

      function openSheet(name, trigger) {
        const sheet = sheets[name];
        if (!sheet) return;
        closeSheets();
        sheet.hidden = false;
        if (overlay) overlay.hidden = false;
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('mobile-sheet-open');
      }

      triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
          const name = trigger.getAttribute('data-mobile-sheet');
          const expanded = trigger.getAttribute('aria-expanded') === 'true';
          if (expanded) {
            closeSheets();
          } else {
            openSheet(name, trigger);
          }
        });
      });

      closeEls.forEach(function (el) {
        el.addEventListener('click', closeSheets);
      });

      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeSheets();
      });
    })();
    </script>
  <?php endif; ?>
</body>
</html>
