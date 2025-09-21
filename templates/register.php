<?php
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Flash::add('error', 'Email inválido');
        header('Location: /?page=register');
        exit;
    }
    if ($password === '' || $password !== $password2) {
        Flash::add('error', 'Las contraseñas no coinciden');
        header('Location: /?page=register');
        exit;
    }
    if (UsersRepository::existsByEmail($email)) {
        Flash::add('error', 'Ya existe un usuario con ese email');
        header('Location: /?page=register');
        exit;
    }
    $uid = UsersRepository::create($email, $password, $name !== '' ? $name : null);
    AuthService::login((int)$uid);
    Flash::add('success', 'Bienvenido a Moni');
    header('Location: /?page=dashboard');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Crear cuenta - Moni</title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=3">
</head>
<body>
  <div class="login-container fade-in-up">
    <div class="login-card">
      <h1 class="login-title">Crear cuenta</h1>
      <p class="login-subtitle">Empieza a usar Moni en segundos</p>

      <?php if (!empty($flashAll)): ?>
        <?php foreach ($flashAll as $type => $messages): ?>
          <?php foreach ($messages as $msg): ?>
            <div class="alert <?= $type==='error'?'error':'' ?>"><?php echo htmlspecialchars($msg); ?></div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="post">
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
        ¿Ya tienes cuenta? <a href="/?page=login">Entrar</a>
      </p>
    </div>
  </div>
</body>
</html>
