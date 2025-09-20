<?php
use Moni\Repositories\ClientsRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$errors = [];
$values = [
  'name' => '',
  'nif' => '',
  'email' => '',
  'phone' => '',
  'address' => '',
  'default_vat' => '21',
  'default_irpf' => '15',
];

if ($editing) {
  $found = ClientsRepository::find($id);
  if ($found) {
    $values = array_merge($values, $found);
  }
}

function valid_email(?string $e): bool {
  return $e === null || $e === '' || filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}
function valid_phone(?string $p): bool {
  if ($p === null || $p === '') return true;
  // Allow digits, spaces, +, -, and parentheses
  return (bool)preg_match('/^[0-9 +\-()]{6,20}$/', $p);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    header('Location: /?page=clients');
    exit;
  }
  $values['name'] = trim((string)($_POST['name'] ?? ''));
  $values['nif'] = trim((string)($_POST['nif'] ?? ''));
  $values['email'] = trim((string)($_POST['email'] ?? ''));
  $values['phone'] = trim((string)($_POST['phone'] ?? ''));
  $values['address'] = trim((string)($_POST['address'] ?? ''));
  $values['default_vat'] = (string)($_POST['default_vat'] ?? '21');
  $values['default_irpf'] = (string)($_POST['default_irpf'] ?? '15');

  if ($values['name'] === '') {
    $errors['name'] = 'El nombre es obligatorio';
  }
  if (!valid_email($values['email'])) {
    $errors['email'] = 'Email no válido';
  }
  if (!valid_phone($values['phone'])) {
    $errors['phone'] = 'Teléfono no válido';
  }
  $vat = (float)$values['default_vat'];
  $irpf = (float)$values['default_irpf'];
  if ($vat < 0 || $vat > 21) {
    $errors['default_vat'] = 'IVA debe estar entre 0 y 21';
  }
  if ($irpf < 0 || $irpf > 19) {
    $errors['default_irpf'] = 'IRPF debe estar entre 0 y 19';
  }

  if (empty($errors)) {
    $payload = [
      'name' => $values['name'],
      'nif' => $values['nif'] ?: null,
      'email' => $values['email'] ?: null,
      'phone' => $values['phone'] ?: null,
      'address' => $values['address'] ?: null,
      'default_vat' => $vat,
      'default_irpf' => $irpf,
    ];
    if ($editing) {
      ClientsRepository::update($id, $payload);
      Flash::add('success', 'Cliente actualizado.');
    } else {
      ClientsRepository::create($payload);
      Flash::add('success', 'Cliente creado.');
    }
    header('Location: /?page=clients');
    exit;
  }
}
?>
<section>
  <h1><?= $editing ? 'Editar cliente' : 'Nuevo cliente' ?></h1>

  <form method="post" class="card" style="max-width:760px">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

    <label>Nombre *</label>
    <input type="text" name="name" value="<?= htmlspecialchars($values['name']) ?>" required />
    <?php if (!empty($errors['name'])): ?><div class="alert" style="background:#FEE2E2;border-color:#FCA5A5;color:#991B1B"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>

    <div class="grid-2">
      <div>
        <label>NIF</label>
        <input type="text" name="nif" value="<?= htmlspecialchars($values['nif']) ?>" />
      </div>
      <div>
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" />
        <?php if (!empty($errors['email'])): ?><div class="alert" style="background:#FEE2E2;border-color:#FCA5A5;color:#991B1B"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>Teléfono</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" />
        <?php if (!empty($errors['phone'])): ?><div class="alert" style="background:#FEE2E2;border-color:#FCA5A5;color:#991B1B"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
      </div>
      <div>
        <label>Dirección</label>
        <input type="text" name="address" value="<?= htmlspecialchars($values['address']) ?>" />
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>IVA por defecto (%)</label>
        <input type="text" name="default_vat" value="<?= htmlspecialchars($values['default_vat']) ?>" />
        <?php if (!empty($errors['default_vat'])): ?><div class="alert" style="background:#FEE2E2;border-color:#FCA5A5;color:#991B1B"><?= htmlspecialchars($errors['default_vat']) ?></div><?php endif; ?>
      </div>
      <div>
        <label>IRPF por defecto (%)</label>
        <input type="text" name="default_irpf" value="<?= htmlspecialchars($values['default_irpf']) ?>" />
        <?php if (!empty($errors['default_irpf'])): ?><div class="alert" style="background:#FEE2E2;border-color:#FCA5A5;color:#991B1B"><?= htmlspecialchars($errors['default_irpf']) ?></div><?php endif; ?>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:10px">
      <button type="submit">Guardar</button>
      <a class="btn btn-secondary" href="/?page=clients">Cancelar</a>
    </div>
  </form>
</section>
