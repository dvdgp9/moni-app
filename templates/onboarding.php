<?php
use Moni\Repositories\SettingsRepository;
use Moni\Repositories\UsersRepository;
use Moni\Services\OnboardingService;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['user_id'])) {
    Flash::add('error', 'Inicia sesión para continuar.');
    moni_redirect(route_path('login'));
}

$userId = (int)$_SESSION['user_id'];
$flashAll = Flash::getAll();

if (isset($_GET['resume']) && $_GET['resume'] === '1') {
    OnboardingService::resume($userId);
}

$state = OnboardingService::getState($userId);
$user = $state['user'];
$step = isset($_GET['step']) ? (int)$_GET['step'] : (int)$state['step'];
$step = max(1, min(5, $step));

if (!empty($state['completed_at'])) {
    moni_redirect(route_path('dashboard'));
}

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
    'invoice_due_days' => $state['invoice_due_days'] !== null ? (string)$state['invoice_due_days'] : '',
    'default_vat_rate' => $state['default_vat_rate'] !== null ? (string)$state['default_vat_rate'] : '',
    'default_irpf_rate' => $state['default_irpf_rate'] !== null ? (string)$state['default_irpf_rate'] : '',
    'activity_mode' => (string)($state['tax_profile']['activity_mode'] ?? 'professional'),
    'issues_invoices_with_irpf' => !empty($state['tax_profile']['issues_invoices_with_irpf']),
    'has_rent_withholdings' => !empty($state['tax_profile']['has_rent_withholdings']),
    'has_payroll_or_professional_withholdings' => !empty($state['tax_profile']['has_payroll_or_professional_withholdings']),
    'tax_models' => $state['tax_models'],
];
$errors = [];

$allModels = [
    '303' => ['label' => 'Modelo 303', 'description' => 'IVA trimestral'],
    '130' => ['label' => 'Modelo 130', 'description' => 'Pago fraccionado de IRPF'],
    '111' => ['label' => 'Modelo 111', 'description' => 'Retenciones de profesionales y nóminas'],
    '115' => ['label' => 'Modelo 115', 'description' => 'Retenciones por alquiler'],
    '390' => ['label' => 'Modelo 390', 'description' => 'Resumen anual de IVA'],
];
$activityModes = [
    'professional' => 'Profesional / freelance',
    'business' => 'Actividad empresarial',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        moni_redirect(route_path('onboarding', ['step' => $step]));
    }

    $postedStep = max(1, min(5, (int)($_POST['step'] ?? $step)));
    $action = (string)($_POST['_action'] ?? 'continue');

    try {
        if ($action === 'skip') {
            OnboardingService::dismiss($userId, $postedStep);
            Flash::add('success', 'Puedes terminar la configuración más tarde desde el dashboard.');
            moni_redirect(route_path('dashboard'));
        }

        if ($postedStep === 1) {
            $values['name'] = trim((string)($_POST['name'] ?? ''));
            $values['company_name'] = trim((string)($_POST['company_name'] ?? ''));
            $values['nif'] = trim((string)($_POST['nif'] ?? ''));
            $values['address'] = trim((string)($_POST['address'] ?? ''));
            $values['phone'] = trim((string)($_POST['phone'] ?? ''));
            $values['billing_email'] = trim((string)($_POST['billing_email'] ?? ''));
            $values['iban'] = trim((string)($_POST['iban'] ?? ''));
            if ($values['name'] === '' && $values['company_name'] === '') {
                $errors['name'] = 'Añade al menos tu nombre o el nombre comercial para identificar la cuenta.';
            }
            if ($values['billing_email'] !== '' && filter_var($values['billing_email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['billing_email'] = 'El email de facturación no es válido.';
            }
            if (empty($errors)) {
                UsersRepository::updateProfile($userId, array_merge($user, $values));
            }
        } elseif ($postedStep === 2) {
            $values['color_primary'] = trim((string)($_POST['color_primary'] ?? $values['color_primary']));
            $values['color_accent'] = trim((string)($_POST['color_accent'] ?? $values['color_accent']));
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $values['color_primary'])) {
                $values['color_primary'] = '#8B5CF6';
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $values['color_accent'])) {
                $values['color_accent'] = '#F59E0B';
            }
            if (!empty($_FILES['logo_file']['name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
                $tmp = $_FILES['logo_file']['tmp_name'];
                $size = (int)($_FILES['logo_file']['size'] ?? 0);
                if ($size > 2 * 1024 * 1024) {
                    $errors['logo_file'] = 'El logo supera el tamaño máximo de 2MB.';
                } else {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = (string)$finfo->file($tmp);
                    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                    if (!isset($allowed[$mime])) {
                        $errors['logo_file'] = 'Usa PNG, JPG o WEBP.';
                    } else {
                        $uploadDir = dirname(__DIR__) . '/public/uploads/logos';
                        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                            $errors['logo_file'] = 'No se pudo preparar el directorio del logo.';
                        } else {
                            $fileName = 'logo-user-' . $userId . '.' . $allowed[$mime];
                            $dest = $uploadDir . '/' . $fileName;
                            if (!@move_uploaded_file($tmp, $dest)) {
                                $errors['logo_file'] = 'No se pudo guardar el logo.';
                            } else {
                                $values['logo_url'] = '/uploads/logos/' . $fileName;
                            }
                        }
                    }
                }
            }
            if (empty($errors)) {
                UsersRepository::updateProfile($userId, array_merge($user, $values));
            }
        } elseif ($postedStep === 3) {
            $values['invoice_due_days'] = trim((string)($_POST['invoice_due_days'] ?? ''));
            $values['default_vat_rate'] = trim((string)($_POST['default_vat_rate'] ?? ''));
            $values['default_irpf_rate'] = trim((string)($_POST['default_irpf_rate'] ?? ''));

            if ($values['invoice_due_days'] !== '') {
                $days = (int)$values['invoice_due_days'];
                if ($days < 1 || $days > 90) {
                    $errors['invoice_due_days'] = 'El plazo debe estar entre 1 y 90 días.';
                }
            }
            if ($values['default_vat_rate'] !== '') {
                $vat = (float)str_replace(',', '.', $values['default_vat_rate']);
                if (!is_numeric(str_replace(',', '.', $values['default_vat_rate'])) || $vat < 0 || $vat > 21) {
                    $errors['default_vat_rate'] = 'El IVA debe ser numérico y estar entre 0 y 21.';
                }
            }
            if ($values['default_irpf_rate'] !== '') {
                $irpf = str_replace(',', '.', $values['default_irpf_rate']);
                if (!is_numeric($irpf) || (float)$irpf < 0) {
                    $errors['default_irpf_rate'] = 'El IRPF debe ser numérico y no negativo.';
                }
            }
            if (empty($errors)) {
                SettingsRepository::set('invoice_due_days', $values['invoice_due_days'] !== '' ? $values['invoice_due_days'] : null, $userId);
                SettingsRepository::set('default_vat_rate', $values['default_vat_rate'] !== '' ? str_replace(',', '.', $values['default_vat_rate']) : null, $userId);
                SettingsRepository::set('default_irpf_rate', $values['default_irpf_rate'] !== '' ? str_replace(',', '.', $values['default_irpf_rate']) : null, $userId);
            }
        } elseif ($postedStep === 4) {
            $values['activity_mode'] = in_array((string)($_POST['activity_mode'] ?? 'professional'), array_keys($activityModes), true)
                ? (string)$_POST['activity_mode']
                : 'professional';
            $values['issues_invoices_with_irpf'] = isset($_POST['issues_invoices_with_irpf']);
            $values['has_rent_withholdings'] = isset($_POST['has_rent_withholdings']);
            $values['has_payroll_or_professional_withholdings'] = isset($_POST['has_payroll_or_professional_withholdings']);
            $values['tax_models'] = isset($_POST['tax_models']) && is_array($_POST['tax_models'])
                ? array_values(array_intersect(array_keys($allModels), array_map('strval', $_POST['tax_models'])))
                : [];

            SettingsRepository::set('tax_models', json_encode($values['tax_models']), $userId);
            SettingsRepository::set('tax_profile', json_encode([
                'activity_mode' => $values['activity_mode'],
                'issues_invoices_with_irpf' => $values['issues_invoices_with_irpf'],
                'has_rent_withholdings' => $values['has_rent_withholdings'],
                'has_payroll_or_professional_withholdings' => $values['has_payroll_or_professional_withholdings'],
            ]), $userId);
        } elseif ($postedStep === 5 && $action === 'finish') {
            OnboardingService::complete($userId);
            Flash::add('success', 'Tu espacio ya está listo. Puedes seguir afinándolo cuando quieras.');
            moni_redirect(route_path('dashboard'));
        }

        if (empty($errors)) {
            $nextStep = min(5, $postedStep + 1);
            OnboardingService::resume($userId, $nextStep);
            if ($postedStep === 4) {
                moni_redirect(route_path('onboarding', ['step' => 5]));
            }
            moni_redirect(route_path('onboarding', ['step' => $nextStep]));
        }
        $step = $postedStep;
    } catch (Throwable $e) {
        error_log('[onboarding] ' . $e->getMessage());
        Flash::add('error', 'No se pudo guardar este paso del onboarding.');
        moni_redirect(route_path('onboarding', ['step' => $postedStep]));
    }
}

$state = OnboardingService::getState($userId);
$sectionList = array_values($state['sections']);
$previewName = trim((string)($values['company_name'] ?: $values['name'] ?: 'Tu marca'));
$previewEmail = trim((string)$values['billing_email']);
$progressPercent = (int)round((($step - 1) / 4) * 100);
?>
<section class="onboarding-shell">
  <div class="onboarding-card card">
    <div class="onboarding-head">
      <div>
        <span class="onboarding-kicker">Bienvenida</span>
        <h1>Prepara tu espacio en unos minutos</h1>
        <p>Vamos a dejar tu cuenta lista para emitir documentos con buena pinta y usar la parte fiscal sin fricción. Puedes saltarte pasos y volver luego.</p>
      </div>
      <a class="btn btn-secondary btn-sm" href="<?= route_path('dashboard') ?>">Ir directo a la app</a>
    </div>

    <?php if (!empty($flashAll)): ?>
      <?php foreach ($flashAll as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
          <div class="alert <?= $type === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert error">Revisa los campos marcados para continuar.</div>
    <?php endif; ?>

    <div class="onboarding-progress">
      <div class="onboarding-progress-bar"><span style="width: <?= $progressPercent ?>%"></span></div>
      <div class="onboarding-steps">
        <?php foreach ([1 => 'Identidad', 2 => 'Imagen', 3 => 'Facturación', 4 => 'Fiscal', 5 => 'Resumen'] as $num => $label): ?>
          <span class="onboarding-step <?= $step === $num ? 'active' : ($step > $num ? 'done' : '') ?>"><?= $num ?>. <?= htmlspecialchars($label) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
      <input type="hidden" name="step" value="<?= $step ?>" />

      <?php if ($step === 1): ?>
        <div class="onboarding-grid">
          <div>
            <label>Nombre / representante</label>
            <input type="text" name="name" value="<?= htmlspecialchars($values['name']) ?>" placeholder="Tu nombre" />
            <?php if (!empty($errors['name'])): ?><div class="alert error"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
          </div>
          <div>
            <label>Nombre comercial o empresa</label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($values['company_name']) ?>" placeholder="Nombre que verán tus clientes" />
          </div>
          <div>
            <label>NIF</label>
            <input type="text" name="nif" value="<?= htmlspecialchars($values['nif']) ?>" placeholder="Tu NIF o CIF" />
          </div>
          <div>
            <label>Teléfono</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" placeholder="Opcional" />
          </div>
          <div class="onboarding-grid-span">
            <label>Dirección</label>
            <input type="text" name="address" value="<?= htmlspecialchars($values['address']) ?>" placeholder="Dirección fiscal o comercial" />
          </div>
          <div>
            <label>Email de facturación</label>
            <input type="email" name="billing_email" value="<?= htmlspecialchars($values['billing_email']) ?>" placeholder="Correo que aparecerá en tus documentos" />
            <?php if (!empty($errors['billing_email'])): ?><div class="alert error"><?= htmlspecialchars($errors['billing_email']) ?></div><?php endif; ?>
          </div>
          <div>
            <label>IBAN</label>
            <input type="text" name="iban" value="<?= htmlspecialchars($values['iban']) ?>" placeholder="Opcional" />
          </div>
        </div>
        <div class="onboarding-note">
          Si no completas esta parte ahora, luego tendrás que rellenarla antes de dejar tus facturas totalmente profesionales.
        </div>
      <?php elseif ($step === 2): ?>
        <div class="onboarding-grid">
          <div>
            <label>Logo</label>
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp" />
            <?php if (!empty($errors['logo_file'])): ?><div class="alert error"><?= htmlspecialchars($errors['logo_file']) ?></div><?php endif; ?>
            <p class="form-hint">PNG, JPG o WEBP. Máx. 2MB.</p>
          </div>
          <div class="onboarding-preview">
            <div class="onboarding-preview-card">
              <?php if (!empty($values['logo_url'])): ?>
                <img src="<?= htmlspecialchars($values['logo_url']) ?>" alt="Logo actual" class="onboarding-preview-logo" />
              <?php else: ?>
                <div class="onboarding-preview-placeholder">Sin logo</div>
              <?php endif; ?>
              <strong style="color:<?= htmlspecialchars($values['color_primary']) ?>"><?= htmlspecialchars($previewName) ?></strong>
              <span><?= htmlspecialchars($previewEmail !== '' ? $previewEmail : 'tu@email.com') ?></span>
            </div>
          </div>
          <div>
            <label>Color principal</label>
            <input type="color" name="color_primary" value="<?= htmlspecialchars($values['color_primary']) ?>" />
          </div>
          <div>
            <label>Color acento</label>
            <input type="color" name="color_accent" value="<?= htmlspecialchars($values['color_accent']) ?>" />
          </div>
        </div>
        <div class="onboarding-note">
          Si sigues sin logo, tus facturas y presupuestos seguirán funcionando, pero se verán menos profesionales.
        </div>
      <?php elseif ($step === 3): ?>
        <div class="onboarding-grid">
          <div>
            <label>Plazo por defecto de vencimiento (días)</label>
            <input type="number" min="1" max="90" name="invoice_due_days" value="<?= htmlspecialchars($values['invoice_due_days']) ?>" placeholder="30" />
            <?php if (!empty($errors['invoice_due_days'])): ?><div class="alert error"><?= htmlspecialchars($errors['invoice_due_days']) ?></div><?php endif; ?>
          </div>
          <div>
            <label>IVA habitual</label>
            <input type="text" name="default_vat_rate" value="<?= htmlspecialchars($values['default_vat_rate']) ?>" placeholder="21" />
            <?php if (!empty($errors['default_vat_rate'])): ?><div class="alert error"><?= htmlspecialchars($errors['default_vat_rate']) ?></div><?php endif; ?>
          </div>
          <div>
            <label>IRPF habitual</label>
            <input type="text" name="default_irpf_rate" value="<?= htmlspecialchars($values['default_irpf_rate']) ?>" placeholder="15" />
            <?php if (!empty($errors['default_irpf_rate'])): ?><div class="alert error"><?= htmlspecialchars($errors['default_irpf_rate']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="onboarding-note">
          Si no lo configuras ahora, tendrás que elegir estos valores manualmente más adelante al crear documentos.
        </div>
      <?php elseif ($step === 4): ?>
        <div class="onboarding-fiscal-layout">
          <div class="onboarding-fiscal-top">
            <div class="onboarding-fiscal-activity">
            <label>Tipo de actividad</label>
            <select name="activity_mode">
              <?php foreach ($activityModes as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $values['activity_mode'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
            <div class="onboarding-fiscal-flags">
              <span class="onboarding-subtitle">Situaciones que te aplican</span>
              <div class="onboarding-checks">
                <label class="onboarding-check-card">
                  <input type="checkbox" name="issues_invoices_with_irpf" value="1" <?= !empty($values['issues_invoices_with_irpf']) ? 'checked' : '' ?> />
                  <span>Mis facturas suelen llevar retención de IRPF</span>
                </label>
                <label class="onboarding-check-card">
                  <input type="checkbox" name="has_rent_withholdings" value="1" <?= !empty($values['has_rent_withholdings']) ? 'checked' : '' ?> />
                  <span>Pago alquiler con retención</span>
                </label>
                <label class="onboarding-check-card">
                  <input type="checkbox" name="has_payroll_or_professional_withholdings" value="1" <?= !empty($values['has_payroll_or_professional_withholdings']) ? 'checked' : '' ?> />
                  <span>Pago profesionales o nóminas con retención</span>
                </label>
              </div>
            </div>
          </div>
          <div class="onboarding-fiscal-models">
            <label>Modelos que te aplican</label>
            <div class="onboarding-models">
              <?php foreach ($allModels as $code => $model): ?>
                <label class="onboarding-model">
                  <input type="checkbox" name="tax_models[]" value="<?= htmlspecialchars((string)$code) ?>" <?= in_array((string)$code, $values['tax_models'], true) ? 'checked' : '' ?> />
                  <span><strong><?= htmlspecialchars($model['label']) ?></strong><small><?= htmlspecialchars($model['description']) ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="onboarding-note">
          Esto nos ayuda a enseñarte solo lo fiscal que te aplica. Si lo dejas para luego, podrás configurarlo desde Centro fiscal.
        </div>
      <?php else: ?>
        <div class="onboarding-summary-grid">
          <?php foreach ($sectionList as $section): ?>
            <article class="onboarding-summary-card <?= $section['complete'] ? 'complete' : 'pending' ?>">
              <strong><?= htmlspecialchars($section['label']) ?></strong>
              <span><?= $section['complete'] ? 'Configurado' : 'Pendiente' ?></span>
              <p><?= htmlspecialchars($section['hint']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="onboarding-note">
          Tu cuenta está al <?= (int)$state['progress'] ?>%. Puedes entrar ya y seguir afinando después desde perfil, ajustes y centro fiscal.
        </div>
      <?php endif; ?>

      <div class="onboarding-actions">
        <?php if ($step > 1): ?>
          <a class="btn btn-secondary" href="<?= route_path('onboarding', ['step' => $step - 1]) ?>">Atrás</a>
        <?php endif; ?>
        <button type="submit" name="_action" value="skip" class="btn btn-secondary">Saltar por ahora</button>
        <?php if ($step < 5): ?>
          <button type="submit" name="_action" value="continue" class="btn">Continuar</button>
        <?php else: ?>
          <a class="btn btn-secondary" href="<?= route_path('profile') ?>">Ir a mi perfil</a>
          <button type="submit" name="_action" value="finish" class="btn">Entrar en la app</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</section>

<style>
.onboarding-shell { max-width: 980px; margin: 0 auto; }
.onboarding-card { padding: 24px; }
.onboarding-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px; }
.onboarding-kicker { display:inline-flex; padding:0.35rem 0.7rem; border-radius:999px; background:rgba(15,163,177,0.1); color:var(--primary-dark); font-size:0.8rem; font-weight:700; margin-bottom:8px; }
.onboarding-head h1 { margin:0 0 8px; }
.onboarding-head p { margin:0; color:var(--gray-600); max-width:62ch; }
.onboarding-progress { margin-bottom: 20px; }
.onboarding-progress-bar { width:100%; height:10px; border-radius:999px; background:var(--gray-100); overflow:hidden; margin-bottom:10px; }
.onboarding-progress-bar span { display:block; height:100%; background:linear-gradient(90deg, var(--primary), #61c6d0); border-radius:inherit; }
.onboarding-steps { display:flex; gap:8px; flex-wrap:wrap; }
.onboarding-step { font-size:0.85rem; padding:6px 10px; border-radius:999px; background:var(--gray-100); color:var(--gray-600); }
.onboarding-step.active { background:rgba(15,163,177,0.12); color:var(--primary-dark); font-weight:700; }
.onboarding-step.done { background:rgba(5,150,105,0.12); color:#047857; }
.onboarding-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:14px; }
.onboarding-grid-span { grid-column:1 / -1; }
.onboarding-note { margin-top:16px; padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0; color:var(--gray-700); }
.onboarding-preview { display:flex; align-items:flex-end; justify-content:flex-end; }
.onboarding-preview-card { width:100%; max-width:280px; min-height:160px; border-radius:18px; border:1px solid rgba(15,35,31,0.08); background:linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247,249,252,0.94)); padding:18px; display:grid; gap:6px; box-shadow:0 12px 30px rgba(15,35,31,0.08); }
.onboarding-preview-logo { max-height:48px; width:auto; max-width:160px; object-fit:contain; }
.onboarding-preview-placeholder { width:88px; height:48px; border-radius:12px; background:var(--gray-100); display:flex; align-items:center; justify-content:center; color:var(--gray-500); font-size:0.78rem; }
.onboarding-fiscal-layout { display:grid; gap:18px; }
.onboarding-fiscal-top { display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1.2fr); gap:16px; align-items:start; }
.onboarding-fiscal-activity, .onboarding-fiscal-flags, .onboarding-fiscal-models { display:grid; gap:10px; }
.onboarding-subtitle { font-size:0.88rem; font-weight:700; color:var(--gray-700); text-transform:uppercase; letter-spacing:0.03em; }
.onboarding-checks { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; }
.onboarding-check-card { display:flex; gap:10px; align-items:flex-start; min-height:100px; padding:14px 16px; border:1px solid var(--gray-200); border-radius:16px; background:#f8fbfd; color:var(--gray-800); font-weight:600; line-height:1.4; }
.onboarding-check-card input[type="checkbox"],
.onboarding-model input[type="checkbox"] {
  width: 16px;
  height: 16px;
  min-width: 16px;
  margin: 2px 0 0;
  padding: 0;
  border: 1px solid var(--gray-300);
  border-radius: 4px;
  background: #fff;
  box-shadow: none;
  appearance: auto;
  -webkit-appearance: checkbox;
  flex: 0 0 auto;
}
.onboarding-check-card span,
.onboarding-model span {
  min-width: 0;
}
.onboarding-models { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
.onboarding-model { display:flex; gap:12px; align-items:flex-start; min-height:92px; padding:14px 16px; border-radius:16px; border:1px solid var(--gray-200); background:#fff; }
.onboarding-model span { display:grid; gap:4px; }
.onboarding-model strong { font-size:1.02rem; color:var(--gray-900); }
.onboarding-model small { color:var(--gray-600); }
.onboarding-summary-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
.onboarding-summary-card { padding:16px; border-radius:16px; border:1px solid var(--gray-200); background:#fff; display:grid; gap:6px; }
.onboarding-summary-card.complete { background:rgba(5,150,105,0.06); border-color:rgba(5,150,105,0.18); }
.onboarding-summary-card.pending { background:rgba(245,158,11,0.07); border-color:rgba(245,158,11,0.22); }
.onboarding-summary-card span { font-weight:700; }
.onboarding-summary-card p { margin:0; color:var(--gray-600); font-size:0.92rem; }
.onboarding-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; flex-wrap:wrap; }
@media (max-width: 820px) {
  .onboarding-grid, .onboarding-summary-grid, .onboarding-models, .onboarding-fiscal-top, .onboarding-checks { grid-template-columns:1fr; }
  .onboarding-preview { justify-content:flex-start; }
}
</style>
