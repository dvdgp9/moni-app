<?php
use Moni\Repositories\SuppliersRepository;
use Moni\Repositories\ExpensesRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

$flashAll = Flash::getAll();
$q = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete')) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = SuppliersRepository::countExpenses($id);
            if ($count > 0) {
                Flash::add('error', 'No puedes eliminar este proveedor porque tiene gastos asociados.');
            } else {
                SuppliersRepository::delete($id);
                Flash::add('success', 'Proveedor eliminado.');
            }
        }
    }
    header('Location: ' . route_path('suppliers', $q !== '' ? ['q' => $q] : []));
    exit;
}

$suppliers = SuppliersRepository::all($q);
$categories = ExpensesRepository::getCategories();
$summary = [
    'count' => count($suppliers),
    'linked_expenses' => 0,
    'total_spend' => 0.0,
];
foreach ($suppliers as $supplier) {
    $summary['linked_expenses'] += (int)($supplier['expense_count'] ?? 0);
    $summary['total_spend'] += (float)($supplier['total_spend'] ?? 0);
}
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <h1 style="margin:0">Proveedores</h1>
    <a href="<?= route_path('supplier_form') ?>" class="btn">+ Nuevo proveedor</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
      <div style="min-width:260px;flex:1 1 280px">
        <label for="q">Buscar proveedor</label>
        <input id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o NIF" />
      </div>
      <button type="submit" class="btn btn-sm">Buscar</button>
      <?php if ($q !== ''): ?>
        <a href="<?= route_path('suppliers') ?>" class="btn btn-sm btn-secondary">Limpiar</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px">
    <div class="stat-card">
      <div class="stat-value" style="color:var(--gray-800)"><?= $summary['count'] ?></div>
      <div class="stat-label">Proveedores visibles</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" style="color:var(--primary)"><?= $summary['linked_expenses'] ?></div>
      <div class="stat-label">Gastos enlazados</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" style="color:var(--gray-700)"><?= number_format($summary['total_spend'], 0, ',', '.') ?>€</div>
      <div class="stat-label">Total asociado</div>
    </div>
  </div>

  <?php if (empty($suppliers)): ?>
    <div class="card" style="text-align:center;padding:48px">
      <p style="color:var(--gray-600);margin:0">Aún no hay proveedores<?= $q !== '' ? ' con esa búsqueda' : '' ?>.</p>
      <a href="<?= route_path('supplier_form') ?>" class="btn" style="margin-top:16px">Crear proveedor</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Proveedor</th>
            <th>Categoría habitual</th>
            <th>IVA</th>
            <th>Gastos</th>
            <th>Total</th>
            <th>Último uso</th>
            <th style="width:160px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $supplier): ?>
            <tr>
              <td>
                <div style="font-weight:700;color:var(--gray-800)"><?= htmlspecialchars($supplier['name']) ?></div>
                <div style="font-size:0.85rem;color:var(--gray-500)"><?= htmlspecialchars($supplier['nif'] ?: 'Sin NIF') ?></div>
              </td>
              <td><?= htmlspecialchars($categories[$supplier['default_category']] ?? $supplier['default_category']) ?></td>
              <td><?= number_format((float)$supplier['default_vat_rate'], 0, ',', '.') ?>%</td>
              <td><?= (int)($supplier['expense_count'] ?? 0) ?></td>
              <td><?= number_format((float)($supplier['total_spend'] ?? 0), 2, ',', '.') ?> €</td>
              <td><?= !empty($supplier['last_expense_date']) ? date('d/m/Y', strtotime((string)$supplier['last_expense_date'])) : '—' ?></td>
              <td>
                <div class="table-actions" style="justify-content:flex-end">
                  <a href="<?= route_path('expenses', ['supplier_id' => (int)$supplier['id']]) ?>" class="btn btn-secondary btn-sm">Ver gastos</a>
                  <a href="<?= route_path('supplier_form', ['id' => (int)$supplier['id']]) ?>" class="btn btn-sm">Editar</a>
                  <form method="post" onsubmit="return confirm('¿Eliminar este proveedor?');" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?= (int)$supplier['id'] ?>" />
                    <button type="submit" class="btn btn-danger btn-sm" <?= (int)($supplier['expense_count'] ?? 0) > 0 ? 'disabled' : '' ?>>Eliminar</button>
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
