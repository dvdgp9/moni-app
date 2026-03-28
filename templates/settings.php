<?php
use Moni\Support\Config;
use Moni\Services\EmailService;
use Moni\Repositories\SettingsRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

// Guardado de ajustes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        moni_redirect(route_path('settings'));
    } else {
    $notify = isset($_POST['notify_email']) ? trim((string)$_POST['notify_email']) : null;
    $tz = isset($_POST['timezone']) ? trim((string)$_POST['timezone']) : null;
    $enabled = isset($_POST['reminders_enabled']) ? '1' : null; // null means no change
    $customDatesRaw = isset($_POST['custom_dates']) ? trim((string)$_POST['custom_dates']) : null;
    $defaultVatRaw = isset($_POST['default_vat_rate']) ? trim((string)$_POST['default_vat_rate']) : null;
    $defaultIrpfRaw = isset($_POST['default_irpf_rate']) ? trim((string)$_POST['default_irpf_rate']) : null;
    // Permitir formato líneas o JSON
    $custom = [];
    if ($customDatesRaw !== null && $customDatesRaw !== '') {
        $json = json_decode($customDatesRaw, true);
        if (is_array($json)) {
            $custom = $json;
        } else {
            $lines = preg_split('/\r?\n/', $customDatesRaw);
            foreach ($lines as $ln) {
                $d = trim($ln);
                if ($d !== '') { $custom[] = $d; }
            }
        }
    }
    // Normalizar fechas YYYY-MM-DD válidas
    $custom = array_values(array_filter($custom, function ($d) {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }));

    try {
        if ($notify !== null && $notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL) === false) {
            Flash::add('error', 'El email de notificación no es válido.');
            moni_redirect(route_path('settings'));
        }
        if ($defaultVatRaw !== null && $defaultVatRaw !== '') {
            $vatNormalized = str_replace(',', '.', $defaultVatRaw);
            if (!is_numeric($vatNormalized) || (float)$vatNormalized < 0 || (float)$vatNormalized > 21) {
                Flash::add('error', 'El IVA por defecto debe ser numérico y estar entre 0 y 21.');
                moni_redirect(route_path('settings'));
            }
        }
        if ($defaultIrpfRaw !== null && $defaultIrpfRaw !== '') {
            $irpfNormalized = str_replace(',', '.', $defaultIrpfRaw);
            if (!is_numeric($irpfNormalized) || (float)$irpfNormalized < 0) {
                Flash::add('error', 'El IRPF por defecto debe ser numérico y no negativo.');
                moni_redirect(route_path('settings'));
            }
        }
        if ($notify !== null) { SettingsRepository::set('notify_email', $notify); }
        if ($tz !== null) { SettingsRepository::set('timezone', $tz); }
        if ($enabled !== null) { SettingsRepository::set('reminders_enabled', $enabled); }
        if ($customDatesRaw !== null) { SettingsRepository::set('reminder_custom_dates', json_encode($custom)); }
        // Guardar plazo por defecto (días) si se envía
        if (isset($_POST['invoice_due_days'])) {
            $days = (int)$_POST['invoice_due_days'];
            if ($days > 0 && $days <= 90) {
                SettingsRepository::set('invoice_due_days', (string)$days);
            }
        }
        if ($defaultVatRaw !== null) {
            SettingsRepository::set('default_vat_rate', $defaultVatRaw !== '' ? str_replace(',', '.', $defaultVatRaw) : null);
        }
        if ($defaultIrpfRaw !== null) {
            SettingsRepository::set('default_irpf_rate', $defaultIrpfRaw !== '' ? str_replace(',', '.', $defaultIrpfRaw) : null);
        }
        Flash::add('success', 'Ajustes guardados correctamente.');
    } catch (Throwable $e) {
        error_log('[settings] ' . $e->getMessage());
        Flash::add('error', 'No se pudieron guardar los ajustes.');
    }
    }
    moni_redirect(route_path('settings'));
}

// Envío de prueba
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
    } else {
        $to = trim($_POST['test_email']);
        try {
            if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                Flash::add('error', 'El email de prueba no es válido.');
                moni_redirect(route_path('settings'));
            }
            $ok = EmailService::sendTest($to);
            Flash::add($ok ? 'success' : 'error', $ok ? 'Email de prueba enviado a ' . $to : 'No se pudo enviar el email de prueba.');
        } catch (Throwable $e) {
            error_log('[settings] ' . $e->getMessage());
            Flash::add('error', 'No se pudo enviar el email de prueba.');
        }
    }
    moni_redirect(route_path('settings'));
}

// Vista previa de recordatorio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_reminder'])) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
    } else {
        // Leer directamente desde BD/Config para evitar dependencia del orden de carga
        $currentNotify = SettingsRepository::get('notify_email') ?? (string)Config::get('settings.notify_email');
        $to = trim((string)$currentNotify);
        if ($to === '') {
            Flash::add('error', 'Configura primero un email de notificación para enviar la vista previa.');
        } else {
            try {
                $subject = 'Vista previa · Recordatorio: Cierre trimestral';
                $data = [
                    'title' => 'Cierre T3',
                    'range' => '01/10 — 20/10',
                    'links' => [
                        ['label' => 'IVA · Modelo 303', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G414.shtml'],
                        ['label' => 'IRPF · Modelo 130', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G601.shtml'],
                    ],
                    'brandName' => (string)(Config::get('app_name') ?? 'Moni'),
                    'appUrl' => (string)(Config::get('app_url') ?? '#'),
                ];
                EmailService::sendReminder($to, $subject, $data);
                Flash::add('success', 'Vista previa enviada a ' . $to);
            } catch (Throwable $e) {
                error_log('[settings] ' . $e->getMessage());
                Flash::add('error', 'No se pudo enviar la vista previa.');
            }
        }
    }
    moni_redirect(route_path('settings'));
}

// Cargar ajustes actuales (fallback a config/.env)
$s_notify = SettingsRepository::get('notify_email') ?? (string)Config::get('settings.notify_email');
$s_tz = SettingsRepository::get('timezone') ?? (string)Config::get('settings.timezone');
$s_enabled_raw = SettingsRepository::get('reminders_enabled');
$s_enabled = $s_enabled_raw === null ? (bool)Config::get('settings.reminders_enabled') : ($s_enabled_raw === '1' || $s_enabled_raw === 'true');
$s_custom_json = SettingsRepository::get('reminder_custom_dates') ?? '[]';
$s_custom_arr = json_decode($s_custom_json, true);
if (!is_array($s_custom_arr)) { $s_custom_arr = []; }
$s_due_days = (int)(SettingsRepository::get('invoice_due_days') ?? (string)Config::get('settings.invoice_due_days', 30));
$s_default_vat = (string)(SettingsRepository::get('default_vat_rate') ?? '');
$s_default_irpf = (string)(SettingsRepository::get('default_irpf_rate') ?? '');
?>
<section>
  <h1>Ajustes</h1>
  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Notificaciones -->
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Notificaciones</h3>
      </div>
      <form method="post">
        <input type="hidden" name="save_settings" value="1" />
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <span style="font-weight:600;color:var(--gray-800)">Activar recordatorios por email</span>
          <input type="hidden" name="reminders_enabled" id="settings-reminders-enabled" value="<?= $s_enabled ? '1' : '0' ?>" />
          <button type="button" id="settings-toggle-btn" class="toggle-switch <?= $s_enabled ? 'active' : '' ?>" aria-label="Activar recordatorios"></button>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div>
            <label>Email de notificación</label>
            <input type="email" name="notify_email" id="settings-notify" value="<?= htmlspecialchars($s_notify) ?>" placeholder="tucorreo@dominio.com" />
            <p id="notify-hint" class="form-hint" style="display:none;margin-top:4px">Introduce un email para poder activar los recordatorios.</p>
          </div>
          <div>
            <label>Zona horaria</label>
            <input type="text" name="timezone" value="<?= htmlspecialchars($s_tz) ?>" placeholder="Europe/Madrid" />
          </div>
        </div>

        <!-- Fechas personalizadas eliminadas: se gestionan desde Notificaciones -->

        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn">Guardar ajustes</button>
        </div>
      </form>
    </div>

    <!-- Facturación y pruebas -->
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Facturación</h3>
      </div>
      <form method="post" style="margin-bottom:12px">
        <input type="hidden" name="save_settings" value="1" />
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
          <div>
            <label>Plazo por defecto (días)</label>
            <input type="number" name="invoice_due_days" min="1" max="90" value="<?= (int)$s_due_days ?>" />
          </div>
          <div>
            <label>IVA habitual</label>
            <input type="text" name="default_vat_rate" value="<?= htmlspecialchars($s_default_vat) ?>" placeholder="21" />
          </div>
          <div>
            <label>IRPF habitual</label>
            <input type="text" name="default_irpf_rate" value="<?= htmlspecialchars($s_default_irpf) ?>" placeholder="15" />
          </div>
        </div>
        <p class="form-hint">Estos valores se usarán como base en nuevas facturas, presupuestos y clientes. Si los dejas vacíos, tendrás que escogerlos manualmente.</p>
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-secondary">Guardar</button>
        </div>
      </form>

      <div class="section-header" style="margin-top:4px">
        <h3 class="section-title">Email de prueba</h3>
      </div>
      <form method="post">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <label>Email destino</label>
        <input type="email" name="test_email" required value="<?= htmlspecialchars($s_notify) ?>" placeholder="tu@correo.com" />
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn">Enviar prueba</button>
        </div>
      </form>
      <p class="form-hint">SMTP se configura en `.env`. Aquí defines la zona horaria, destinatario y preferencias.</p>

      <div class="section-header" style="margin-top:8px">
        <h3 class="section-title">Vista previa de recordatorio</h3>
      </div>
      <form method="post">
        <input type="hidden" name="preview_reminder" value="1" />
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <p class="form-hint">Se enviará un ejemplo a tu Email de notificación.</p>
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-secondary">Enviar vista previa</button>
        </div>
      </form>
    </div>
  </div>
</section>
<script>
(function(){
  const input = document.getElementById('settings-reminders-enabled');
  const btn = document.getElementById('settings-toggle-btn');
  const notify = document.getElementById('settings-notify');
  const hint = document.getElementById('notify-hint');

  function isEmailReady(v){ return (v||'').trim() !== ''; }

  function updateNotifyState(){
    const ok = isEmailReady(notify ? notify.value : '');
    if (!btn) return;
    if (ok) {
      btn.disabled = false;
      if (hint) hint.style.display = 'none';
    } else {
      btn.disabled = true;
      if (hint) hint.style.display = 'block';
      if (input) { input.value = '0'; btn.classList.remove('active'); }
    }
  }

  if (btn && input) {
    btn.addEventListener('click', function(){
      const on = input.value === '1';
      const next = on ? '0' : '1';
      input.value = next;
      btn.classList.toggle('active', next === '1');
    });
  }

  if (notify) {
    notify.addEventListener('input', updateNotifyState);
    notify.addEventListener('change', updateNotifyState);
  }

  updateNotifyState();
})();
</script>
