<?php
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $user = UsersRepository::findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        AuthService::login((int)$user['id']);
        $to = $_SESSION['_intended'] ?? '/?page=dashboard';
        unset($_SESSION['_intended']);
        header('Location: ' . $to);
        exit;
    }
    Flash::add('error', 'Credenciales inválidas');
    header('Location: /?page=login');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Entrar - Moni</title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=3">
</head>
<body>
  <div class="login-container fade-in-up">
    <div class="login-card">
      <h1 class="login-title">Moni</h1>
      <p class="login-subtitle">Gestión financiera para autónomos</p>

      <?php if (!empty($flashAll)): ?>
        <?php foreach ($flashAll as $type => $messages): ?>
          <?php foreach ($messages as $msg): ?>
            <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="post">
        <label>Email</label>
        <input type="email" name="email" required placeholder="tu@email.com" />

        <label>Contraseña</label>
        <input type="password" name="password" required placeholder="••••••••" />

        <button type="submit" class="btn" style="width:100%;margin-top:1rem">Entrar</button>
      </form>

      <p style="margin-top:1rem;font-size:0.9rem;color:var(--gray-600)">
        ¿No tienes cuenta? <a href="/?page=register">Crear una cuenta</a>
      </p>
    </div>
  </div>
</body>
</html>
