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
?>
<section>
  <h1>Ajustes</h1>
  <?php if ($flash): ?>
    <div class="alert"><?= $flash ?></div>
  <?php endif; ?>

  <div class="grid-2">
    <div>
      <h3>SMTP (de .env)</h3>
      <ul class="kv">
        <li><span>Host</span><span><?= htmlspecialchars(Config::get('mail.host')) ?></span></li>
        <li><span>Puerto</span><span><?= (int)Config::get('mail.port') ?></span></li>
        <li><span>Usuario</span><span><?= htmlspecialchars((string)Config::get('mail.username')) ?></span></li>
        <li><span>Encriptación</span><span><?= htmlspecialchars((string)Config::get('mail.encryption')) ?></span></li>
        <li><span>Remitente</span><span><?= htmlspecialchars((string)Config::get('mail.from_address')) ?> (<?= htmlspecialchars((string)Config::get('mail.from_name')) ?>)</span></li>
      </ul>

      <h3>Ajustes de recordatorios</h3>
      <form method="post">
        <input type="hidden" name="save_settings" value="1" />

        <label>
          <input type="checkbox" name="reminders_enabled" <?= $s_enabled ? 'checked' : '' ?> />
          Activar recordatorios por email
        </label>

        <label>Email de notificación</label>
        <input type="email" name="notify_email" value="<?= htmlspecialchars($s_notify) ?>" placeholder="tucorreo@dominio.com" />

        <label>Zona horaria</label>
        <input type="text" name="timezone" value="<?= htmlspecialchars($s_tz) ?>" placeholder="Europe/Madrid" />

        <label>Fechas personalizadas (YYYY-MM-DD, una por línea o JSON)</label>
        <textarea name="custom_dates" rows="5" placeholder="2025-02-15&#10;2025-09-30"><?php foreach ($s_custom_arr as $d) { echo htmlspecialchars($d) . "\n"; } ?></textarea>

        <button type="submit">Guardar ajustes</button>
      </form>
    </div>
    <div>
      <h3>Enviar email de prueba</h3>
      <form method="post">
        <label>Email destino</label>
        <input type="email" name="test_email" required value="<?= htmlspecialchars($s_notify) ?>" placeholder="tu@correo.com" />
        <button type="submit">Enviar prueba</button>
      </form>
      <p class="hint">SMTP se configura en `.env`. Los destinatarios y preferencias se guardan en BD.</p>
    </div>
  </div>
</section>
