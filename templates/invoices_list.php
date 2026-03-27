<?php
use Moni\Repositories\InvoicesRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Services\InvoiceNumberingService;
use Moni\Repositories\InvoiceItemsRepository;

$flashAll = Flash::getAll();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$yearsRaw = $_GET['years'] ?? [];
$quartersRaw = $_GET['quarters'] ?? [];
$years = is_array($yearsRaw) ? array_values(array_unique(array_filter(array_map('intval', $yearsRaw), static fn(int $y): bool => $y >= 1900 && $y <= 2100))) : [];
$quarters = is_array($quartersRaw) ? array_values(array_unique(array_filter(array_map('intval', $quartersRaw), static fn(int $qv): bool => $qv >= 1 && $qv <= 4))) : [];

$allowedSort = ['invoice_number', 'client_name', 'status', 'issue_date', 'due_date', 'amount'];
$sortByReq = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : '';
$sortByReq = in_array($sortByReq, $allowedSort, true) ? $sortByReq : '';
$sortDirReq = isset($_GET['sort_dir']) ? strtolower(trim((string)$_GET['sort_dir'])) : 'asc';
$sortDirReq = $sortDirReq === 'desc' ? 'desc' : 'asc';

$effectiveSortBy = $sortByReq !== '' ? $sortByReq : 'issue_date';
$effectiveSortDir = $sortByReq !== '' ? $sortDirReq : 'desc';

// Actions: issue, paid, cancelled, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    moni_redirect(route_path('invoices'));
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
        InvoicesRepository::delete($id);
        Flash::add('success', 'Factura eliminada.');
      }
    } catch (Throwable $e) {
      Flash::add('error', 'Acción fallida: ' . $e->getMessage());
    }
    moni_redirect(route_path('invoices'));
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

function year_short_label(int $year): string {
  return substr((string)$year, -2);
}

function sort_next(?string $currentBy, string $currentDir, string $column): array {
  if ($currentBy !== $column) {
    return ['by' => $column, 'dir' => 'asc'];
  }
  if ($currentDir === 'asc') {
    return ['by' => $column, 'dir' => 'desc'];
  }
  return ['by' => null, 'dir' => null];
}

function sort_indicator(?string $currentBy, string $currentDir, string $column): string {
  if ($currentBy !== $column) {
    return '↕';
  }
  return $currentDir === 'asc' ? '↑' : '↓';
}

$availableYears = InvoicesRepository::issueYearRange();
$invoices = InvoicesRepository::all($q, $years, $quarters, $effectiveSortBy, $effectiveSortDir);
$isAjax = (($_GET['ajax'] ?? '') === '1');

$today = date('Y-m-d');
$upcomingLimit = date('Y-m-d', strtotime('+7 days'));
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

$filtersActive = $q !== '' || !empty($years) || !empty($quarters);
$baseQuery = ['q' => $q];
if (!empty($years)) {
  $baseQuery['years'] = $years;
}
if (!empty($quarters)) {
  $baseQuery['quarters'] = $quarters;
}

ob_start();
?>
<div id="invoicesResults">
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
  </div>

  <?php if (empty($invoices)): ?>
    <div class="card" style="text-align:center;padding:48px">
      <p style="color:var(--gray-600);margin:0">No hay facturas con los filtros actuales.</p>
      <div style="margin-top:16px">
        <a href="<?= route_path('invoices') ?>" class="btn btn-secondary">Ver todas</a>
        <a href="<?= route_path('invoice_form') ?>" class="btn">Crear factura</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <div class="invoices-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <?php
              $headers = [
                'invoice_number' => 'Nº',
                'client_name' => 'Cliente',
                'status' => 'Estado',
                'issue_date' => 'Fecha factura',
                'due_date' => 'Vencimiento',
                'amount' => 'Importe',
              ];
              foreach ($headers as $col => $label):
                $next = sort_next($sortByReq !== '' ? $sortByReq : null, $sortDirReq, $col);
                $query = $baseQuery;
                if ($next['by'] !== null) {
                  $query['sort_by'] = $next['by'];
                  $query['sort_dir'] = $next['dir'];
                }
                $href = route_path('invoices', $query);
            ?>
              <th>
                <a class="table-sort-link" href="<?= htmlspecialchars($href) ?>">
                  <span><?= htmlspecialchars($label) ?></span>
                  <span class="table-sort-indicator"><?= htmlspecialchars(sort_indicator($sortByReq !== '' ? $sortByReq : null, $sortDirReq, $col)) ?></span>
                </a>
              </th>
            <?php endforeach; ?>
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
                <a class="btn" href="<?= route_path('invoice_form', ['id' => (int)$i['id']]) ?>">Editar</a>
                <a class="btn btn-secondary" href="<?= route_path('invoice_pdf', ['id' => (int)$i['id']]) ?>" target="_blank" rel="noopener">PDF</a>
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
</div>
<?php
$resultsHtml = (string)ob_get_clean();
if ($isAjax) {
  while (ob_get_level()) { ob_end_clean(); }
  header('Content-Type: text/html; charset=UTF-8');
  echo $resultsHtml;
  exit;
}
?>

<section>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <div>
      <h1 style="margin-bottom:8px">Facturas</h1>
      <p style="margin:0;color:var(--gray-600);max-width:760px">
        Filtra por año y trimestre para ver lo relevante del periodo. Haz clic en los encabezados para ordenar.
      </p>
    </div>
    <a href="<?= route_path('invoice_form') ?>" class="btn">+ Nueva factura</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <form id="invoiceFiltersForm" method="get" class="invoices-filter-grid invoices-filter-grid-compact">
      <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sortByReq) ?>" />
      <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sortDirReq) ?>" />

      <div class="invoices-filter-block invoices-filter-search">
        <label for="q">Buscar</label>
        <input id="q" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Número o cliente" />
      </div>

      <div class="invoices-filter-block">
        <div class="invoices-group-head">
          <label style="margin:0">Año</label>
          <button type="button" class="group-clear-btn" data-clear-group="years" title="Limpiar años">×</button>
        </div>
        <div class="check-pills" data-group="years">
          <?php foreach ($availableYears as $y): ?>
            <label class="check-pill">
              <input type="checkbox" name="years[]" value="<?= (int)$y ?>" <?= in_array((int)$y, $years, true) ? 'checked' : '' ?> />
              <span><?= htmlspecialchars(year_short_label((int)$y)) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="invoices-filter-block">
        <div class="invoices-group-head">
          <label style="margin:0">Periodo</label>
          <button type="button" class="group-clear-btn" data-clear-group="quarters" title="Limpiar trimestres">×</button>
        </div>
        <div class="check-pills" data-group="quarters">
          <?php for ($quarter = 1; $quarter <= 4; $quarter++): ?>
            <label class="check-pill">
              <input type="checkbox" name="quarters[]" value="<?= $quarter ?>" <?= in_array($quarter, $quarters, true) ? 'checked' : '' ?> />
              <span>T<?= $quarter ?></span>
            </label>
          <?php endfor; ?>
        </div>
      </div>

      <div class="invoices-filter-actions">
        <span class="invoices-auto-hint">Se aplica automaticamente</span>
        <?php if ($filtersActive): ?>
          <a href="<?= route_path('invoices') ?>" class="btn btn-secondary js-clear-all">Limpiar todo</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?= $resultsHtml ?>
</section>

<script>
(function () {
  const form = document.getElementById('invoiceFiltersForm');
  if (!form) return;
  const resultsId = 'invoicesResults';
  let requestController = null;

  let timer = null;
  const refreshByAjax = function () {
    const container = document.getElementById(resultsId);
    if (!container) {
      return;
    }
    if (requestController) {
      requestController.abort();
    }
    requestController = new AbortController();
    container.classList.add('invoices-loading');
    const params = new URLSearchParams(new FormData(form));
    params.set('ajax', '1');
    fetch('<?= route_path('invoices') ?>?' + params.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      signal: requestController.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (resp) {
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.text();
      })
      .then(function (html) {
        container.outerHTML = html;
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        console.warn('Invoices AJAX failed', err && err.message ? err.message : err);
      })
      .finally(function () {
        const liveContainer = document.getElementById(resultsId);
        if (liveContainer) liveContainer.classList.remove('invoices-loading');
      });
  };

  const submitWithDelay = function (ms) {
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      refreshByAjax();
    }, ms);
  };

  form.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
    el.addEventListener('change', function () { submitWithDelay(220); });
  });

  const qInput = form.querySelector('input[name="q"]');
  if (qInput) {
    qInput.addEventListener('input', function () { submitWithDelay(340); });
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    refreshByAjax();
  });

  document.addEventListener('click', function (ev) {
    const groupClear = ev.target.closest('.group-clear-btn');
    if (groupClear && form.contains(groupClear)) {
      ev.preventDefault();
      const group = groupClear.getAttribute('data-clear-group');
      if (!group) return;
      form.querySelectorAll('.check-pills[data-group="' + group + '"] input[type="checkbox"]').forEach(function (cb) {
        cb.checked = false;
      });
      submitWithDelay(120);
      return;
    }

    const clearAll = ev.target.closest('.js-clear-all');
    if (clearAll && form.contains(clearAll)) {
      ev.preventDefault();
      const q = form.querySelector('input[name="q"]');
      if (q) q.value = '';
      form.querySelectorAll('input[name="years[]"], input[name="quarters[]"]').forEach(function (cb) {
        cb.checked = false;
      });
      const byInput = form.querySelector('input[name="sort_by"]');
      const dirInput = form.querySelector('input[name="sort_dir"]');
      if (byInput) byInput.value = '';
      if (dirInput) dirInput.value = 'asc';
      refreshByAjax();
      return;
    }

    const sortLink = ev.target.closest('#' + resultsId + ' .table-sort-link');
    if (sortLink) {
      ev.preventDefault();
      try {
        const nextUrl = new URL(sortLink.href, window.location.origin);
        const nextBy = nextUrl.searchParams.get('sort_by') || '';
        const nextDir = nextUrl.searchParams.get('sort_dir') || '';
        const byInput = form.querySelector('input[name="sort_by"]');
        const dirInput = form.querySelector('input[name="sort_dir"]');
        if (byInput) byInput.value = nextBy;
        if (dirInput) dirInput.value = nextDir;
        refreshByAjax();
      } catch (err) {
        console.warn('Invoices sort link failed', err && err.message ? err.message : err);
      }
    }
  });
})();
</script>
