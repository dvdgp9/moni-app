<?php
use Moni\Repositories\UsersRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Inicia sesión para acceder al perfil';
    return;
}

$userId = (int)$_SESSION['user_id'];
$flashAll = Flash::getAll();
$user = UsersRepository::find($userId) ?? [];

$values = [
  'name' => $user['name'] ?? '',
  'company_name' => $user['company_name'] ?? '',
  'nif' => $user['nif'] ?? '',
  'address' => $user['address'] ?? '',
  'phone' => $user['phone'] ?? '',
  'billing_email' => $user['billing_email'] ?? ($user['email'] ?? ''),
  'iban' => $user['iban'] ?? '',
  'logo_url' => $user['logo_url'] ?? '',
  'color_primary' => $user['color_primary'] ?? '#8B5CF6',
  'color_accent' => $user['color_accent'] ?? '#F59E0B',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        header('Location: /?page=profile');
        exit;
    }
    $values['name'] = trim((string)($_POST['name'] ?? ''));
    $values['company_name'] = trim((string)($_POST['company_name'] ?? ''));
    $values['nif'] = trim((string)($_POST['nif'] ?? ''));
    $values['address'] = trim((string)($_POST['address'] ?? ''));
    $values['phone'] = trim((string)($_POST['phone'] ?? ''));
    $values['billing_email'] = trim((string)($_POST['billing_email'] ?? ''));
    $values['iban'] = trim((string)($_POST['iban'] ?? ''));
    $values['logo_url'] = trim((string)($_POST['logo_url'] ?? ''));
    $values['color_primary'] = trim((string)($_POST['color_primary'] ?? ''));
    $values['color_accent'] = trim((string)($_POST['color_accent'] ?? ''));

    UsersRepository::updateProfile($userId, $values);
    Flash::add('success', 'Perfil actualizado.');
    header('Location: /?page=profile');
    exit;
}
?>
<section>
  <h1>Perfil</h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

    <div class="grid-2">
      <div>
        <label>Nombre / Representante *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($values['name']) ?>" required />
      </div>
      <div>
        <label>Nombre empresa (si aplica)</label>
        <input type="text" name="company_name" value="<?= htmlspecialchars($values['company_name']) ?>" />
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>NIF</label>
        <input type="text" name="nif" value="<?= htmlspecialchars($values['nif']) ?>" />
      </div>
      <div>
        <label>Teléfono</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" />
      </div>
    </div>

    <label>Dirección</label>
    <input type="text" name="address" value="<?= htmlspecialchars($values['address']) ?>" />

    <div class="grid-2">
      <div>
        <label>Email de facturación</label>
        <input type="email" name="billing_email" value="<?= htmlspecialchars($values['billing_email']) ?>" />
      </div>
      <div>
        <label>IBAN</label>
        <input type="text" name="iban" value="<?= htmlspecialchars($values['iban']) ?>" />
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>Logo (URL)</label>
        <input type="text" name="logo_url" value="<?= htmlspecialchars($values['logo_url']) ?>" />
      </div>
      <div>
        <label>Colores (primario y acento)</label>
        <div class="grid-2">
          <input type="color" name="color_primary" value="<?= htmlspecialchars($values['color_primary']) ?>" />
          <input type="color" name="color_accent" value="<?= htmlspecialchars($values['color_accent']) ?>" />
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" class="btn">Guardar perfil</button>
      <a href="/?page=dashboard" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</section>
