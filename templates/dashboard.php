<?php
use Moni\Support\Config;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\ClientsRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Repositories\RemindersRepository;
use Moni\Services\InvoiceService;

$currentMonth = date('Y-m');
$currentYear = date('Y');
$today = new DateTime();

$allInvoices = InvoicesRepository::all();
$allClients = ClientsRepository::all();

// Pre-compute totals per invoice
$invoiceTotals = [];
foreach ($allInvoices as $inv) {
    $items = InvoiceItemsRepository::byInvoice((int)$inv['id']);
    $totals = InvoiceService::computeTotals($items);
    $invoiceTotals[(int)$inv['id']] = $totals;
}

// Monthly stats (issued/paid this month)
$thisMonthInvoices = array_filter($allInvoices, function($inv) use ($currentMonth) {
    $monthOk = substr((string)$inv['issue_date'], 0, 7) === $currentMonth;
    $isRevenue = in_array($inv['status'], ['issued','paid'], true);
    return $monthOk && $isRevenue;
});
$thisMonthTotal = array_sum(array_map(function($inv) use ($invoiceTotals) {
    return (float)($invoiceTotals[(int)$inv['id']]['total'] ?? 0);
}, $thisMonthInvoices));

// Status counts
$pendingInvoices = array_filter($allInvoices, fn($inv) => $inv['status'] === 'issued');
$paidInvoices = array_filter($allInvoices, fn($inv) => $inv['status'] === 'paid');
$overdue = array_filter($allInvoices, function($inv) use ($today) {
    return $inv['status'] === 'issued' && new DateTime($inv['due_date']) < $today;
});

// Recent activity (already ordered by created_at DESC in repo)
$recentInvoices = array_slice($allInvoices, 0, 5);

// Upcoming reminders (next occurrences, enabled)
$reminders = RemindersRepository::all();
$todayYmd = (new DateTime('today'));
$nextReminders = [];
foreach ($reminders as $r) {
    if (!($r['enabled'] ?? true)) continue;
    $eventDate = new DateTime((string)$r['event_date']);
    // build next occurrence (yearly or one-off)
    $rec = $r['recurring'] ?? 'yearly';
    $next = clone $eventDate;
    if ($rec === 'yearly') {
        $next->setDate((int)$todayYmd->format('Y'), (int)$eventDate->format('m'), (int)$eventDate->format('d'));
        if ($next < $todayYmd) { $next->modify('+1 year'); }
    }
    $nextReminders[] = [
        'title' => (string)$r['title'],
        'date' => $next,
    ];
}
usort($nextReminders, fn($a,$b) => $a['date'] <=> $b['date']);
$nextReminders = array_slice($nextReminders, 0, 3);
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
              <span style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($inv['invoice_number'] ?: '—') ?></span>
              <span style="color:var(--gray-500);font-size:0.85rem;margin-left:8px"><?= htmlspecialchars((new DateTime($inv['issue_date']))->format('d/m')) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-weight:600;color:var(--gray-800)"><?= number_format($invoiceTotals[(int)$inv['id']]['total'] ?? 0, 2) ?>€</span>
              <span class="status-badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  
  <div class="card">
    <h3 style="margin:0 0 12px 0;font-size:1rem;color:var(--gray-800)">Próximos avisos</h3>
    <?php if (empty($nextReminders)): ?>
      <p style="color:var(--gray-500);font-style:italic;margin:0">Sin avisos próximos</p>
    <?php else: ?>
      <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($nextReminders as $r): ?>
          <li style="display:flex;justify-content:space-between;gap:8px">
            <span style="color:var(--gray-800)"><?= htmlspecialchars($r['title']) ?></span>
            <span style="color:var(--gray-600)"><?= $r['date']->format('d/m/Y') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div style="display:flex;gap:6px;margin-top:12px">
      <a href="/?page=reminders" class="btn btn-sm btn-secondary">Gestionar avisos</a>
      <a href="/?page=invoices" class="btn btn-sm btn-secondary">Ver facturas</a>
    </div>
  </div>
</div>
