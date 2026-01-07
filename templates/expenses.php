<?php
use Moni\Repositories\ExpensesRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'Token CSRF inválido.');
    } else {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0 && ExpensesRepository::delete($delId)) {
            Flash::add('success', 'Gasto eliminado correctamente.');
        } else {
            Flash::add('error', 'No se pudo eliminar el gasto.');
        }
    }
    header('Location: /?page=expenses');
    exit;
}

// Handle validate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'Token CSRF inválido.');
    } else {
        $valId = (int)($_POST['id'] ?? 0);
        if ($valId > 0 && ExpensesRepository::validate($valId)) {
            Flash::add('success', 'Gasto validado.');
        }
    }
    header('Location: /?page=expenses');
    exit;
}

// Filters
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
$filterCategory = $_GET['category'] ?? null;

$expenses = ExpensesRepository::all($filterYear, $filterCategory);
$years = ExpensesRepository::getYears();
$categories = ExpensesRepository::getCategories();

// Calculate totals for current filter
$totals = ['base' => 0, 'vat' => 0, 'total' => 0];
foreach ($expenses as $e) {
    $totals['base'] += (float)$e['base_amount'];
    $totals['vat'] += (float)$e['vat_amount'];
    $totals['total'] += (float)$e['total_amount'];
}
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <h1 style="margin:0">Gastos</h1>
    <a href="/?page=expense_form" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:-3px"><path d="M12 5v14M5 12h14"/></svg>
      Nuevo gasto
    </a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="page" value="expenses" />
      <div>
        <label for="year">Año</label>
        <select id="year" name="year" style="min-width:100px">
          <option value="">Todos</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $filterYear === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
          <?php if (empty($years) || !in_array((int)date('Y'), $years)): ?>
            <option value="<?= date('Y') ?>" <?= $filterYear === (int)date('Y') ? 'selected' : '' ?>><?= date('Y') ?></option>
          <?php endif; ?>
        </select>
      </div>
      <div>
        <label for="category">Categoría</label>
        <select id="category" name="category" style="min-width:180px">
          <option value="">Todas</option>
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= $key ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-sm">Filtrar</button>
      <?php if ($filterYear || $filterCategory): ?>
        <a href="/?page=expenses" class="btn btn-sm" style="background:var(--gray-200);color:var(--gray-700)">Limpiar</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!empty($expenses)): ?>
    <div class="card" style="margin-bottom:16px;background:var(--primary-50)">
      <div style="display:flex;gap:24px;flex-wrap:wrap">
        <div><strong>Base total:</strong> <?= number_format($totals['base'], 2, ',', '.') ?> €</div>
        <div><strong>IVA soportado:</strong> <?= number_format($totals['vat'], 2, ',', '.') ?> €</div>
        <div><strong>Total gastos:</strong> <?= number_format($totals['total'], 2, ',', '.') ?> €</div>
        <div style="color:var(--gray-600)">(<?= count($expenses) ?> registros)</div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($expenses)): ?>
    <div class="card" style="text-align:center;padding:48px">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.5" style="margin-bottom:12px">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
      </svg>
      <p style="color:var(--gray-600);margin:0">No hay gastos registrados<?= $filterYear || $filterCategory ? ' con estos filtros' : '' ?>.</p>
      <a href="/?page=expense_form" class="btn btn-primary" style="margin-top:16px">Registrar primer gasto</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Proveedor</th>
            <th>Nº Factura</th>
            <th>Categoría</th>
            <th style="text-align:right">Base</th>
            <th style="text-align:right">IVA</th>
            <th style="text-align:right">Total</th>
            <th>Estado</th>
            <th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
            <tr>
              <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($e['invoice_date'])) ?></td>
              <td>
                <strong><?= htmlspecialchars($e['supplier_name']) ?></strong>
                <?php if ($e['supplier_nif']): ?>
                  <br><small style="color:var(--gray-500)"><?= htmlspecialchars($e['supplier_nif']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= $e['invoice_number'] ? htmlspecialchars($e['invoice_number']) : '<span style="color:var(--gray-400)">—</span>' ?></td>
              <td>
                <span class="badge badge-secondary"><?= htmlspecialchars($categories[$e['category']] ?? $e['category']) ?></span>
              </td>
              <td style="text-align:right;white-space:nowrap"><?= number_format((float)$e['base_amount'], 2, ',', '.') ?> €</td>
              <td style="text-align:right;white-space:nowrap"><?= number_format((float)$e['vat_amount'], 2, ',', '.') ?> €</td>
              <td style="text-align:right;white-space:nowrap;font-weight:600"><?= number_format((float)$e['total_amount'], 2, ',', '.') ?> €</td>
              <td>
                <?php if ($e['status'] === 'validated'): ?>
                  <span class="badge badge-success">Validado</span>
                <?php else: ?>
                  <span class="badge badge-warning">Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <?php if ($e['pdf_path']): ?>
                    <a href="/<?= htmlspecialchars($e['pdf_path']) ?>" target="_blank" class="btn btn-sm" title="Ver PDF" style="padding:4px 8px">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </a>
                  <?php endif; ?>
                  <a href="/?page=expense_form&id=<?= $e['id'] ?>" class="btn btn-sm" title="Editar" style="padding:4px 8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </a>
                  <?php if ($e['status'] !== 'validated'): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Validar este gasto?')">
                      <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                      <input type="hidden" name="action" value="validate" />
                      <input type="hidden" name="id" value="<?= $e['id'] ?>" />
                      <button type="submit" class="btn btn-sm" title="Validar" style="padding:4px 8px;background:var(--success-500);color:white">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este gasto? Esta acción no se puede deshacer.')">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?= $e['id'] ?>" />
                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar" style="padding:4px 8px">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
