<?php
use Moni\Support\Config;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\ClientsRepository;

$currentMonth = date('Y-m');
$currentYear = date('Y');
$today = new DateTime();

$allInvoices = InvoicesRepository::all();
$allClients = ClientsRepository::all();

// Monthly stats
$thisMonthInvoices = array_filter($allInvoices, fn($inv) => substr($inv['issue_date'], 0, 7) === $currentMonth);
$thisMonthTotal = array_sum(array_map(fn($inv) => (float)$inv['total'], $thisMonthInvoices));

// Status counts
$pendingInvoices = array_filter($allInvoices, fn($inv) => $inv['status'] === 'issued');
$paidInvoices = array_filter($allInvoices, fn($inv) => $inv['status'] === 'paid');
$overdue = array_filter($allInvoices, function($inv) use ($today) {
    return $inv['status'] === 'issued' && new DateTime($inv['due_date']) < $today;
});

// Recent activity
$recentInvoices = array_slice(array_reverse($allInvoices), 0, 5);

// Next quarter deadline
$quarters = [
    1 => '2025-04-30', 2 => '2025-07-30', 3 => '2025-10-30', 4 => '2025-01-30'
];
$currentQ = (int)ceil(date('n') / 3);
$nextDeadline = $quarters[$currentQ] ?? $quarters[1];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <h1 style="margin:0">Dashboard</h1>
  <div style="display:flex;gap:8px">
    <a href="/?page=invoice_form" class="btn btn-sm">+ Factura</a>
    <a href="/?page=client_form" class="btn btn-secondary btn-sm">+ Cliente</a>
  </div>
</div>

<div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.5rem">
  <div class="stat-card">
    <div class="stat-value" style="color:var(--primary)"><?= number_format($thisMonthTotal, 0) ?>€</div>
    <div class="stat-label">Este mes</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--accent)"><?= count($pendingInvoices) ?></div>
    <div class="stat-label">Pendientes</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:<?= count($overdue) > 0 ? 'var(--danger)' : 'var(--gray-600)' ?>"><?= count($overdue) ?></div>
    <div class="stat-label">Vencidas</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--gray-600)"><?= count($allClients) ?></div>
    <div class="stat-label">Clientes</div>
  </div>
</div>

<div class="grid-2" style="gap:12px">
  <div class="card">
    <h3 style="margin:0 0 12px 0;font-size:1rem;color:var(--gray-800)">Actividad reciente</h3>
    <?php if (empty($recentInvoices)): ?>
      <p style="color:var(--gray-500);font-style:italic;margin:0">No hay facturas aún</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach ($recentInvoices as $inv): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--gray-100)">
            <div>
              <span style="font-weight:500;color:var(--gray-800)"><?= htmlspecialchars($inv['number']) ?></span>
              <span style="color:var(--gray-500);font-size:0.85rem;margin-left:8px"><?= htmlspecialchars((new DateTime($inv['issue_date']))->format('d/m')) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-weight:600;color:var(--gray-800)"><?= number_format($inv['total'], 0) ?>€</span>
              <span class="status-badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  
  <div class="card">
    <h3 style="margin:0 0 12px 0;font-size:1rem;color:var(--gray-800)">Próximo trimestre</h3>
    <div style="padding:12px;background:var(--gray-50);border-radius:8px;margin-bottom:12px">
      <div style="font-weight:600;color:var(--gray-800)">Q<?= $currentQ ?> <?= date('Y') ?></div>
      <div style="color:var(--gray-600);font-size:0.9rem">Vence: <?= (new DateTime($nextDeadline))->format('d/m/Y') ?></div>
    </div>
    <div style="display:flex;gap:6px">
      <a href="/?page=reminders" class="btn btn-sm btn-secondary">Notificaciones</a>
      <a href="/?page=invoices" class="btn btn-sm btn-secondary">Ver facturas</a>
    </div>
  </div>
</div>
