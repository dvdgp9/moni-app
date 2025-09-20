<?php
use Moni\Repositories\ClientsRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

$flashAll = Flash::getAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido. Inténtalo de nuevo.');
        header('Location: /?page=clients');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        ClientsRepository::delete($id);
        Flash::add('success', 'Cliente eliminado.');
        header('Location: /?page=clients');
        exit;
    }
}

$clients = ClientsRepository::all();
?>
<section>
  <h1>Clientes</h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert" style="<?= $type==='error'?'background:#FEE2E2;border-color:#FCA5A5;color:#991B1B':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <p>
    <a href="/?page=client_form" class="btn">+ Nuevo cliente</a>
  </p>

  <?php if (empty($clients)): ?>
    <p>No hay clientes todavía.</p>
  <?php else: ?>
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>NIF</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars($c['nif'] ?? '') ?></td>
              <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
              <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
              <td class="table-actions">
                <a href="/?page=client_form&id=<?= (int)$c['id'] ?>" class="btn">Editar</a>
                <form method="post" onsubmit="return confirm('¿Eliminar cliente?');">
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
