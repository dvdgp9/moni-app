<?php
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        moni_redirect(route_path('login'));
    }
    try {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        $user = UsersRepository::findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            AuthService::login((int)$user['id'], $remember);
            $to = $_SESSION['_intended'] ?? route_path('dashboard');
            unset($_SESSION['_intended']);
            moni_redirect($to);
        }
        Flash::add('error', 'Credenciales inválidas.');
    } catch (Throwable $e) {
        error_log('[login] ' . $e->getMessage());
        Flash::add('error', 'No se pudo iniciar sesión. Inténtalo de nuevo.');
    }
    moni_redirect(route_path('login'));
}
?>
<section class="auth-shell fade-in-up">
  <div class="login-container">
    <div class="login-card login-card-public">
      <span class="auth-kicker">Acceso</span>
      <h1 class="login-title">Entra en tu espacio de trabajo</h1>
      <p class="login-subtitle">Gestiona clientes, facturas, gastos y recordatorios desde una zona separada de la web pública.</p>

      <?php if (!empty($flashAll)): ?>
        <?php foreach ($flashAll as $type => $messages): ?>
          <?php foreach ($messages as $msg): ?>
            <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <label>Email</label>
        <input type="email" name="email" required placeholder="tu@email.com" />

        <label>Contraseña</label>
        <input type="password" name="password" required placeholder="••••••••" />

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.5rem">
          <label style="display:flex;align-items:center;gap:6px;margin:0;color:var(--gray-700);font-weight:500;font-size:0.8rem;white-space:nowrap">
            <input type="checkbox" name="remember" value="1" />
            Recordarme (30 días)
          </label>
          <button type="submit" class="btn">Entrar</button>
        </div>
      </form>

      <p style="margin-top:1rem;font-size:0.9rem;color:var(--gray-600)">
        ¿No tienes cuenta? <a href="<?= route_path('register') ?>">Crear una cuenta</a>
      </p>
    </div>
  </div>
</section>
