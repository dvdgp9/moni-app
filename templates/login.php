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
<section>
  <h1>Entrar</h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" class="card" style="max-width:520px">
    <label>Email</label>
    <input type="email" name="email" required />

    <label>Contraseña</label>
    <input type="password" name="password" required />

    <button type="submit">Entrar</button>
  </form>

  <p class="hint">Si aún no tienes usuario, crea uno en la tabla <code>users</code> con una contraseña hash de PHP (password_hash).</p>
</section>
