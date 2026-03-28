<?php
use Moni\Repositories\QuotesRepository;
use Moni\Repositories\QuoteItemsRepository;
use Moni\Repositories\ClientsRepository;
use Moni\Support\Csrf;
use Moni\Support\Flash;
use Moni\Services\InvoiceService;
use Moni\Support\Config;
use Moni\Services\QuoteNumberingService;
use Moni\Repositories\UsersRepository;
use Moni\Repositories\SettingsRepository;
use Moni\Services\AuthService;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$errors = [];

$clients = ClientsRepository::all();
$storedVat = SettingsRepository::get('default_vat_rate');
$storedIrpf = SettingsRepository::get('default_irpf_rate');
$defaultVat = $storedVat !== null && is_numeric(str_replace(',', '.', $storedVat)) ? (string)(float)str_replace(',', '.', $storedVat) : '21';
$defaultIrpf = $storedIrpf !== null && is_numeric(str_replace(',', '.', $storedIrpf)) ? (string)(float)str_replace(',', '.', $storedIrpf) : '15';

$quote = [
  'client_id' => $clients[0]['id'] ?? 0,
  'issue_date' => date('Y-m-d'),
  'valid_until' => date('Y-m-d', strtotime('+30 days')),
  'notes' => '',
];
$currentStatus = 'draft';
$items = [[
  'description' => '',
  'quantity' => '1',
  'unit_price' => '0.00',
  'vat_rate' => $defaultVat,
  'irpf_rate' => $defaultIrpf,
]];

if ($editing) {
  $found = QuotesRepository::find($id);
  if ($found) {
    $quote = array_merge($quote, $found);
    $currentStatus = $found['status'] ?? 'draft';
    $items = QuoteItemsRepository::byQuote($id);
    if (empty($items)) {
      $items = [[
        'description' => '', 'quantity' => '1', 'unit_price' => '0.00', 'vat_rate' => $defaultVat, 'irpf_rate' => $defaultIrpf
      ]];
    }
  } else {
    Flash::add('error', 'Presupuesto no encontrado o sin acceso.');
    moni_redirect(route_path('quotes'));
  }
}

function parse_quote_items_from_post(): array {
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
    $v = (string)($vat[$i] ?? ($GLOBALS['defaultVat'] ?? '21'));
    $r = (string)($irpf[$i] ?? ($GLOBALS['defaultIrpf'] ?? '15'));
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

function quote_is_valid_decimal(string $value): bool {
  return is_numeric(str_replace(',', '.', trim($value)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    moni_redirect(route_path('quotes'));
  }
  $quote['client_id'] = (int)($_POST['client_id'] ?? 0);
  $quote['issue_date'] = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
  $quote['valid_until'] = trim((string)($_POST['valid_until'] ?? ''));
  if ($quote['valid_until'] === '') { $quote['valid_until'] = null; }
  $quote['notes'] = trim((string)($_POST['notes'] ?? ''));
  $targetStatus = (string)($_POST['status'] ?? 'draft');
  $targetStatus = in_array($targetStatus, ['draft', 'sent'], true) ? $targetStatus : 'draft';
  $items = parse_quote_items_from_post();

  if ($quote['client_id'] <= 0) {
    $errors['client_id'] = 'Selecciona un cliente';
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $quote['issue_date'])) {
    $errors['issue_date'] = 'Fecha inválida';
  }
  if ($quote['valid_until'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $quote['valid_until'])) {
    $errors['valid_until'] = 'Fecha de validez inválida';
  }
  if ($quote['valid_until'] !== null && empty($errors['issue_date']) && empty($errors['valid_until']) && $quote['valid_until'] < $quote['issue_date']) {
    $errors['valid_until'] = 'La fecha de validez no puede ser anterior a la fecha del presupuesto';
  }
  if (empty($items)) {
    $errors['items'] = 'Añade al menos una línea';
  } else {
    foreach ($items as $item) {
      if (trim((string)$item['description']) === '') {
        $errors['items'] = 'Todas las líneas deben tener descripción';
        break;
      }
      if (!quote_is_valid_decimal((string)$item['quantity']) || (float)str_replace(',', '.', (string)$item['quantity']) <= 0) {
        $errors['items'] = 'La cantidad de cada línea debe ser numérica y mayor que 0';
        break;
      }
      if (!quote_is_valid_decimal((string)$item['unit_price']) || (float)str_replace(',', '.', (string)$item['unit_price']) < 0) {
        $errors['items'] = 'El precio de cada línea debe ser numérico y no negativo';
        break;
      }
      if (!quote_is_valid_decimal((string)$item['vat_rate'])) {
        $errors['items'] = 'El IVA de cada línea debe ser numérico';
        break;
      }
      $vatValue = (float)str_replace(',', '.', (string)$item['vat_rate']);
      if ($vatValue < 0 || $vatValue > 21) {
        $errors['items'] = 'El IVA de cada línea debe estar entre 0 y 21';
        break;
      }
      if (!quote_is_valid_decimal((string)$item['irpf_rate'])) {
        $errors['items'] = 'El IRPF de cada línea debe ser numérico';
        break;
      }
      $irpfValue = (float)str_replace(',', '.', (string)$item['irpf_rate']);
      if ($irpfValue < 0) {
        $errors['items'] = 'El IRPF de cada línea debe ser igual o mayor que 0';
        break;
      }
    }
  }

  // Check client has email if sending
  if ($targetStatus === 'sent' && empty($errors)) {
    $client = ClientsRepository::find($quote['client_id']);
    if (!$client || empty($client['email'])) {
      $errors['client_id'] = 'El cliente debe tener un email para enviar el presupuesto';
    } elseif (filter_var((string)$client['email'], FILTER_VALIDATE_EMAIL) === false) {
      $errors['client_id'] = 'El email del cliente no es válido para enviar el presupuesto';
    }
  }

  if (empty($errors)) {
    try {
      $savedId = $id;
      if ($editing) {
        QuotesRepository::update($id, $quote);
        QuoteItemsRepository::deleteByQuote($id);
        QuoteItemsRepository::insertMany($id, $items);
      } else {
        $savedId = QuotesRepository::create($quote);
        QuoteItemsRepository::insertMany($savedId, $items);
      }

      if ($targetStatus === 'sent') {
        $num = QuoteNumberingService::issue($savedId, $quote['issue_date']);
        // Send email
        $savedQuote = QuotesRepository::find($savedId);
        $client = ClientsRepository::find((int)$quote['client_id']);
        if ($savedQuote && $client && !empty($client['email'])) {
          $totals = InvoiceService::computeTotals($items);
          $appUrl = rtrim((string)Config::get('app_url', ''), '/');
          $publicUrl = $appUrl . '/presupuesto/' . $savedQuote['token'];
          $user = UsersRepository::find((int)AuthService::userId());
          $senderName = trim((string)(($user['company_name'] ?? '') ?: ($user['name'] ?? Config::get('app_name', 'Moni'))));
          $senderEmail = trim((string)(($user['billing_email'] ?? '') ?: ($user['email'] ?? '')));
          try {
            \Moni\Services\EmailService::sendQuote($client['email'], 'Presupuesto de ' . $senderName, [
              'brandName' => Config::get('app_name', 'Moni'),
              'appUrl' => $appUrl,
              'quoteNumber' => $num,
              'clientName' => $client['name'] ?? '',
              'total' => number_format($totals['total'], 2, ',', '.') . ' €',
              'validUntil' => $quote['valid_until'] ? date('d/m/Y', strtotime($quote['valid_until'])) : '',
              'publicUrl' => $publicUrl,
              'senderName' => $senderName,
              'senderEmail' => $senderEmail,
              'platformName' => Config::get('app_name', 'Moni'),
            ]);
            Flash::add('success', 'Presupuesto ' . $num . ' enviado por email a ' . $client['email']);
          } catch (Throwable $mailErr) {
            error_log('[quote_form] Email failed: ' . $mailErr->getMessage());
            Flash::add('success', 'Presupuesto ' . $num . ' guardado.');
            Flash::add('error', 'No se pudo enviar el email: ' . (Config::get('debug') ? $mailErr->getMessage() : 'revisa la configuración SMTP.'));
          }
        } else {
          Flash::add('success', 'Presupuesto ' . $num . ' guardado (sin enviar email).');
        }
      } else {
        Flash::add('success', 'Presupuesto guardado como borrador.');
      }

      moni_redirect(route_path('quotes'));
    } catch (Throwable $e) {
      error_log('[quote_form] ' . $e->getMessage());
      $errors['general'] = Config::get('debug')
        ? 'No se pudo guardar: ' . $e->getMessage()
        : 'No se pudo guardar el presupuesto. Revisa los datos e inténtalo de nuevo.';
    }
  }
}

$totals = InvoiceService::computeTotals($items);
$canEdit = in_array($currentStatus, ['draft', 'sent'], true);
?>
<section>
  <h1><?= $editing ? 'Editar presupuesto' : 'Nuevo presupuesto' ?></h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert error">Por favor, corrige los errores marcados.</div>
  <?php endif; ?>
  <?php if (!empty($errors['general'])): ?>
    <div class="alert error"><?= htmlspecialchars($errors['general']) ?></div>
  <?php endif; ?>

  <?php if ($editing && !$canEdit): ?>
    <div class="alert">Este presupuesto no se puede editar en su estado actual (<?= htmlspecialchars($currentStatus) ?>).</div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />

    <div class="grid-2">
      <div>
        <label>Cliente *</label>
        <select name="client_id" required style="width:100%;padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff" <?= !$canEdit ? 'disabled' : '' ?>>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$quote['client_id']===(int)$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?><?= !empty($c['email']) ? ' (' . htmlspecialchars($c['email']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['client_id'])): ?><div class="alert error"><?= htmlspecialchars($errors['client_id']) ?></div><?php endif; ?>
      </div>
      <div></div>
    </div>

    <div class="grid-2">
      <div>
        <label>Fecha del presupuesto *</label>
        <input type="date" name="issue_date" value="<?= htmlspecialchars($quote['issue_date']) ?>" required <?= !$canEdit ? 'disabled' : '' ?> />
        <?php if (!empty($errors['issue_date'])): ?><div class="alert error"><?= htmlspecialchars($errors['issue_date']) ?></div><?php endif; ?>
      </div>
      <div>
        <label>Válido hasta</label>
        <input type="date" name="valid_until" value="<?= htmlspecialchars((string)($quote['valid_until'] ?? '')) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
        <?php if (!empty($errors['valid_until'])): ?><div class="alert error"><?= htmlspecialchars($errors['valid_until']) ?></div><?php endif; ?>
      </div>
    </div>

    <label>Notas</label>
    <textarea name="notes" rows="3" placeholder="Condiciones, observaciones..." <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars($quote['notes'] ?? '') ?></textarea>

    <h3>Líneas</h3>
    <div id="qitems">
      <?php foreach ($items as $idx => $it): ?>
        <div class="card" style="margin-bottom:8px; position:relative">
          <?php if ($idx > 0 && $canEdit): ?>
            <button type="button" class="btn btn-danger" data-role="remove-line" title="Eliminar línea" style="position:absolute;top:8px;right:8px;padding:6px 10px">&#x1F5D1;&#xFE0F;</button>
          <?php endif; ?>
          <div class="grid-2">
            <div>
              <label>Descripción</label>
              <input type="text" name="item_description[]" value="<?= htmlspecialchars((string)$it['description']) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
            </div>
            <div class="grid-2">
              <div>
                <label>Cantidad</label>
                <input type="text" name="item_quantity[]" value="<?= htmlspecialchars((string)$it['quantity']) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
              </div>
              <div>
                <label>Precio</label>
                <input type="text" name="item_unit_price[]" value="<?= htmlspecialchars((string)$it['unit_price']) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
              </div>
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>IVA %</label>
              <input type="text" name="item_vat_rate[]" value="<?= htmlspecialchars((string)$it['vat_rate']) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
            </div>
            <div>
              <label>IRPF %</label>
              <input type="text" name="item_irpf_rate[]" value="<?= htmlspecialchars((string)$it['irpf_rate']) ?>" <?= !$canEdit ? 'disabled' : '' ?> />
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($errors['items'])): ?><div class="alert error"><?= htmlspecialchars($errors['items']) ?></div><?php endif; ?>
    <?php if ($canEdit): ?>
      <button type="button" class="btn" id="q_add_line">+ Añadir línea</button>
    <?php endif; ?>

    <div class="card" style="margin-top:12px">
      <div class="grid-2">
        <div><strong>Base imponible:</strong> <span id="qt_base"><?= number_format($totals['base'], 2) ?></span> €</div>
        <div><strong>IVA:</strong> <span id="qt_iva"><?= number_format($totals['iva'], 2) ?></span> €</div>
        <div><strong>IRPF:</strong> <span id="qt_irpf"><?= number_format($totals['irpf'], 2) ?></span> €</div>
        <div><strong>Total:</strong> <span id="qt_total"><?= number_format($totals['total'], 2) ?></span> €</div>
      </div>
    </div>

    <?php if ($canEdit): ?>
    <div style="display:flex;gap:10px;margin-top:10px;justify-content:flex-end;flex-wrap:wrap">
      <button type="submit" name="status" value="draft" class="btn btn-secondary">Guardar borrador</button>
      <button type="submit" name="status" value="sent" class="btn" onclick="return confirm('Se asignará número y se enviará por email al cliente. ¿Continuar?')">Enviar al cliente</button>
      <a class="btn btn-secondary" href="<?= route_path('quotes') ?>">Cancelar</a>
    </div>
    <?php else: ?>
    <div style="display:flex;gap:10px;margin-top:10px;justify-content:flex-end">
      <a class="btn btn-secondary" href="<?= route_path('quotes') ?>">Volver</a>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($editing && $canEdit): ?>
    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <form method="post" action="<?= route_path('quotes') ?>" onsubmit="return confirm('¿Eliminar el presupuesto?');">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <input type="hidden" name="_action" value="delete" />
        <input type="hidden" name="id" value="<?= (int)$id ?>" />
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </form>
    </div>
  <?php endif; ?>
</section>

<script>
(function(){
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  if (!canEdit) return;

  var addBtn = document.getElementById('q_add_line');
  var items = document.getElementById('qitems');

  function addLine(){
    var div = document.createElement('div');
    div.className = 'card';
    div.style.marginBottom = '8px';
    div.style.position = 'relative';
    div.innerHTML = '<button type="button" class="btn btn-danger" data-role="remove-line" title="Eliminar línea" style="position:absolute;top:8px;right:8px;padding:6px 10px">\uD83D\uDDD1\uFE0F</button>'
      + '<div class="grid-2"><div><label>Descripción</label><input type="text" name="item_description[]" /></div>'
      + '<div class="grid-2"><div><label>Cantidad</label><input type="text" name="item_quantity[]" value="1" /></div>'
      + '<div><label>Precio</label><input type="text" name="item_unit_price[]" value="0.00" /></div></div></div>'
      + '<div class="grid-2"><div><label>IVA %</label><input type="text" name="item_vat_rate[]" value="<?= htmlspecialchars($defaultVat) ?>" /></div>'
      + '<div><label>IRPF %</label><input type="text" name="item_irpf_rate[]" value="<?= htmlspecialchars($defaultIrpf) ?>" /></div></div>';
    items.appendChild(div);
    recalc();
  }

  function pf(v){ var n=parseFloat((''+v).replace(',','.')); return isNaN(n)?0:n; }

  function recalc(){
    var qty=Array.from(document.querySelectorAll('[name="item_quantity[]"]')).map(function(i){return pf(i.value)});
    var price=Array.from(document.querySelectorAll('[name="item_unit_price[]"]')).map(function(i){return pf(i.value)});
    var vat=Array.from(document.querySelectorAll('[name="item_vat_rate[]"]')).map(function(i){return pf(i.value)});
    var irpf=Array.from(document.querySelectorAll('[name="item_irpf_rate[]"]')).map(function(i){return pf(i.value)});
    var base=0,iva=0,ir=0,n=Math.max(qty.length,price.length,vat.length,irpf.length);
    for(var i=0;i<n;i++){var lb=(qty[i]||0)*(price[i]||0);base+=lb;iva+=lb*((vat[i]||0)/100);ir+=lb*((irpf[i]||0)/100);}
    document.getElementById('qt_base').textContent=base.toFixed(2);
    document.getElementById('qt_iva').textContent=iva.toFixed(2);
    document.getElementById('qt_irpf').textContent=ir.toFixed(2);
    document.getElementById('qt_total').textContent=(base+iva-ir).toFixed(2);
  }

  addBtn && addBtn.addEventListener('click', addLine);
  document.addEventListener('input', function(e){
    var names=['item_quantity[]','item_unit_price[]','item_vat_rate[]','item_irpf_rate[]'];
    if(e.target && names.indexOf(e.target.name)!==-1) recalc();
  });
  items.addEventListener('click', function(e){
    var btn=e.target.closest('[data-role="remove-line"]');
    if(!btn) return;
    var card=btn.closest('.card');
    if(!card) return;
    if(items.querySelectorAll('.card').length<=1) return;
    var desc=card.querySelector('input[name="item_description[]"]').value.trim();
    var pr=parseFloat((card.querySelector('input[name="item_unit_price[]"]').value||'').replace(',','.'))||0;
    if(desc!==''||pr>0){if(!confirm('¿Eliminar esta línea?')) return;}
    card.remove(); recalc();
  });
})();
</script>
