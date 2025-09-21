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

    // Handle optional logo file upload
    if (!empty($_FILES['logo_file']['name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
        $tmp = $_FILES['logo_file']['tmp_name'];
        $orig = $_FILES['logo_file']['name'];
        $size = (int)($_FILES['logo_file']['size'] ?? 0);
        // Limit 2MB
        if ($size > 2 * 1024 * 1024) {
            Flash::add('error', 'El logo supera el tamaño máximo (2MB).');
            header('Location: /?page=profile');
            exit;
        }
        // Validate mime
        $f = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$f->file($tmp);
        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            Flash::add('error', 'Formato de logo no soportado. Usa PNG, JPG, SVG o WEBP.');
            header('Location: /?page=profile');
            exit;
        }
        $ext = $allowed[$mime];
        $uploadDir = dirname(__DIR__, 1) . '/public/uploads/logos';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $fileName = 'logo-user-' . $userId . '.' . $ext;
        $dest = $uploadDir . '/' . $fileName;
        if (!@move_uploaded_file($tmp, $dest)) {
            Flash::add('error', 'No se pudo guardar el logo.');
            header('Location: /?page=profile');
            exit;
        }
        // Public URL
        $values['logo_url'] = '/uploads/logos/' . $fileName;
    }

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

  <form method="post" class="card" enctype="multipart/form-data">
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
        <label>Logo</label>
        <?php if (!empty($values['logo_url'])): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <img src="<?= htmlspecialchars($values['logo_url']) ?>" alt="Logo actual" style="height:42px;width:auto;border-radius:6px;border:1px solid var(--gray-200);background:#fff" />
            <span style="color:var(--gray-600);font-size:0.85rem">Se guardará en tu perfil y en el PDF</span>
          </div>
        <?php endif; ?>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" />
        <input type="text" name="logo_url" value="<?= htmlspecialchars($values['logo_url']) ?>" placeholder="o pega una URL (opcional)" />
        <p style="color:var(--gray-500);font-size:0.8rem;margin:6px 0 0">Tamaño recomendado 600×600 (cuadrado). Máx. 2MB. Formatos: PNG, JPG, SVG, WEBP.</p>
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
