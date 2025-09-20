<?php
use Moni\Repositories\InvoicesRepository;
use Moni\Support\Flash;

$flashAll = Flash::getAll();
$invoices = InvoicesRepository::all();
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

  <p>
    <a href="/?page=invoice_form" class="btn">+ Nueva factura</a>
  </p>

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
              <td><?= htmlspecialchars($i['status']) ?></td>
              <td><?= htmlspecialchars($i['issue_date']) ?></td>
              <td><?= htmlspecialchars($i['due_date'] ?? '') ?></td>
              <td class="table-actions">
                <a class="btn" href="/?page=invoice_form&id=<?= (int)$i['id'] ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
