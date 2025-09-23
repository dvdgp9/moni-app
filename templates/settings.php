<?php
use Moni\Support\Config;
use Moni\Services\EmailService;
use Moni\Repositories\SettingsRepository;

$flash = null;

// Guardado de ajustes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $notify = trim((string)($_POST['notify_email'] ?? ''));
    $tz = trim((string)($_POST['timezone'] ?? 'Europe/Madrid'));
    $enabled = isset($_POST['reminders_enabled']) ? '1' : '0';
    $customDatesRaw = trim((string)($_POST['custom_dates'] ?? ''));
    // Permitir formato líneas o JSON
    $custom = [];
    if ($customDatesRaw !== '') {
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
        SettingsRepository::set('notify_email', $notify);
        SettingsRepository::set('timezone', $tz);
        SettingsRepository::set('reminders_enabled', $enabled);
        SettingsRepository::set('reminder_custom_dates', json_encode($custom));
        // Guardar plazo por defecto (días) si se envía
        if (isset($_POST['invoice_due_days'])) {
            $days = (int)$_POST['invoice_due_days'];
            if ($days > 0 && $days <= 90) {
                SettingsRepository::set('invoice_due_days', (string)$days);
            }
        }
        $flash = 'Ajustes guardados correctamente.';
    } catch (Throwable $e) {
        $flash = 'Error guardando ajustes: ' . htmlspecialchars($e->getMessage());
    }
}

// Envío de prueba
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $to = trim($_POST['test_email']);
    try {
        $ok = EmailService::sendTest($to);
        $flash = $ok ? 'Email de prueba enviado a ' . htmlspecialchars($to) : 'Error al enviar el email de prueba';
    } catch (Throwable $e) {
        $flash = 'Excepción enviando email: ' . htmlspecialchars($e->getMessage());
    }
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
?>
<section>
  <h1>Ajustes</h1>
  <?php if ($flash): ?>
    <div class="alert"><?= $flash ?></div>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Notificaciones -->
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Notificaciones</h3>
      </div>
      <form method="post">
        <input type="hidden" name="save_settings" value="1" />

        <label style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="reminders_enabled" <?= $s_enabled ? 'checked' : '' ?> />
          Activar recordatorios por email
        </label>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div>
            <label>Email de notificación</label>
            <input type="email" name="notify_email" value="<?= htmlspecialchars($s_notify) ?>" placeholder="tucorreo@dominio.com" />
          </div>
          <div>
            <label>Zona horaria</label>
            <input type="text" name="timezone" value="<?= htmlspecialchars($s_tz) ?>" placeholder="Europe/Madrid" />
          </div>
        </div>

        <details style="margin:8px 0">
          <summary style="cursor:pointer;color:var(--gray-600);font-size:0.9rem">Avanzado</summary>
          <div style="margin-top:8px">
            <label>Fechas personalizadas (YYYY-MM-DD, una por línea o JSON)</label>
            <textarea name="custom_dates" rows="4" placeholder="2025-02-15&#10;2025-09-30"><?php foreach ($s_custom_arr as $d) { echo htmlspecialchars($d) . "\n"; } ?></textarea>
            <p class="form-hint">Compatibilidad con versiones anteriores; lo normal es usar la sección de Notificaciones.</p>
          </div>
        </details>

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
        <label>Plazo por defecto (días)</label>
        <input type="number" name="invoice_due_days" min="1" max="90" value="<?= (int)$s_due_days ?>" />
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-secondary">Guardar</button>
        </div>
      </form>

      <div class="section-header" style="margin-top:4px">
        <h3 class="section-title">Email de prueba</h3>
      </div>
      <form method="post">
        <label>Email destino</label>
        <input type="email" name="test_email" required value="<?= htmlspecialchars($s_notify) ?>" placeholder="tu@correo.com" />
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn">Enviar prueba</button>
        </div>
      </form>
      <p class="form-hint">SMTP se configura en `.env`. Aquí defines la zona horaria, destinatario y preferencias.</p>
    </div>
  </div>
</section>
