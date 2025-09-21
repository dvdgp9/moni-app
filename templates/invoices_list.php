<?php
use Moni\Repositories\InvoicesRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Services\InvoiceNumberingService;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Database;

$flashAll = Flash::getAll();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$status = in_array($status, ['draft','issued','paid','cancelled'], true) ? $status : null;

// Actions: issue, paid, cancelled, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    header('Location: /?page=invoices');
    exit;
  }
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['_action'] ?? '';
  if ($id > 0 && $action) {
    try {
      if ($action === 'issue') {
        $inv = InvoicesRepository::find($id);
        if ($inv && $inv['status'] === 'draft') {
          $items = InvoiceItemsRepository::byInvoice($id);
          if (empty($items)) {
            Flash::add('error', 'No puedes emitir una factura sin líneas.');
          } else {
            $num = InvoiceNumberingService::issue($id, $inv['issue_date']);
            Flash::add('success', 'Factura emitida con número ' . $num);
          }
        }
      } elseif ($action === 'paid') {
        InvoicesRepository::setStatus($id, 'paid');
        Flash::add('success', 'Factura marcada como Pagada.');
      } elseif ($action === 'cancelled') {
        InvoicesRepository::setStatus($id, 'cancelled');
        Flash::add('success', 'Factura Cancelada.');
      } elseif ($action === 'delete') {
        // borrar líneas y factura
        InvoiceItemsRepository::deleteByInvoice($id);
        $pdo = Database::pdo();
        $del = $pdo->prepare('DELETE FROM invoices WHERE id = :id');
        $del->execute([':id' => $id]);
        Flash::add('success', 'Factura eliminada.');
      }
    } catch (Throwable $e) {
      Flash::add('error', 'Acción fallida: ' . $e->getMessage());
    }
    // redirect back to avoid resubmission
    header('Location: /?page=invoices');
    exit;
  }
}

$invoices = InvoicesRepository::all($q, $status);

function status_es(string $s): string {
  return match ($s) {
    'draft' => 'Borrador',
    'issued' => 'Emitida',
    'paid' => 'Pagada',
    'cancelled' => 'Cancelada',
    default => $s,
  };
}
?>
<section>
  <h1>Facturas</h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="get" class="card" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="page" value="invoices" />
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por número o cliente" style="flex:1" />
    <select name="status" style="padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff">
      <option value="">Todos los estados</option>
      <option value="draft" <?= $status==='draft'?'selected':'' ?>>Borrador</option>
      <option value="issued" <?= $status==='issued'?'selected':'' ?>>Emitida</option>
      <option value="paid" <?= $status==='paid'?'selected':'' ?>>Pagada</option>
      <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelada</option>
    </select>
    <button type="submit" class="btn">Filtrar</button>
    <?php if ($q!=='' || $status): ?>
      <a href="/?page=invoices" class="btn btn-secondary">Limpiar</a>
    <?php endif; ?>
    <a href="/?page=invoice_form" class="btn" style="margin-left:auto">+ Nueva factura</a>
  </form>

  <?php if (empty($invoices)): ?>
    <p>No hay facturas todavía.</p>
  <?php else: ?>
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Nº</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Vencimiento</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $i): ?>
            <tr>
              <td><?= htmlspecialchars($i['invoice_number'] ?: '—') ?></td>
              <td><?= htmlspecialchars($i['client_name'] ?? '') ?></td>
              <td><?= htmlspecialchars(status_es($i['status'])) ?></td>
              <td><?= htmlspecialchars($i['issue_date']) ?></td>
              <td><?= htmlspecialchars($i['due_date'] ?? '') ?></td>
              <td class="table-actions">
                <a class="btn" href="/?page=invoice_form&id=<?= (int)$i['id'] ?>">Editar</a>
                <a class="btn btn-secondary" href="/?page=invoice_pdf&id=<?= (int)$i['id'] ?>" target="_blank" rel="noopener">PDF</a>
                <?php if ($i['status'] === 'draft'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="issue" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn">Emitir</button>
                  </form>
                <?php elseif ($i['status'] === 'issued'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="paid" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn">Marcar pagada</button>
                  </form>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Cancelar la factura?');">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="cancelled" />
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
                    <button type="submit" class="btn btn-danger">Cancelar</button>
                  </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar la factura?');">
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
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
