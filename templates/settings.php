<?php
use Moni\Support\Config;
use Moni\Services\EmailService;

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $to = trim($_POST['test_email']);
    try {
        $ok = EmailService::sendTest($to);
        $flash = $ok ? 'Email de prueba enviado a ' . htmlspecialchars($to) : 'Error al enviar el email de prueba';
    } catch (Throwable $e) {
        $flash = 'Excepción enviando email: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<section>
  <h1>Ajustes</h1>
  <?php if ($flash): ?>
    <div class="alert"><?= $flash ?></div>
  <?php endif; ?>

  <div class="grid-2">
    <div>
      <h3>SMTP (desde .env)</h3>
      <ul class="kv">
        <li><span>Host</span><span><?= htmlspecialchars(Config::get('mail.host')) ?></span></li>
        <li><span>Puerto</span><span><?= (int)Config::get('mail.port') ?></span></li>
        <li><span>Usuario</span><span><?= htmlspecialchars((string)Config::get('mail.username')) ?></span></li>
        <li><span>Encriptación</span><span><?= htmlspecialchars((string)Config::get('mail.encryption')) ?></span></li>
        <li><span>Remitente</span><span><?= htmlspecialchars((string)Config::get('mail.from_address')) ?> (<?= htmlspecialchars((string)Config::get('mail.from_name')) ?>)</span></li>
      </ul>
    </div>
    <div>
      <h3>Enviar email de prueba</h3>
      <form method="post">
        <label>Email destino</label>
        <input type="email" name="test_email" required placeholder="tu@correo.com" />
        <button type="submit">Enviar prueba</button>
      </form>
      <p class="hint">Los valores se leen de .env por ahora. Más adelante se podrán guardar en BD.</p>
    </div>
  </div>
</section>
