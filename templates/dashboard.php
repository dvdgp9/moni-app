<?php
use Moni\Repositories\ClientsRepository;
use Moni\Repositories\ExpensesRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\QuotesRepository;
use Moni\Repositories\RemindersRepository;
use Moni\Repositories\SuppliersRepository;
use Moni\Services\InvoiceService;
use Moni\Services\OnboardingService;
use Moni\Services\TaxQuarterService;

$today = new DateTime();
$todayYmd = $today->format('Y-m-d');
$currentMonth = $today->format('Y-m');
$currentYear = (int)$today->format('Y');
$currentQuarter = (int)ceil(((int)$today->format('n')) / 3);
$quarterRange = TaxQuarterService::quarterRange($currentYear, $currentQuarter);

$allInvoices = InvoicesRepository::all();
$allClients = ClientsRepository::all();
$allQuotes = QuotesRepository::all();
$allExpenses = ExpensesRepository::all();
$allSuppliers = SuppliersRepository::all();
$reminders = RemindersRepository::all();

$invoiceTotals = [];
foreach ($allInvoices as $invoice) {
    $items = InvoiceItemsRepository::byInvoice((int)$invoice['id']);
    $invoiceTotals[(int)$invoice['id']] = InvoiceService::computeTotals($items);
}

$issuedInvoices = array_values(array_filter($allInvoices, static fn(array $invoice): bool => in_array((string)$invoice['status'], ['issued', 'paid'], true)));
$pendingInvoices = array_values(array_filter($allInvoices, static fn(array $invoice): bool => (string)$invoice['status'] === 'issued'));
$draftInvoices = array_values(array_filter($allInvoices, static fn(array $invoice): bool => (string)$invoice['status'] === 'draft'));
$overdueInvoices = array_values(array_filter($pendingInvoices, static function(array $invoice) use ($todayYmd): bool {
    return !empty($invoice['due_date']) && (string)$invoice['due_date'] < $todayYmd;
}));
$paidInvoices = array_values(array_filter($allInvoices, static fn(array $invoice): bool => (string)$invoice['status'] === 'paid'));

$revenueMonth = 0.0;
$collectedMonth = 0.0;
$pendingCash = 0.0;
$overdueCash = 0.0;
foreach ($issuedInvoices as $invoice) {
    $total = (float)($invoiceTotals[(int)$invoice['id']]['total'] ?? 0);
    if (substr((string)$invoice['issue_date'], 0, 7) === $currentMonth) {
        $revenueMonth += $total;
    }
    if ((string)$invoice['status'] === 'paid' && substr((string)$invoice['issue_date'], 0, 7) === $currentMonth) {
        $collectedMonth += $total;
    }
    if ((string)$invoice['status'] === 'issued') {
        $pendingCash += $total;
        if (!empty($invoice['due_date']) && (string)$invoice['due_date'] < $todayYmd) {
            $overdueCash += $total;
        }
    }
}

$quotesDraft = array_values(array_filter($allQuotes, static fn(array $quote): bool => (string)$quote['status'] === 'draft'));
$quotesSent = array_values(array_filter($allQuotes, static fn(array $quote): bool => (string)$quote['status'] === 'sent'));
$quotesAccepted = array_values(array_filter($allQuotes, static fn(array $quote): bool => (string)$quote['status'] === 'accepted'));
$quotesPendingResponse = array_values(array_filter($quotesSent, static function(array $quote) use ($todayYmd): bool {
    return empty($quote['valid_until']) || (string)$quote['valid_until'] >= $todayYmd;
}));
$quotesExpiringSoon = array_values(array_filter($quotesPendingResponse, static function(array $quote) use ($today): bool {
    if (empty($quote['valid_until'])) {
        return false;
    }
    $validUntil = DateTime::createFromFormat('Y-m-d', (string)$quote['valid_until']);
    if (!$validUntil) {
        return false;
    }
    $diff = (int)$today->diff($validUntil)->format('%r%a');
    return $diff >= 0 && $diff <= 7;
}));

$expensesMonth = array_values(array_filter($allExpenses, static fn(array $expense): bool => substr((string)$expense['invoice_date'], 0, 7) === $currentMonth));
$expensesPending = array_values(array_filter($allExpenses, static fn(array $expense): bool => (string)$expense['status'] === 'pending'));
$expensesWithoutSupplier = array_values(array_filter($allExpenses, static fn(array $expense): bool => empty($expense['supplier_id'])));
$expensesOtherCategory = array_values(array_filter($allExpenses, static fn(array $expense): bool => (string)$expense['category'] === 'otros'));
$expensesTotalMonth = array_sum(array_map(static fn(array $expense): float => (float)($expense['total_amount'] ?? 0), $expensesMonth));

$quarterTax = TaxQuarterService::summarizeSales($currentYear, $currentQuarter);
$quarterExpenses = TaxQuarterService::summarizeExpenses($currentYear, $currentQuarter);
$quarterChecklist = TaxQuarterService::quarterChecklist($currentYear, $currentQuarter);
$quarterVatDue = (float)$quarterTax['iva_total'] - (float)$quarterExpenses['vat_total'];
$quarterYtd = TaxQuarterService::summarizeSalesYTD($currentYear, $currentQuarter);
$quarterExpensesYtd = TaxQuarterService::summarizeExpensesYTD($currentYear, $currentQuarter);
$quarterIrpfEstimateBase = (float)$quarterYtd['base_total_ytd'] - (float)$quarterExpensesYtd['base_total_ytd'];
$quarterIrpfEstimate = $quarterIrpfEstimateBase > 0 ? round($quarterIrpfEstimateBase * 0.20, 2) : 0.0;

$recentInvoices = $allInvoices;
usort($recentInvoices, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$recentInvoices = array_slice($recentInvoices, 0, 5);

$urgentCollection = $pendingInvoices;
usort($urgentCollection, static function(array $a, array $b): int {
    $dueA = (string)($a['due_date'] ?? '9999-12-31');
    $dueB = (string)($b['due_date'] ?? '9999-12-31');
    return strcmp($dueA, $dueB);
});
$urgentCollection = array_slice($urgentCollection, 0, 4);

$recentExpenses = $allExpenses;
usort($recentExpenses, static function(array $a, array $b): int {
    $dateCmp = strcmp((string)$b['invoice_date'], (string)$a['invoice_date']);
    if ($dateCmp !== 0) {
        return $dateCmp;
    }
    return ((int)$b['id']) <=> ((int)$a['id']);
});
$recentExpenses = array_slice($recentExpenses, 0, 4);

$upcomingReminders = [];
$todayOnly = new DateTime('today');
foreach ($reminders as $reminder) {
    if (!($reminder['enabled'] ?? true)) {
        continue;
    }
    $eventDate = DateTime::createFromFormat('Y-m-d', (string)$reminder['event_date']);
    if (!$eventDate) {
        continue;
    }
    $next = clone $eventDate;
    if (($reminder['recurring'] ?? 'yearly') === 'yearly') {
        $next->setDate((int)$todayOnly->format('Y'), (int)$eventDate->format('m'), (int)$eventDate->format('d'));
        if ($next < $todayOnly) {
            $next->modify('+1 year');
        }
    }
    $upcomingReminders[] = [
        'title' => (string)$reminder['title'],
        'date' => $next,
    ];
}
usort($upcomingReminders, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
$upcomingReminders = array_slice($upcomingReminders, 0, 4);
$onboardingState = OnboardingService::getState((int)($_SESSION['user_id'] ?? 0));
$showOnboardingCard = empty($onboardingState['completed_at']);

$focusItems = [];
if (count($overdueInvoices) > 0) {
    $focusItems[] = ['tone' => 'danger', 'title' => 'Cobros vencidos', 'text' => count($overdueInvoices) . ' facturas emitidas estan fuera de plazo.'];
}
if (count($quotesExpiringSoon) > 0) {
    $focusItems[] = ['tone' => 'warning', 'title' => 'Presupuestos por cerrar', 'text' => count($quotesExpiringSoon) . ' presupuestos vencen en los proximos 7 dias.'];
}
if ($quarterChecklist['pending_expenses'] > 0 || $quarterChecklist['unlinked_suppliers'] > 0) {
    $focusItems[] = ['tone' => 'info', 'title' => 'Compras por revisar', 'text' => $quarterChecklist['pending_expenses'] . ' gastos pendientes y ' . $quarterChecklist['unlinked_suppliers'] . ' sin proveedor vinculado.'];
}
if (empty($focusItems)) {
    $focusItems[] = ['tone' => 'success', 'title' => 'Panel al dia', 'text' => 'No hay alertas urgentes ahora mismo. Puedes centrarte en vender o revisar el trimestre.'];
}

function dashboard_money(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' EUR';
}

function dashboard_date(?string $date): string
{
    if (!$date) {
        return 'Sin fecha';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
?>

<div class="dashboard-shell">
  <section class="dashboard-hero card">
    <div class="dashboard-hero-copy">
      <span class="dashboard-eyebrow">Centro de mando</span>
      <h1>Dashboard</h1>
      <p>
        Una vista rapida de cobros, ventas, compras y presion fiscal para saber que mover hoy sin entrar en cinco pantallas.
      </p>
      <div class="dashboard-hero-actions">
        <a href="<?= route_path('invoice_form') ?>" class="btn btn-sm">+ Factura</a>
        <a href="<?= route_path('quote_form') ?>" class="btn btn-secondary btn-sm">+ Presupuesto</a>
        <a href="<?= route_path('expense_form') ?>" class="btn btn-secondary btn-sm">+ Gasto</a>
      </div>
    </div>
    <div class="dashboard-hero-summary">
      <div class="dashboard-hero-metric">
        <span>Caja pendiente de cobrar</span>
        <strong><?= dashboard_money($pendingCash) ?></strong>
      </div>
      <div class="dashboard-hero-metric">
        <span>Facturacion del mes</span>
        <strong><?= dashboard_money($revenueMonth) ?></strong>
      </div>
      <div class="dashboard-hero-metric">
        <span>Gasto del mes</span>
        <strong><?= dashboard_money($expensesTotalMonth) ?></strong>
      </div>
    </div>
  </section>

  <section class="dashboard-focus-grid">
    <?php foreach ($focusItems as $item): ?>
      <article class="dashboard-focus-card dashboard-focus-<?= htmlspecialchars($item['tone']) ?>">
        <strong><?= htmlspecialchars($item['title']) ?></strong>
        <p><?= htmlspecialchars($item['text']) ?></p>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($showOnboardingCard): ?>
    <section class="card dashboard-onboarding-card">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Onboarding</span>
          <h3>Tu cuenta está al <?= (int)$onboardingState['progress'] ?>%</h3>
        </div>
        <a href="<?= route_path('onboarding', ['resume' => 1, 'step' => (int)$onboardingState['step']]) ?>" class="btn btn-sm">Continuar configuración</a>
      </div>
      <div class="dashboard-mini-grid">
        <?php foreach ($onboardingState['sections'] as $section): ?>
          <div class="dashboard-mini-stat">
            <span><?= htmlspecialchars($section['label']) ?></span>
            <strong><?= $section['complete'] ? 'OK' : 'Pendiente' ?></strong>
            <small style="color:var(--gray-600)"><?= htmlspecialchars($section['hint']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="dashboard-kpi-grid">
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">Facturado este mes</span>
      <strong><?= dashboard_money($revenueMonth) ?></strong>
      <small><?= count(array_filter($issuedInvoices, static fn(array $invoice): bool => substr((string)$invoice['issue_date'], 0, 7) === date('Y-m'))) ?> facturas emitidas o cobradas</small>
    </article>
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">Pendiente de cobro</span>
      <strong><?= dashboard_money($pendingCash) ?></strong>
      <small><?= count($pendingInvoices) ?> facturas siguen abiertas</small>
    </article>
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">Presupuestos esperando respuesta</span>
      <strong><?= count($quotesPendingResponse) ?></strong>
      <small><?= count($quotesExpiringSoon) ?> vencen pronto</small>
    </article>
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">Gastos pendientes</span>
      <strong><?= count($expensesPending) ?></strong>
      <small><?= count($expensesWithoutSupplier) ?> sin proveedor vinculado</small>
    </article>
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">IVA estimado trimestre</span>
      <strong><?= dashboard_money($quarterVatDue) ?></strong>
      <small><?= dashboard_date($quarterRange['start']) ?> - <?= dashboard_date($quarterRange['end']) ?></small>
    </article>
    <article class="dashboard-kpi-card">
      <span class="dashboard-kpi-label">IRPF estimado acumulado</span>
      <strong><?= dashboard_money($quarterIrpfEstimate) ?></strong>
      <small>Base neta acumulada: <?= dashboard_money(max($quarterIrpfEstimateBase, 0)) ?></small>
    </article>
  </section>

  <section class="dashboard-board-grid">
    <article class="card dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Ventas</span>
          <h3>Estado comercial</h3>
        </div>
        <a href="<?= route_path('quotes') ?>" class="btn btn-secondary btn-sm">Ver presupuestos</a>
      </div>
      <div class="dashboard-mini-grid">
        <div class="dashboard-mini-stat">
          <span>Aceptados</span>
          <strong><?= count($quotesAccepted) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>Borradores</span>
          <strong><?= count($quotesDraft) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>Cobradas</span>
          <strong><?= count($paidInvoices) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>Clientes activos</span>
          <strong><?= count($allClients) ?></strong>
        </div>
      </div>
      <div class="dashboard-list">
        <?php if (empty($urgentCollection)): ?>
          <p class="dashboard-empty">No hay cobros urgentes ahora mismo.</p>
        <?php else: ?>
          <?php foreach ($urgentCollection as $invoice): ?>
            <div class="dashboard-list-row">
              <div>
                <strong><?= htmlspecialchars((string)($invoice['client_name'] ?? 'Cliente')) ?></strong>
                <span><?= htmlspecialchars((string)($invoice['invoice_number'] ?: 'Sin numero')) ?> · vence <?= dashboard_date($invoice['due_date'] ?? null) ?></span>
              </div>
              <div class="dashboard-list-value <?= (!empty($invoice['due_date']) && (string)$invoice['due_date'] < $todayYmd) ? 'danger' : '' ?>">
                <?= dashboard_money((float)($invoiceTotals[(int)$invoice['id']]['total'] ?? 0)) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>

    <article class="card dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Fiscal</span>
          <h3>Trimestre en curso</h3>
        </div>
        <a href="<?= route_path('declaraciones') ?>" class="btn btn-secondary btn-sm">Abrir centro fiscal</a>
      </div>
      <div class="dashboard-tax-grid">
        <div class="dashboard-tax-card">
          <span>Base ventas</span>
          <strong><?= dashboard_money((float)$quarterTax['base_total']) ?></strong>
        </div>
        <div class="dashboard-tax-card">
          <span>Base gastos</span>
          <strong><?= dashboard_money((float)$quarterExpenses['base_total']) ?></strong>
        </div>
        <div class="dashboard-tax-card">
          <span>IVA ventas</span>
          <strong><?= dashboard_money((float)$quarterTax['iva_total']) ?></strong>
        </div>
        <div class="dashboard-tax-card">
          <span>IVA gastos</span>
          <strong><?= dashboard_money((float)$quarterExpenses['vat_total']) ?></strong>
        </div>
      </div>
      <ul class="dashboard-checklist">
        <li><span>Borradores por emitir</span><strong><?= (int)$quarterChecklist['draft_invoices'] ?></strong></li>
        <li><span>Gastos por revisar</span><strong><?= (int)$quarterChecklist['pending_expenses'] ?></strong></li>
        <li><span>Gastos en otros</span><strong><?= (int)$quarterChecklist['uncategorized_expenses'] ?></strong></li>
        <li><span>Facturas sin cobrar</span><strong><?= (int)$quarterChecklist['unpaid_issued'] ?></strong></li>
      </ul>
    </article>

    <article class="card dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Compras</span>
          <h3>Operacion de gastos</h3>
        </div>
        <a href="<?= route_path('expenses') ?>" class="btn btn-secondary btn-sm">Ver gastos</a>
      </div>
      <div class="dashboard-mini-grid">
        <div class="dashboard-mini-stat">
          <span>Gastos este mes</span>
          <strong><?= count($expensesMonth) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>Proveedores</span>
          <strong><?= count($allSuppliers) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>Sin proveedor</span>
          <strong><?= count($expensesWithoutSupplier) ?></strong>
        </div>
        <div class="dashboard-mini-stat">
          <span>En otros</span>
          <strong><?= count($expensesOtherCategory) ?></strong>
        </div>
      </div>
      <div class="dashboard-list">
        <?php if (empty($recentExpenses)): ?>
          <p class="dashboard-empty">Todavia no hay gastos recientes.</p>
        <?php else: ?>
          <?php foreach ($recentExpenses as $expense): ?>
            <div class="dashboard-list-row">
              <div>
                <strong><?= htmlspecialchars((string)$expense['supplier_name']) ?></strong>
                <span><?= dashboard_date((string)$expense['invoice_date']) ?> · <?= htmlspecialchars((string)$expense['status']) ?></span>
              </div>
              <div class="dashboard-list-value">
                <?= dashboard_money((float)($expense['total_amount'] ?? 0)) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>

    <article class="card dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Agenda</span>
          <h3>Lo proximo</h3>
        </div>
        <a href="<?= route_path('reminders') ?>" class="btn btn-secondary btn-sm">Gestionar avisos</a>
      </div>
      <div class="dashboard-list">
        <?php if (empty($upcomingReminders)): ?>
          <p class="dashboard-empty">No hay avisos proximos configurados.</p>
        <?php else: ?>
          <?php foreach ($upcomingReminders as $reminder): ?>
            <div class="dashboard-list-row">
              <div>
                <strong><?= htmlspecialchars($reminder['title']) ?></strong>
                <span>Recordatorio activo</span>
              </div>
              <div class="dashboard-list-value"><?= $reminder['date']->format('d/m/Y') ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  </section>

  <section class="dashboard-bottom-grid">
    <article class="card dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Actividad</span>
          <h3>Ultimas facturas</h3>
        </div>
        <a href="<?= route_path('invoices') ?>" class="btn btn-secondary btn-sm">Ver facturas</a>
      </div>
      <div class="dashboard-list">
        <?php if (empty($recentInvoices)): ?>
          <p class="dashboard-empty">No hay facturas todavia.</p>
        <?php else: ?>
          <?php foreach ($recentInvoices as $invoice): ?>
            <div class="dashboard-list-row">
              <div>
                <strong><?= htmlspecialchars((string)($invoice['invoice_number'] ?: 'Borrador')) ?></strong>
                <span><?= htmlspecialchars((string)($invoice['client_name'] ?? 'Sin cliente')) ?> · <?= dashboard_date((string)$invoice['issue_date']) ?></span>
              </div>
              <div class="dashboard-list-value"><?= dashboard_money((float)($invoiceTotals[(int)$invoice['id']]['total'] ?? 0)) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>

    <article class="card dashboard-panel dashboard-actions-panel">
      <div class="dashboard-panel-head">
        <div>
          <span class="dashboard-panel-kicker">Acciones</span>
          <h3>Atajos rapidos</h3>
        </div>
      </div>
      <div class="dashboard-action-list">
        <a href="<?= route_path('quote_form') ?>" class="dashboard-action-tile">
          <strong>Preparar presupuesto</strong>
          <span>Lanza una propuesta y compartela con el cliente.</span>
        </a>
        <a href="<?= route_path('expense_form') ?>" class="dashboard-action-tile">
          <strong>Escanear ticket</strong>
          <span>Sube una foto o PDF y completa el gasto.</span>
        </a>
        <a href="<?= route_path('suppliers') ?>" class="dashboard-action-tile">
          <strong>Revisar proveedores</strong>
          <span>Mantiene categorias y VAT por defecto afinados.</span>
        </a>
        <a href="<?= route_path('declaraciones') ?>" class="dashboard-action-tile">
          <strong>Revisar trimestre</strong>
          <span>Comprueba IVA, IRPF y tareas de cierre.</span>
        </a>
      </div>
    </article>
  </section>
</div>

<style>
.dashboard-shell {
  display: grid;
  gap: 16px;
}
.dashboard-hero {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 16px;
  background:
    radial-gradient(circle at top right, rgba(15, 163, 177, 0.18), transparent 36%),
    linear-gradient(135deg, rgba(247, 249, 252, 0.98), rgba(255, 255, 255, 0.94));
}
.dashboard-eyebrow {
  display: inline-flex;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  background: rgba(15, 163, 177, 0.1);
  color: var(--primary-dark);
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 10px;
}
.dashboard-hero-copy h1 {
  margin: 0 0 8px;
}
.dashboard-hero-copy p {
  max-width: 64ch;
  color: var(--gray-600);
  margin: 0;
}
.dashboard-hero-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 16px;
}
.dashboard-hero-summary {
  display: grid;
  gap: 10px;
}
.dashboard-hero-metric {
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(15, 35, 31, 0.08);
  background: rgba(255, 255, 255, 0.82);
}
.dashboard-hero-metric span {
  display: block;
  font-size: 0.84rem;
  color: var(--gray-600);
  margin-bottom: 6px;
}
.dashboard-hero-metric strong {
  font-size: 1.25rem;
  color: var(--gray-900);
}
.dashboard-focus-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.dashboard-focus-card {
  border-radius: 16px;
  padding: 14px 16px;
  border: 1px solid transparent;
}
.dashboard-focus-card strong {
  display: block;
  margin-bottom: 6px;
}
.dashboard-focus-card p {
  margin: 0;
  color: var(--gray-700);
}
.dashboard-focus-danger {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.14);
}
.dashboard-focus-warning {
  background: rgba(245, 158, 11, 0.09);
  border-color: rgba(245, 158, 11, 0.16);
}
.dashboard-focus-info {
  background: rgba(15, 163, 177, 0.08);
  border-color: rgba(15, 163, 177, 0.15);
}
.dashboard-focus-success {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.14);
}
.dashboard-kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.dashboard-kpi-card {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(15, 23, 42, 0.06);
  border-radius: 18px;
  padding: 16px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}
.dashboard-kpi-label {
  display: block;
  font-size: 0.84rem;
  color: var(--gray-600);
  margin-bottom: 8px;
}
.dashboard-kpi-card strong {
  display: block;
  font-size: 1.35rem;
  color: var(--gray-900);
  margin-bottom: 6px;
}
.dashboard-kpi-card small {
  color: var(--gray-500);
}
.dashboard-board-grid,
.dashboard-bottom-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.dashboard-panel {
  background: rgba(255, 255, 255, 0.94);
}
.dashboard-panel-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 14px;
}
.dashboard-panel-head h3 {
  margin: 2px 0 0;
}
.dashboard-panel-kicker {
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--gray-500);
  font-weight: 700;
}
.dashboard-mini-grid,
.dashboard-tax-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.dashboard-mini-stat,
.dashboard-tax-card {
  padding: 12px 14px;
  border-radius: 14px;
  background: #f7f9fc;
  border: 1px solid #e7ecf2;
}
.dashboard-mini-stat span,
.dashboard-tax-card span {
  display: block;
  font-size: 0.82rem;
  color: var(--gray-600);
  margin-bottom: 6px;
}
.dashboard-mini-stat strong,
.dashboard-tax-card strong {
  font-size: 1.1rem;
  color: var(--gray-900);
}
.dashboard-list {
  display: grid;
  gap: 8px;
}
.dashboard-list-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.dashboard-list-row:last-child {
  border-bottom: 0;
}
.dashboard-list-row strong,
.dashboard-list-row span {
  display: block;
}
.dashboard-list-row span {
  color: var(--gray-500);
  font-size: 0.86rem;
}
.dashboard-list-value {
  font-weight: 700;
  color: var(--gray-900);
  text-align: right;
  white-space: nowrap;
}
.dashboard-list-value.danger {
  color: var(--danger);
}
.dashboard-empty {
  margin: 0;
  color: var(--gray-500);
  font-style: italic;
}
.dashboard-checklist {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}
.dashboard-checklist li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.dashboard-checklist li:last-child {
  border-bottom: 0;
}
.dashboard-actions-panel {
  background:
    linear-gradient(180deg, rgba(255,255,255,0.97), rgba(245,248,252,0.96));
}
.dashboard-action-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.dashboard-action-tile {
  text-decoration: none;
  color: inherit;
  padding: 14px;
  border-radius: 16px;
  border: 1px solid rgba(15, 35, 31, 0.08);
  background: rgba(255, 255, 255, 0.88);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.dashboard-action-tile:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  border-color: rgba(15, 163, 177, 0.2);
}
.dashboard-action-tile strong {
  display: block;
  margin-bottom: 6px;
}
.dashboard-action-tile span {
  color: var(--gray-600);
  font-size: 0.88rem;
}
@media (max-width: 980px) {
  .dashboard-hero,
  .dashboard-kpi-grid,
  .dashboard-board-grid,
  .dashboard-bottom-grid,
  .dashboard-action-list {
    grid-template-columns: 1fr;
  }
}
</style>
