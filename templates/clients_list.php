<?php
use Moni\Repositories\ClientsRepository;
use Moni\Repositories\InvoicesRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;

$flashAll = Flash::getAll();

// Read search query
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido. Inténtalo de nuevo.');
        header('Location: /?page=clients');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $cnt = InvoicesRepository::countByClient($id);
        if ($cnt > 0) {
            Flash::add('error', 'No se puede eliminar el cliente: tiene ' . $cnt . ' factura(s) asociada(s).');
        } else {
            ClientsRepository::delete($id);
            Flash::add('success', 'Cliente eliminado.');
        }
        header('Location: /?page=clients');
        exit;
    }
}

$clients = ClientsRepository::all($q);
?>
<section>
  <h1>Clientes</h1>
  <p style="margin-top:-6px;margin-bottom:14px;color:var(--gray-600)">
    Gestiona tus clientes para facturar más rápido. Desde aquí puedes crear, editar y eliminar
    clientes. Si un cliente tiene facturas, no se podrá eliminar por seguridad.
  </p>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="get" class="card" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="page" value="clients" />
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nombre, NIF, email o teléfono" style="flex:1" />
    <button type="submit" class="btn">Buscar</button>
    <?php if ($q !== ''): ?>
      <a href="/?page=clients" class="btn btn-secondary">Limpiar</a>
    <?php endif; ?>
    <a href="/?page=client_form" class="btn" style="margin-left:auto">+ Nuevo cliente</a>
  </form>

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
            <?php $invCount = InvoicesRepository::countByClient((int)$c['id']); ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars($c['nif'] ?? '') ?></td>
              <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
              <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
              <td class="table-actions">
                <a href="/?page=client_form&id=<?= (int)$c['id'] ?>" class="btn">Editar</a>
                <form method="post" onsubmit="return canDeleteClient(this, <?= (int)$invCount ?>);">
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
    <script>
    function canDeleteClient(form, count) {
      if (count > 0) {
        alert('No se puede eliminar el cliente: tiene ' + count + ' factura(s) asociada(s).');
        return false;
      }
      return confirm('¿Eliminar cliente?');
    }
    </script>
  <?php endif; ?>
</section>
