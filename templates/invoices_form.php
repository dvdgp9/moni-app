<?php
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Repositories\ClientsRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;
use Moni\Services\InvoiceService;
use Moni\Support\Config;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$errors = [];

// Load clients for select
$clients = ClientsRepository::all();

// Defaults
$defaultDays = (int) Config::get('settings.invoice_due_days', 30);
$defaultDays = ($defaultDays > 0 && $defaultDays <= 90) ? $defaultDays : 30;
$invoice = [
  'client_id' => $clients[0]['id'] ?? 0,
  'issue_date' => date('Y-m-d'),
  'due_date' => date('Y-m-d', strtotime('+' . $defaultDays . ' days')),
  'notes' => '',
];
$due_terms = ($defaultDays === 15 ? '15' : ($defaultDays === 30 ? '30' : 'custom')); // '15' | '30' | 'custom'
$items = [[
  'description' => '',
  'quantity' => '1',
  'unit_price' => '0.00',
  'vat_rate' => '21',
  'irpf_rate' => '15',
]];

if ($editing) {
  $found = InvoicesRepository::find($id);
  if ($found) {
    $invoice = array_merge($invoice, $found);
    $items = InvoiceItemsRepository::byInvoice($id);
    // Preseleccionar due_terms según diferencia con issue_date
    if (!empty($invoice['due_date']) && !empty($invoice['issue_date'])) {
      $issue = strtotime($invoice['issue_date']);
      $due = strtotime($invoice['due_date']);
      $diffDays = (int) round(($due - $issue) / 86400);
      if ($diffDays === 15) { $due_terms = '15'; }
      elseif ($diffDays === 30) { $due_terms = '30'; }
      else { $due_terms = 'custom'; }
    }
    if (empty($items)) {
      $items = [[
        'description' => '', 'quantity' => '1', 'unit_price' => '0.00', 'vat_rate' => '21', 'irpf_rate' => '15'
      ]];
    }
  }
}

function parse_items_from_post(): array {
  $out = [];
  $desc = $_POST['item_description'] ?? [];
  $qty = $_POST['item_quantity'] ?? [];
  $price = $_POST['item_unit_price'] ?? [];
  $vat = $_POST['item_vat_rate'] ?? [];
  $irpf = $_POST['item_irpf_rate'] ?? [];
  $n = max(count($desc), count($qty), count($price), count($vat), count($irpf));
  for ($i=0; $i<$n; $i++) {
    $d = trim((string)($desc[$i] ?? ''));
    $q = (string)($qty[$i] ?? '1');
    $p = (string)($price[$i] ?? '0');
    $v = (string)($vat[$i] ?? '21');
    $r = (string)($irpf[$i] ?? '15');
    if ($d === '' && (float)$p === 0.0) { continue; }
    $out[] = [
      'description' => $d,
      'quantity' => $q,
      'unit_price' => $p,
      'vat_rate' => $v,
      'irpf_rate' => $r,
    ];
  }
  return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    header('Location: /?page=invoices');
    exit;
  }
  $invoice['client_id'] = (int)($_POST['client_id'] ?? 0);
  $invoice['issue_date'] = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
  $due_terms = ($_POST['due_terms'] ?? '30');
  $raw_due = trim((string)($_POST['due_date'] ?? ''));
  if ($due_terms === '15' || $due_terms === '30') {
    $base = strtotime($invoice['issue_date']);
    if ($base !== false) {
      $days = (int)$due_terms;
      $invoice['due_date'] = date('Y-m-d', strtotime("+{$days} days", $base));
    } else {
      $invoice['due_date'] = null;
    }
  } else {
    $invoice['due_date'] = $raw_due !== '' ? $raw_due : null;
  }
  $invoice['notes'] = trim((string)($_POST['notes'] ?? ''));
  $items = parse_items_from_post();

  if ($invoice['client_id'] <= 0) {
    $errors['client_id'] = 'Selecciona un cliente';
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice['issue_date'])) {
    $errors['issue_date'] = 'Fecha de factura inválida';
  }
  if ($invoice['due_date'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice['due_date'])) {
    $errors['due_date'] = 'Fecha de vencimiento inválida';
  }
  if (empty($items)) {
    $errors['items'] = 'Añade al menos una línea';
  }

  if (empty($errors)) {
    if ($editing) {
      InvoicesRepository::updateDraft($id, $invoice);
      InvoiceItemsRepository::deleteByInvoice($id);
      InvoiceItemsRepository::insertMany($id, $items);
      Flash::add('success', 'Factura guardada como borrador.');
    } else {
      $newId = InvoicesRepository::createDraft($invoice);
      InvoiceItemsRepository::insertMany($newId, $items);
      Flash::add('success', 'Factura creada como borrador.');
      header('Location: /?page=invoice_form&id=' . $newId);
      exit;
    }
  }
}

$totals = InvoiceService::computeTotals($items);
?>
<section>
  <h1><?= $editing ? 'Editar factura' : 'Nueva factura' ?></h1>

  <?php if (!empty($errors)): ?>
    <div class="alert error">Por favor, corrige los errores marcados.</div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

    <div class="grid-2">
      <div>
        <label>Cliente *</label>
        <select name="client_id" required style="width:100%;padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff">
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$invoice['client_id']===(int)$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['client_id'])): ?><div class="alert error"><?= htmlspecialchars($errors['client_id']) ?></div><?php endif; ?>
      </div>
      <div></div>
    </div>

    <div class="grid-2">
      <div>
        <label>Fecha de la factura *</label>
        <input type="date" name="issue_date" value="<?= htmlspecialchars($invoice['issue_date']) ?>" required id="issue_date" />
        <?php if (!empty($errors['issue_date'])): ?><div class="alert error"><?= htmlspecialchars($errors['issue_date']) ?></div><?php endif; ?>
      </div>
      <div>
        <label>Vencimiento</label>
        <div class="grid-2">
          <select name="due_terms" id="due_terms" style="width:100%;padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff">
            <option value="15" <?= $due_terms==='15'?'selected':'' ?>>15 días</option>
            <option value="30" <?= $due_terms==='30'?'selected':'' ?>>30 días</option>
            <option value="custom" <?= $due_terms==='custom'?'selected':'' ?>>Personalizado</option>
          </select>
          <input type="date" name="due_date" value="<?= htmlspecialchars((string)$invoice['due_date']) ?>" id="due_date" />
        </div>
        <?php if (!empty($errors['due_date'])): ?><div class="alert error"><?= htmlspecialchars($errors['due_date']) ?></div><?php endif; ?>
      </div>
    </div>

    <label>Notas</label>
    <textarea name="notes" rows="3" placeholder="Observaciones, forma de pago, etc."><?= htmlspecialchars($invoice['notes']) ?></textarea>

    <h3>Líneas</h3>
    <div id="items">
      <?php foreach ($items as $idx => $it): ?>
        <div class="card" style="margin-bottom:8px; position:relative">
          <?php if ($idx > 0): ?>
            <button type="button" class="btn btn-danger" data-role="remove-line" title="Eliminar línea" style="position:absolute;top:8px;right:8px;padding:6px 10px">🗑️</button>
          <?php endif; ?>
          <div class="grid-2">
            <div>
              <label>Descripción</label>
              <input type="text" name="item_description[]" value="<?= htmlspecialchars((string)$it['description']) ?>" />
            </div>
            <div class="grid-2">
              <div>
                <label>Cantidad</label>
                <input type="text" name="item_quantity[]" value="<?= htmlspecialchars((string)$it['quantity']) ?>" />
              </div>
              <div>
                <label>Precio</label>
                <input type="text" name="item_unit_price[]" value="<?= htmlspecialchars((string)$it['unit_price']) ?>" />
              </div>
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>IVA %</label>
              <input type="text" name="item_vat_rate[]" value="<?= htmlspecialchars((string)$it['vat_rate']) ?>" />
            </div>
            <div>
              <label>IRPF %</label>
              <input type="text" name="item_irpf_rate[]" value="<?= htmlspecialchars((string)$it['irpf_rate']) ?>" />
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($errors['items'])): ?><div class="alert error"><?= htmlspecialchars($errors['items']) ?></div><?php endif; ?>
    <button type="button" class="btn" id="add_line">+ Añadir línea</button>

    <div class="card" style="margin-top:12px">
      <div class="grid-2">
        <div><strong>Base imponible:</strong> <span id="t_base"><?= number_format($totals['base'], 2) ?></span> €</div>
        <div><strong>IVA:</strong> <span id="t_iva"><?= number_format($totals['iva'], 2) ?></span> €</div>
        <div><strong>IRPF:</strong> <span id="t_irpf"><?= number_format($totals['irpf'], 2) ?></span> €</div>
        <div><strong>Total:</strong> <span id="t_total"><?= number_format($totals['total'], 2) ?></span> €</div>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:10px;justify-content:flex-end">
      <button type="submit" class="btn">Guardar borrador</button>
      <a class="btn btn-secondary" href="/?page=invoices">Cancelar</a>
      <?php if ($editing): ?>
        <form method="post" action="/?page=invoices" onsubmit="return confirm('¿Eliminar la factura?');" style="margin-left:auto">
          <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
          <input type="hidden" name="_action" value="delete" />
          <input type="hidden" name="id" value="<?= (int)$id ?>" />
          <button type="submit" class="btn btn-danger">Eliminar</button>
        </form>
      <?php endif; ?>
    </div>
  </form>
</section>

<script>
(function(){
  const addBtn = document.getElementById('add_line');
  const items = document.getElementById('items');
  const issue = document.getElementById('issue_date');
  const due = document.getElementById('due_date');
  const terms = document.getElementById('due_terms');

  function applyTerms(){
    const mode = terms ? terms.value : '30';
    if (!issue || !due) return;
    if (mode === '15' || mode === '30') {
      // calcular vista
      const base = new Date(issue.value);
      if (!isNaN(base.getTime())) {
        const days = parseInt(mode, 10);
        const d = new Date(base.getTime());
        d.setDate(d.getDate() + days);
        const iso = d.toISOString().slice(0,10);
        due.value = iso;
      }
      due.readOnly = true;
      due.style.opacity = 0.8;
    } else {
      due.readOnly = false;
      due.style.opacity = 1;
    }
  }

  function addLine(){
    const div = document.createElement('div');
    div.className = 'card';
    div.style.marginBottom = '8px';
    div.style.position = 'relative';
    div.innerHTML = `
      <button type="button" class="btn btn-danger" data-role="remove-line" title="Eliminar línea" style="position:absolute;top:8px;right:8px;padding:6px 10px">🗑️</button>
      <div class="grid-2">
        <div>
          <label>Descripción</label>
          <input type="text" name="item_description[]" />
        </div>
        <div class="grid-2">
          <div>
            <label>Cantidad</label>
            <input type="text" name="item_quantity[]" value="1" />
          </div>
          <div>
            <label>Precio</label>
            <input type="text" name="item_unit_price[]" value="0.00" />
          </div>
        </div>
      </div>
      <div class="grid-2">
        <div>
          <label>IVA %</label>
          <input type="text" name="item_vat_rate[]" value="21" />
        </div>
        <div>
          <label>IRPF %</label>
          <input type="text" name="item_irpf_rate[]" value="15" />
        </div>
      </div>`;
    items.appendChild(div);
    recalcTotals();
  }

  function parseFloatSafe(v){
    const n = parseFloat((''+v).replace(',', '.'));
    return isNaN(n)?0:n;
  }

  function recalcTotals(){
    const qty = Array.from(document.querySelectorAll('[name="item_quantity[]"]')).map(i=>parseFloatSafe(i.value));
    const price = Array.from(document.querySelectorAll('[name="item_unit_price[]"]')).map(i=>parseFloatSafe(i.value));
    const vat = Array.from(document.querySelectorAll('[name="item_vat_rate[]"]')).map(i=>parseFloatSafe(i.value));
    const irpf = Array.from(document.querySelectorAll('[name="item_irpf_rate[]"]')).map(i=>parseFloatSafe(i.value));
    let base=0, iva=0, ir=0;
    const n = Math.max(qty.length, price.length, vat.length, irpf.length);
    for(let i=0;i<n;i++){
      const lineBase = (qty[i]||0)*(price[i]||0);
      base += lineBase;
      iva += lineBase * ((vat[i]||0)/100);
      ir += lineBase * ((irpf[i]||0)/100);
    }
    const total = base + iva - ir;
    document.getElementById('t_base').textContent = base.toFixed(2);
    document.getElementById('t_iva').textContent = iva.toFixed(2);
    document.getElementById('t_irpf').textContent = ir.toFixed(2);
    document.getElementById('t_total').textContent = total.toFixed(2);
  }

  addBtn && addBtn.addEventListener('click', addLine);
  document.addEventListener('input', function(e){
    const names = ['item_quantity[]','item_unit_price[]','item_vat_rate[]','item_irpf_rate[]'];
    if (e.target && names.includes(e.target.name)) {
      recalcTotals();
    }
  });
  // Delete line handling with confirmation if fields have data (description or price)
  items.addEventListener('click', function(e){
    const btn = e.target.closest('[data-role="remove-line"]');
    if (!btn) return;
    const card = btn.closest('.card');
    if (!card) return;
    // Ensure at least one line remains
    const cards = items.querySelectorAll('.card');
    if (cards.length <= 1) return; // first mandatory line protection
    // Check if has data
    const desc = card.querySelector('input[name="item_description[]"]').value.trim();
    const priceStr = card.querySelector('input[name="item_unit_price[]"]').value.trim();
    const price = parseFloat(priceStr.replace(',', '.')) || 0;
    if (desc !== '' || price > 0) {
      if (!confirm('Al continuar se eliminarán los datos de esta línea, ¿quieres continuar?')) {
        return;
      }
    }
    card.remove();
    recalcTotals();
  });
  issue && issue.addEventListener('change', applyTerms);
  terms && terms.addEventListener('change', applyTerms);
  // inicial
  applyTerms();
})();
</script>
