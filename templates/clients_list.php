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
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr>
            <th style="text-align:left;border-bottom:1px solid #E2E8F0;padding:8px">Nombre</th>
            <th style="text-align:left;border-bottom:1px solid #E2E8F0;padding:8px">NIF</th>
            <th style="text-align:left;border-bottom:1px solid #E2E8F0;padding:8px">Email</th>
            <th style="text-align:left;border-bottom:1px solid #E2E8F0;padding:8px">Teléfono</th>
            <th style="text-align:left;border-bottom:1px solid #E2E8F0;padding:8px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
            <tr>
              <td style="padding:8px;border-bottom:1px solid #F1F5F9;"><?= htmlspecialchars($c['name']) ?></td>
              <td style="padding:8px;border-bottom:1px solid #F1F5F9;"><?= htmlspecialchars($c['nif'] ?? '') ?></td>
              <td style="padding:8px;border-bottom:1px solid #F1F5F9;"><?= htmlspecialchars($c['email'] ?? '') ?></td>
              <td style="padding:8px;border-bottom:1px solid #F1F5F9;"><?= htmlspecialchars($c['phone'] ?? '') ?></td>
              <td style="padding:8px;border-bottom:1px solid #F1F5F9;display:flex;gap:8px">
                <a href="/?page=client_form&id=<?= (int)$c['id'] ?>">Editar</a>
                <form method="post" onsubmit="return confirm('¿Eliminar cliente?');">
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button type="submit" style="background:#EF4444" class="btn">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
