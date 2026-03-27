<?php
use Moni\Repositories\SuppliersRepository;
use Moni\Repositories\ExpensesRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$errors = [];
$categories = ExpensesRepository::getCategories();
$supplier = [
    'name' => '',
    'nif' => '',
    'default_category' => 'otros',
    'default_vat_rate' => '21',
    'notes' => '',
];

if ($editing) {
    $found = SuppliersRepository::find($id);
    if (!$found) {
        Flash::add('error', 'Proveedor no encontrado o sin acceso.');
        moni_redirect(route_path('suppliers'));
    }
    $supplier = array_merge($supplier, $found);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        moni_redirect(route_path('suppliers'));
    }

    $supplier['name'] = trim((string)($_POST['name'] ?? ''));
    $supplier['nif'] = strtoupper(trim((string)($_POST['nif'] ?? '')));
    $supplier['default_category'] = (string)($_POST['default_category'] ?? 'otros');
    $supplier['default_vat_rate'] = str_replace(',', '.', trim((string)($_POST['default_vat_rate'] ?? '21')));
    $supplier['notes'] = trim((string)($_POST['notes'] ?? ''));

    if ($supplier['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }
    if (!is_numeric($supplier['default_vat_rate'])) {
        $errors['default_vat_rate'] = 'El IVA debe ser numérico.';
    }

    if (empty($errors)) {
        try {
            if ($editing) {
                SuppliersRepository::update($id, $supplier);
                Flash::add('success', 'Proveedor actualizado.');
            } else {
                SuppliersRepository::create($supplier);
                Flash::add('success', 'Proveedor creado.');
            }
            moni_redirect(route_path('suppliers'));
        } catch (\Throwable $e) {
            error_log('[supplier_form] ' . $e->getMessage());
            $errors['general'] = 'No se pudo guardar el proveedor. Revisa el NIF y los datos introducidos.';
        }
    }
}
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <h1 style="margin:0"><?= $editing ? 'Editar proveedor' : 'Nuevo proveedor' ?></h1>
    <a href="<?= route_path('suppliers') ?>" class="btn btn-secondary">Volver</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert error"><?= htmlspecialchars($errors['general']) ?></div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

    <div class="grid-2">
      <div>
        <label for="name">Nombre *</label>
        <input id="name" name="name" value="<?= htmlspecialchars($supplier['name']) ?>" required />
        <?php if (!empty($errors['name'])): ?><div class="alert error"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
      </div>
      <div>
        <label for="nif">NIF/CIF</label>
        <input id="nif" name="nif" value="<?= htmlspecialchars((string)$supplier['nif']) ?>" />
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label for="default_category">Categoría habitual</label>
        <select id="default_category" name="default_category">
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($supplier['default_category'] ?? 'otros') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="default_vat_rate">IVA habitual</label>
        <input id="default_vat_rate" name="default_vat_rate" value="<?= htmlspecialchars((string)$supplier['default_vat_rate']) ?>" />
        <?php if (!empty($errors['default_vat_rate'])): ?><div class="alert error"><?= htmlspecialchars($errors['default_vat_rate']) ?></div><?php endif; ?>
      </div>
    </div>

    <div>
      <label for="notes">Notas internas</label>
      <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars((string)$supplier['notes']) ?></textarea>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end">
      <a href="<?= route_path('suppliers') ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn"><?= $editing ? 'Guardar cambios' : 'Crear proveedor' ?></button>
    </div>
  </form>
</section>
