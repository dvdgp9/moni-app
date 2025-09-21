<?php
use Moni\Support\Config;
use Moni\Repositories\InvoicesRepository;

// Get current month stats
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Simple stats for now - can be enhanced later
$allInvoices = InvoicesRepository::all();
$thisMonthInvoices = array_filter($allInvoices, fn($inv) => substr($inv['issue_date'], 0, 7) === $currentMonth);
$issuedInvoices = array_filter($allInvoices, fn($inv) => in_array($inv['status'], ['issued', 'paid']));
$paidInvoices = array_filter($allInvoices, fn($inv) => $inv['status'] === 'paid');

$totalInvoices = count($allInvoices);
$monthlyInvoices = count($thisMonthInvoices);
$pendingInvoices = count(array_filter($allInvoices, fn($inv) => $inv['status'] === 'issued'));

$today = new DateTime('today');
$year = (int)$today->format('Y');
$events = [
    ["title" => "Inicio trimestre Q1", "date" => "$year-01-01"],
    ["title" => "Inicio trimestre Q2", "date" => "$year-04-01"],
    ["title" => "Inicio trimestre Q3", "date" => "$year-07-01"],
    ["title" => "Inicio trimestre Q4", "date" => "$year-10-01"],
];
?>

<h1>Dashboard</h1>

<div class="dashboard-grid">
  <div class="stat-card">
    <div class="stat-value"><?= $totalInvoices ?></div>
    <div class="stat-label">Facturas Totales</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-value"><?= $monthlyInvoices ?></div>
    <div class="stat-label">Este Mes</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-value"><?= $pendingInvoices ?></div>
    <div class="stat-label">Pendientes de Cobro</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-value"><?= count($paidInvoices) ?></div>
    <div class="stat-label">Pagadas</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>Acciones Rápidas</h3>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem">
      <a href="/?page=invoice_form" class="btn">+ Nueva Factura</a>
      <a href="/?page=client_form" class="btn btn-secondary">+ Nuevo Cliente</a>
      <a href="/?page=invoices" class="btn btn-secondary">Ver Facturas</a>
    </div>
  </div>
  
  <div class="card">
    <h3>Próximos Eventos</h3>
    <ul style="margin:0;padding-left:1.5rem;color:var(--gray-600)">
      <?php foreach ($events as $e): ?>
        <li style="margin-bottom:0.5rem">
          <strong><?= htmlspecialchars($e['title']) ?>:</strong>
          <span><?= htmlspecialchars((new DateTime($e['date']))->format('d/m/Y')) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="card">
  <h3>Bienvenido a <?= htmlspecialchars(Config::get('app_name', 'Moni')) ?></h3>
  <p style="color:var(--gray-600);margin-bottom:1rem">Tu asistente de gestión financiera para autónomos.</p>
  <ul style="color:var(--gray-600);margin:0;padding-left:1.5rem">
    <li>Gestiona clientes y facturas</li>
    <li>Genera PDFs profesionales</li>
    <li>Controla pagos y vencimientos</li>
    <li>Recordatorios automáticos</li>
  </ul>
</div>
