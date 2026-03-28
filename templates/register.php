<?php
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;
use Moni\Services\OnboardingService;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
        moni_redirect(route_path('register'));
    }
    try {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::add('error', 'Email inválido.');
            moni_redirect(route_path('register'));
        }
        if ($password === '' || $password !== $password2) {
            Flash::add('error', 'Las contraseñas no coinciden.');
            moni_redirect(route_path('register'));
        }
        if (UsersRepository::existsByEmail($email)) {
            Flash::add('error', 'Ya existe un usuario con ese email.');
            moni_redirect(route_path('register'));
        }
        $uid = UsersRepository::create($email, $password, $name !== '' ? $name : null);
        AuthService::login((int)$uid);
        OnboardingService::setStep((int)$uid, 1);
        Flash::add('success', 'Bienvenido a Moni.');
        moni_redirect(route_path('onboarding'));
    } catch (Throwable $e) {
        error_log('[register] ' . $e->getMessage());
        Flash::add('error', 'No se pudo completar el registro. Inténtalo de nuevo.');
    }
    moni_redirect(route_path('register'));
}
?>
<section class="auth-shell fade-in-up">
  <div class="login-container">
    <div class="login-card login-card-public">
      <span class="auth-kicker">Registro beta</span>
      <h1 class="login-title">Crea tu cuenta gratis</h1>
      <p class="login-subtitle">La beta es gratuita mientras construimos la plataforma. A cambio, te pedimos feedback y avisos de errores.</p>

      <?php if (!empty($flashAll)): ?>
        <?php foreach ($flashAll as $type => $messages): ?>
          <?php foreach ($messages as $msg): ?>
            <div class="alert <?= $type==='error'?'error':'' ?>"><?php echo htmlspecialchars($msg); ?></div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <label>Nombre (opcional)</label>
        <input type="text" name="name" placeholder="Tu nombre" />

        <label>Email</label>
        <input type="email" name="email" required placeholder="tu@email.com" />

        <label>Contraseña</label>
        <input type="password" name="password" required placeholder="••••••••" />

        <label>Repite la contraseña</label>
        <input type="password" name="password2" required placeholder="••••••••" />

        <button type="submit" class="btn" style="width:100%;margin-top:1rem">Crear cuenta</button>
      </form>

      <p style="margin-top:1rem;font-size:0.9rem;color:var(--gray-600)">
        ¿Ya tienes cuenta? <a href="<?= route_path('login') ?>">Entrar</a>
      </p>
    </div>
  </div>
</section>
