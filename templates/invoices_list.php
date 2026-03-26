<?php
use Moni\Repositories\InvoicesRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Services\InvoiceNumberingService;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Database;

$flashAll = Flash::getAll();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$status = in_array($status, ['draft','issued','paid','cancelled'], true) ? $status : null;
$period = isset($_GET['period']) ? trim((string)$_GET['period']) : '';
$period = in_array($period, ['month', 'quarter', 'year', 'custom'], true) ? $period : '';
$due = isset($_GET['due']) ? trim((string)$_GET['due']) : '';
$due = in_array($due, ['overdue', 'upcoming', 'no_due'], true) ? $due : null;
$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'issue_date';
$sort = in_array($sort, ['issue_date', 'due_date', 'amount', 'client'], true) ? $sort : 'issue_date';
$dir = isset($_GET['dir']) ? strtolower(trim((string)($_GET['dir'] ?? 'desc'))) : 'desc';
$dir = $dir === 'asc' ? 'asc' : 'desc';
$start = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$end = isset($_GET['end']) ? trim((string)$_GET['end']) : '';

if ($period === '' && ($start !== '' || $end !== '')) {
  $period = 'custom';
}

// Actions: issue, paid, cancelled, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    header('Location: /?page=invoices');
    exit;
  }
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['_action'] ?? '';
  if ($id > 0 && $action) {
    try {
      if ($action === 'issue') {
        $inv = InvoicesRepository::find($id);
        if ($inv && $inv['status'] === 'draft') {
          $items = InvoiceItemsRepository::byInvoice($id);
          if (empty($items)) {
            Flash::add('error', 'No puedes emitir una factura sin líneas.');
          } else {
            $num = InvoiceNumberingService::issue($id, $inv['issue_date']);
            Flash::add('success', 'Factura emitida con número ' . $num);
          }
        }
      } elseif ($action === 'paid') {
        InvoicesRepository::setStatus($id, 'paid');
        Flash::add('success', 'Factura marcada como Pagada.');
      } elseif ($action === 'cancelled') {
        InvoicesRepository::setStatus($id, 'cancelled');
        Flash::add('success', 'Factura Cancelada.');
      } elseif ($action === 'delete') {
        InvoiceItemsRepository::deleteByInvoice($id);
        $pdo = Database::pdo();
        $del = $pdo->prepare('DELETE FROM invoices WHERE id = :id');
        $del->execute([':id' => $id]);
        Flash::add('success', 'Factura eliminada.');
      }
    } catch (Throwable $e) {
      Flash::add('error', 'Acción fallida: ' . $e->getMessage());
    }
    header('Location: /?page=invoices');
    exit;
  }
}

function fmt_date(?string $date): string {
  if (!$date) {
    return '—';
  }
  $ts = strtotime($date);
  return $ts ? date('d/m/Y', $ts) : $date;
}

function status_es(string $s): string {
  return match ($s) {
    'draft' => 'Borrador',
    'issued' => 'Emitida',
    'paid' => 'Pagada',
    'cancelled' => 'Cancelada',
    default => $s,
  };
}

function period_label(string $period, ?string $start, ?string $end): string {
  return match ($period) {
    'month' => 'Este mes',
    'quarter' => 'Este trimestre',
    'year' => 'Este año',
    'custom' => ($start && $end) ? ('Periodo: ' . fmt_date($start) . ' - ' . fmt_date($end)) : 'Periodo personalizado',
    default => 'Sin restricción temporal',
  };
}

$dateFrom = null;
$dateTo = null;
$today = date('Y-m-d');
$upcomingLimit = date('Y-m-d', strtotime('+7 days'));

if ($period === 'month') {
  $dateFrom = date('Y-m-01');
  $dateTo = date('Y-m-t');
} elseif ($period === 'quarter') {
  $month = (int) date('n');
  $quarterStartMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
  $dateFrom = date(sprintf('Y-%02d-01', $quarterStartMonth));
  $dateTo = date('Y-m-t', strtotime(sprintf('%s +2 months', $dateFrom)));
} elseif ($period === 'year') {
  $dateFrom = date('Y-01-01');
  $dateTo = date('Y-12-31');
} elseif ($period === 'custom') {
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $dateFrom = $start;
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $dateTo = $end;
  }
}

$invoices = InvoicesRepository::all($q, $status, $dateFrom, $dateTo, $due, $sort, $dir);
$summary = [
  'count' => count($invoices),
  'total' => 0.0,
  'pending' => 0,
  'overdue' => 0,
];

foreach ($invoices as &$invoiceRow) {
  $invoiceRow['is_overdue'] = $invoiceRow['status'] === 'issued'
    && !empty($invoiceRow['due_date'])
    && $invoiceRow['due_date'] < $today;
  $invoiceRow['is_upcoming'] = $invoiceRow['status'] === 'issued'
    && !empty($invoiceRow['due_date'])
    && $invoiceRow['due_date'] >= $today
    && $invoiceRow['due_date'] <= $upcomingLimit;
  $summary['total'] += (float)($invoiceRow['total_amount'] ?? 0);
  if ($invoiceRow['status'] === 'issued') {
    $summary['pending']++;
  }
  if ($invoiceRow['is_overdue']) {
    $summary['overdue']++;
  }
}
unset($invoiceRow);

$filtersActive = $q !== '' || $status || $period || $due || $start !== '' || $end !== '' || $sort !== 'issue_date' || $dir !== 'desc';
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <div>
      <h1 style="margin-bottom:8px">Facturas</h1>
      <p style="margin:0;color:var(--gray-600);max-width:760px">
        Filtra por periodo, vencimiento y estado para centrarte antes en lo que toca revisar o cobrar.
      </p>
    </div>
    <a href="/?page=invoice_form" class="btn">+ Nueva factura</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <form method="get" class="invoices-filter-grid">
      <input type="hidden" name="page" value="invoices" />

      <div class="invoices-filter-block invoices-filter-search">
        <label for="q">Buscar</label>
        <input id="q" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Número o cliente" />
      </div>

      <div class="invoices-filter-block">
        <label for="period">Periodo</label>
        <select id="period" name="period">
          <option value="">Todos</option>
          <option value="month" <?= $period==='month'?'selected':'' ?>>Este mes</option>
          <option value="quarter" <?= $period==='quarter'?'selected':'' ?>>Este trimestre</option>
          <option value="year" <?= $period==='year'?'selected':'' ?>>Este año</option>
          <option value="custom" <?= $period==='custom'?'selected':'' ?>>Personalizado</option>
        </select>
      </div>

      <div class="invoices-filter-block">
        <label for="status">Estado</label>
        <select id="status" name="status">
          <option value="">Todos</option>
          <option value="draft" <?= $status==='draft'?'selected':'' ?>>Borrador</option>
          <option value="issued" <?= $status==='issued'?'selected':'' ?>>Emitida</option>
          <option value="paid" <?= $status==='paid'?'selected':'' ?>>Pagada</option>
          <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelada</option>
        </select>
      </div>

      <div class="invoices-filter-block">
        <label for="due">Vencimiento</label>
        <select id="due" name="due">
          <option value="">Todos</option>
          <option value="overdue" <?= $due==='overdue'?'selected':'' ?>>Vencidas</option>
          <option value="upcoming" <?= $due==='upcoming'?'selected':'' ?>>Próximas a vencer</option>
          <option value="no_due" <?= $due==='no_due'?'selected':'' ?>>Sin vencimiento</option>
        </select>
      </div>

      <div class="invoices-filter-block">
        <label for="sort">Ordenar por</label>
        <select id="sort" name="sort">
          <option value="issue_date" <?= $sort==='issue_date'?'selected':'' ?>>Fecha factura</option>
          <option value="due_date" <?= $sort==='due_date'?'selected':'' ?>>Vencimiento</option>
          <option value="amount" <?= $sort==='amount'?'selected':'' ?>>Importe</option>
          <option value="client" <?= $sort==='client'?'selected':'' ?>>Cliente</option>
        </select>
      </div>

      <div class="invoices-filter-block">
        <label for="dir">Dirección</label>
        <select id="dir" name="dir">
          <option value="desc" <?= $dir==='desc'?'selected':'' ?>>Descendente</option>
          <option value="asc" <?= $dir==='asc'?'selected':'' ?>>Ascendente</option>
        </select>
      </div>

      <div class="invoices-filter-block">
        <label for="start">Desde</label>
        <input id="start" type="date" name="start" value="<?= htmlspecialchars($start) ?>" />
      </div>

      <div class="invoices-filter-block">
        <label for="end">Hasta</label>
        <input id="end" type="date" name="end" value="<?= htmlspecialchars($end) ?>" />
      </div>

      <div class="invoices-filter-actions">
        <button type="submit" class="btn">Aplicar filtros</button>
        <?php if ($filtersActive): ?>
          <a href="/?page=invoices" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card invoices-summary-card">
    <div class="invoices-summary-grid">
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gray-800)"><?= $summary['count'] ?></div>
        <div class="stat-label">Facturas visibles</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--primary)"><?= number_format($summary['total'], 2, ',', '.') ?>€</div>
        <div class="stat-label">Importe filtrado</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#D97706"><?= $summary['pending'] ?></div>
        <div class="stat-label">Pendientes de cobro</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:<?= $summary['overdue'] > 0 ? 'var(--danger)' : 'var(--gray-600)' ?>"><?= $summary['overdue'] ?></div>
        <div class="stat-label">Vencidas</div>
      </div>
    </div>
    <div class="invoices-active-caption">
      <?= htmlspecialchars(period_label($period, $dateFrom, $dateTo)) ?>
      <?php if ($due === 'overdue'): ?> · Solo vencidas<?php endif; ?>
      <?php if ($due === 'upcoming'): ?> · Próximas a vencer (7 días)<?php endif; ?>
      <?php if ($due === 'no_due'): ?> · Sin fecha de vencimiento<?php endif; ?>
    </div>
  </div>

  <?php if (empty($invoices)): ?>
    <div class="card" style="text-align:center;padding:48px">
      <p style="color:var(--gray-600);margin:0">No hay facturas con los filtros actuales.</p>
      <div style="margin-top:16px">
        <a href="/?page=invoices" class="btn btn-secondary">Ver todas</a>
        <a href="/?page=invoice_form" class="btn">Crear factura</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <div class="invoices-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Nº</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Fecha factura</th>
            <th>Vencimiento</th>
            <th>Importe</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $i): ?>
            <tr class="<?= $i['is_overdue'] ? 'invoice-row-overdue' : ($i['is_upcoming'] ? 'invoice-row-upcoming' : '') ?>">
              <td>
                <div style="font-weight:700;color:var(--gray-800)"><?= htmlspecialchars($i['invoice_number'] ?: '—') ?></div>
                <div style="font-size:0.8rem;color:var(--gray-500)">Creada <?= fmt_date(substr((string)$i['created_at'], 0, 10)) ?></div>
              </td>
              <td>
                <div style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($i['client_name'] ?? 'Sin cliente') ?></div>
              </td>
              <td>
                <span class="status-badge status-<?= htmlspecialchars($i['status']) ?>"><?= htmlspecialchars(status_es($i['status'])) ?></span>
                <?php if ($i['is_overdue']): ?>
                  <div class="invoice-meta-warning">Pendiente y vencida</div>
                <?php elseif ($i['is_upcoming']): ?>
                  <div class="invoice-meta-warning invoice-meta-upcoming">Vence pronto</div>
                <?php endif; ?>
              </td>
              <td>
                <div class="invoice-date-main"><?= fmt_date($i['issue_date']) ?></div>
                <div class="invoice-date-sub">Fecha de emisión</div>
              </td>
              <td>
                <?php if (!empty($i['due_date'])): ?>
                  <div class="invoice-date-main"><?= fmt_date($i['due_date']) ?></div>
                  <div class="invoice-date-sub <?= $i['is_overdue'] ? 'is-danger' : ($i['is_upcoming'] ? 'is-warning' : '') ?>">
                    <?php if ($i['is_overdue']): ?>
                      Vencida
                    <?php elseif ($i['is_upcoming']): ?>
                      Próxima
                    <?php else: ?>
                      <?= (strtotime($i['due_date']) >= strtotime($today)) ? 'En plazo' : 'Revisar' ?>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="invoice-date-main">—</div>
                  <div class="invoice-date-sub">Sin vencimiento</div>
                <?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <div style="font-weight:700;color:var(--gray-800)"><?= number_format((float)$i['total_amount'], 2, ',', '.') ?> €</div>
              </td>
              <td class="table-actions">
                <a class="btn" href="/?page=invoice_form&id=<?= (int)$i['id'] ?>">Editar</a>
                <a class="btn btn-secondary" href="/?page=invoice_pdf&id=<?= (int)$i['id'] ?>" target="_blank" rel="noopener">PDF</a>
                <?php if ($i['status'] === 'draft'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="issue" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn btn-secondary">Emitir</button>
                  </form>
                <?php elseif ($i['status'] === 'issued'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="paid" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn btn-secondary">Marcar pagada</button>
                  </form>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Cancelar la factura?');">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="cancelled" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn btn-danger">Cancelar</button>
                  </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar la factura?');">
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                  <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
</section>
