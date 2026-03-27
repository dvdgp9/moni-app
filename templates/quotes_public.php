<?php
use Moni\Repositories\QuotesRepository;
use Moni\Repositories\QuoteItemsRepository;
use Moni\Repositories\UsersRepository;
use Moni\Services\InvoiceService;
use Moni\Support\Config;

// $quoteToken is set by the router in index.php
$token = $quoteToken ?? '';
if ($token === '') {
    http_response_code(404);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>No encontrado</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Presupuesto no encontrado</h1></body></html>';
    exit;
}

$quote = QuotesRepository::findByToken($token);
if (!$quote) {
    http_response_code(404);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>No encontrado</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Presupuesto no encontrado</h1><p>El enlace no es válido o el presupuesto ha sido eliminado.</p></body></html>';
    exit;
}

$items = QuoteItemsRepository::byQuotePublic((int)$quote['id']);
$totals = InvoiceService::computeTotals($items);

// Emitter info
$emitter = UsersRepository::find((int)$quote['user_id']);
$brandName = ($emitter['company_name'] ?? '') ?: ($emitter['name'] ?? Config::get('app_name', 'Moni'));
$emitterName = ($emitter['company_name'] ?? '') ?: ($emitter['name'] ?? '');
$emitterNif = $emitter['nif'] ?? '';
$emitterAddress = $emitter['address'] ?? '';
$emitterPhone = $emitter['phone'] ?? '';
$emitterEmail = $emitter['billing_email'] ?? ($emitter['email'] ?? '');
$primary = $emitter['color_primary'] ?? '#0FA3B1';
$accent = $emitter['color_accent'] ?? '#F59E0B';

$today = date('Y-m-d');
$isExpired = $quote['status'] === 'sent' && !empty($quote['valid_until']) && $quote['valid_until'] < $today;

// Handle POST actions (accept / reject)
$actionMessage = '';
$actionType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';
    $postToken = $_POST['_token_quote'] ?? '';
    if ($postToken !== $token) {
        $actionMessage = 'Token inválido.';
        $actionType = 'error';
    } elseif ($isExpired) {
        $actionMessage = 'Este presupuesto ha expirado y ya no se puede aceptar o rechazar.';
        $actionType = 'error';
    } elseif ($quote['status'] !== 'sent') {
        $actionMessage = 'Este presupuesto ya no está pendiente de respuesta.';
        $actionType = 'error';
    } elseif ($postAction === 'accept') {
        $ok = QuotesRepository::acceptByToken($token);
        if ($ok) {
            $quote['status'] = 'accepted';
            $quote['accepted_at'] = date('Y-m-d H:i:s');
            $actionMessage = 'Has aceptado el presupuesto. Nos pondremos en contacto contigo pronto.';
            $actionType = 'success';
        } else {
            $actionMessage = 'No se pudo procesar la acción. Inténtalo de nuevo.';
            $actionType = 'error';
        }
    } elseif ($postAction === 'reject') {
        $reason = trim((string)($_POST['rejection_reason'] ?? ''));
        $ok = QuotesRepository::rejectByToken($token, $reason ?: null);
        if ($ok) {
            $quote['status'] = 'rejected';
            $quote['rejected_at'] = date('Y-m-d H:i:s');
            $actionMessage = 'Has rechazado el presupuesto.';
            $actionType = 'info';
        } else {
            $actionMessage = 'No se pudo procesar la acción. Inténtalo de nuevo.';
            $actionType = 'error';
        }
    }
}

function qp_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qp_mny($n) { return number_format((float)$n, 2, ',', '.'); }
function qp_date(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

$statusLabel = match ($quote['status']) {
    'draft' => 'Borrador',
    'sent' => 'Pendiente de respuesta',
    'accepted' => 'Aceptado',
    'rejected' => 'Rechazado',
    'expired' => 'Expirado',
    'converted' => 'Aceptado',
    default => $quote['status'],
};
$canRespond = $quote['status'] === 'sent' && !$isExpired;

$root = dirname(__DIR__);
$brandDir = $root . '/public/assets/brand';
$logoPath = null;
foreach (['/logo.svg', '/logo.png'] as $candidate) {
    if (file_exists($brandDir . $candidate)) {
        $logoPath = '/assets/brand' . $candidate;
        break;
    }
}
$faviconPng = file_exists($brandDir . '/favicon.png') ? '/assets/brand/favicon.png' : null;
$faviconIco = file_exists($brandDir . '/favicon.ico') ? '/assets/brand/favicon.ico' : null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Presupuesto <?= qp_h($quote['quote_number'] ?? '') ?> — <?= qp_h($brandName) ?></title>
  <?php if ($faviconPng): ?><link rel="icon" type="image/png" sizes="32x32" href="<?= $faviconPng ?>" /><?php endif; ?>
  <?php if ($faviconIco): ?><link rel="icon" href="<?= $faviconIco ?>" /><?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f1f5f9; color: #0f172a; line-height: 1.5; min-height: 100vh;
    }
    .qp-header {
      background: #fff; border-bottom: 1px solid #e2e8f0; padding: 16px 0;
    }
    .qp-container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
    .qp-brand { display: flex; align-items: center; gap: 10px; }
    .qp-brand img { height: 36px; }
    .qp-brand-mark { font-weight: 800; font-size: 20px; color: <?= qp_h($primary) ?>; }
    .qp-brand-meta { display:flex; flex-direction:column; gap:2px; }
    .qp-brand-sub { font-size: 12px; color: #64748b; }
    .qp-main { padding: 32px 0 60px; }
    .qp-card {
      background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;
      box-shadow: 0 4px 16px rgba(2,12,27,0.05); overflow: hidden; margin-bottom: 20px;
    }
    .qp-card-header {
      padding: 20px 24px; border-bottom: 1px solid #f1f5f9;
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
    }
    .qp-card-header h1 { font-size: 22px; font-weight: 700; }
    .qp-badge {
      display: inline-block; padding: 4px 14px; border-radius: 999px;
      font-size: 13px; font-weight: 600; letter-spacing: 0.2px;
    }
    .qp-badge-pending { background: #FEF3C7; color: #92400E; }
    .qp-badge-accepted { background: #D1FAE5; color: #065F46; }
    .qp-badge-rejected { background: #FEE2E2; color: #991B1B; }
    .qp-badge-expired { background: #F1F5F9; color: #64748B; }
    .qp-card-body { padding: 20px 24px; }
    .qp-meta-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
    }
    @media (max-width: 600px) { .qp-meta-grid { grid-template-columns: 1fr; } }
    .qp-meta-box {
      background: #f8fafc; border-radius: 10px; padding: 14px 16px; border: 1px solid #f1f5f9;
    }
    .qp-meta-box h3 {
      font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
      color: #94a3b8; margin-bottom: 8px; font-weight: 700;
    }
    .qp-meta-row { font-size: 14px; color: #334155; margin-bottom: 3px; }
    .qp-meta-row strong { color: #0f172a; }
    .qp-dates {
      display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 20px;
      font-size: 14px; color: #475569;
    }
    .qp-dates strong { color: #0f172a; }
    .qp-table { width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 14px; }
    .qp-table th {
      background: <?= qp_h($primary) ?>; color: #fff; padding: 10px 12px;
      text-align: left; font-weight: 600; font-size: 13px;
    }
    .qp-table th:first-child { border-top-left-radius: 8px; }
    .qp-table th:last-child { border-top-right-radius: 8px; text-align: right; }
    .qp-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
    .qp-table td:last-child { text-align: right; font-weight: 600; }
    .qp-table tbody tr:last-child td { border-bottom: none; }
    .qp-totals {
      display: flex; justify-content: flex-end; padding: 20px 24px;
      border-top: 1px solid #f1f5f9;
    }
    .qp-totals-box {
      background: #f8fafc; border-radius: 10px; padding: 14px 20px;
      border: 1px solid #e2e8f0; min-width: 240px;
    }
    .qp-totals-row {
      display: flex; justify-content: space-between; font-size: 14px; color: #475569; margin-bottom: 4px;
    }
    .qp-totals-grand {
      display: flex; justify-content: space-between; font-size: 18px; font-weight: 700;
      color: #0f172a; padding-top: 8px; margin-top: 8px; border-top: 2px solid <?= qp_h($primary) ?>;
    }
    .qp-notes {
      padding: 14px 16px; margin: 0 24px 20px; background: #fffbeb; border-radius: 8px;
      border: 1px solid #fef3c7; font-size: 13px; color: #78350f;
    }
    .qp-actions {
      padding: 20px 24px; border-top: 1px solid #f1f5f9;
      display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-start;
    }
    .qp-btn {
      display: inline-block; padding: 12px 28px; border-radius: 10px;
      font-weight: 700; font-size: 15px; text-decoration: none; border: none;
      cursor: pointer; transition: all 0.15s ease;
    }
    .qp-btn-accept { background: #059669; color: #fff; }
    .qp-btn-accept:hover { background: #047857; }
    .qp-btn-reject { background: #fff; color: #DC2626; border: 2px solid #FCA5A5; }
    .qp-btn-reject:hover { background: #FEF2F2; border-color: #DC2626; }
    .qp-alert {
      padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500;
    }
    .qp-alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
    .qp-alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .qp-alert-info { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
    .qp-status-banner {
      padding: 20px 24px; text-align: center; font-size: 15px;
    }
    .qp-status-banner strong { display: block; font-size: 17px; margin-bottom: 4px; }
    .qp-reject-field { width: 100%; margin-top: 8px; }
    .qp-reject-field textarea {
      width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
      font-family: inherit; font-size: 14px; resize: vertical; min-height: 60px;
    }
    .qp-footer {
      text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;
    }
    .qp-expired-overlay {
      background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px;
      padding: 16px 20px; text-align: center; margin-bottom: 20px; color: #991B1B;
    }
  </style>
</head>
<body>
  <header class="qp-header">
    <div class="qp-container">
      <div class="qp-brand">
        <?php if ($logoPath): ?>
          <img src="<?= $logoPath ?>" alt="<?= qp_h($brandName) ?>" />
        <?php else: ?>
          <span class="qp-brand-mark"><?= qp_h($brandName) ?></span>
        <?php endif; ?>
        <div class="qp-brand-meta">
          <?php if ($logoPath): ?>
            <span class="qp-brand-mark"><?= qp_h($brandName) ?></span>
          <?php endif; ?>
          <span class="qp-brand-sub">Presupuesto enviado por <?= qp_h($brandName) ?></span>
        </div>
      </div>
    </div>
  </header>

  <main class="qp-main">
    <div class="qp-container">

      <?php if ($actionMessage): ?>
        <div class="qp-alert qp-alert-<?= qp_h($actionType) ?>"><?= qp_h($actionMessage) ?></div>
      <?php endif; ?>

      <?php if ($isExpired && $quote['status'] === 'sent'): ?>
        <div class="qp-expired-overlay">
          <strong>Este presupuesto ha expirado</strong>
          <p>La fecha de validez (<?= qp_date($quote['valid_until']) ?>) ya ha pasado. Contacta con <?= qp_h($brandName) ?> si aún estás interesado.</p>
        </div>
      <?php endif; ?>

      <div class="qp-card">
        <!-- Header -->
        <div class="qp-card-header">
          <div>
            <h1>Presupuesto <?= qp_h($quote['quote_number'] ?? '') ?></h1>
            <div style="margin-top:4px;font-size:14px;color:#64748b;">Documento compartido por <?= qp_h($brandName) ?></div>
          </div>
          <?php
            $badgeClass = match ($quote['status']) {
                'sent' => ($isExpired ? 'qp-badge-expired' : 'qp-badge-pending'),
                'accepted', 'converted' => 'qp-badge-accepted',
                'rejected' => 'qp-badge-rejected',
                'expired' => 'qp-badge-expired',
                default => 'qp-badge-pending',
            };
          ?>
          <span class="qp-badge <?= $badgeClass ?>"><?= qp_h($isExpired && $quote['status'] === 'sent' ? 'Expirado' : $statusLabel) ?></span>
        </div>

        <div class="qp-card-body">
          <!-- Dates -->
          <div class="qp-dates">
            <div><strong>Fecha:</strong> <?= qp_date($quote['issue_date']) ?></div>
            <?php if (!empty($quote['valid_until'])): ?>
              <div><strong>Válido hasta:</strong> <?= qp_date($quote['valid_until']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Emitter / Client -->
          <div class="qp-meta-grid">
            <div class="qp-meta-box">
              <h3>De</h3>
              <div class="qp-meta-row"><strong><?= qp_h($emitterName) ?></strong></div>
              <?php if ($emitterNif): ?><div class="qp-meta-row">NIF: <?= qp_h($emitterNif) ?></div><?php endif; ?>
              <?php if ($emitterAddress): ?><div class="qp-meta-row"><?= qp_h($emitterAddress) ?></div><?php endif; ?>
              <?php if ($emitterEmail): ?><div class="qp-meta-row"><?= qp_h($emitterEmail) ?></div><?php endif; ?>
              <?php if ($emitterPhone): ?><div class="qp-meta-row"><?= qp_h($emitterPhone) ?></div><?php endif; ?>
            </div>
            <div class="qp-meta-box">
              <h3>Para</h3>
              <div class="qp-meta-row"><strong><?= qp_h($quote['client_name'] ?? '') ?></strong></div>
              <?php if (!empty($quote['client_nif'])): ?><div class="qp-meta-row">NIF: <?= qp_h($quote['client_nif']) ?></div><?php endif; ?>
              <?php if (!empty($quote['client_address'])): ?><div class="qp-meta-row"><?= qp_h($quote['client_address']) ?></div><?php endif; ?>
              <?php if (!empty($quote['client_email'])): ?><div class="qp-meta-row"><?= qp_h($quote['client_email']) ?></div><?php endif; ?>
              <?php if (!empty($quote['client_phone'])): ?><div class="qp-meta-row"><?= qp_h($quote['client_phone']) ?></div><?php endif; ?>
            </div>
          </div>

          <!-- Items table -->
          <table class="qp-table">
            <thead>
              <tr>
                <th>Concepto</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>IVA</th>
                <th>Importe</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it):
                $qty = (float)$it['quantity'];
                $price = (float)$it['unit_price'];
                $lineBase = $qty * $price;
                $vatRate = (float)($it['vat_rate'] ?? 21);
                $irpfRate = (float)($it['irpf_rate'] ?? 15);
                $lineTotal = $lineBase + ($lineBase * ($vatRate/100)) - ($lineBase * ($irpfRate/100));
              ?>
              <tr>
                <td><?= qp_h($it['description']) ?></td>
                <td><?= qp_h($qty) ?></td>
                <td><?= qp_mny($price) ?> €</td>
                <td><?= qp_h(number_format($vatRate, 0)) ?>%</td>
                <td><?= qp_mny($lineTotal) ?> €</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="qp-totals">
          <div class="qp-totals-box">
            <div class="qp-totals-row"><span>Base imponible</span><span><?= qp_mny($totals['base']) ?> €</span></div>
            <div class="qp-totals-row"><span>IVA</span><span><?= qp_mny($totals['iva']) ?> €</span></div>
            <?php if ($totals['irpf'] > 0): ?>
              <div class="qp-totals-row"><span>IRPF</span><span>-<?= qp_mny($totals['irpf']) ?> €</span></div>
            <?php endif; ?>
            <div class="qp-totals-grand"><span>Total</span><span><?= qp_mny($totals['total']) ?> €</span></div>
          </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($quote['notes'])): ?>
          <div class="qp-notes">
            <strong>Notas:</strong><br />
            <?= nl2br(qp_h($quote['notes'])) ?>
          </div>
        <?php endif; ?>

        <!-- Action buttons or status message -->
        <?php if ($canRespond): ?>
          <div class="qp-actions">
            <form method="post" style="display:inline">
              <input type="hidden" name="_token_quote" value="<?= qp_h($token) ?>" />
              <input type="hidden" name="_action" value="accept" />
              <button type="submit" class="qp-btn qp-btn-accept" onclick="return confirm('¿Confirmas que deseas aceptar este presupuesto?')">Aceptar presupuesto</button>
            </form>
            <form method="post" id="rejectForm" style="flex:1;min-width:200px;">
              <input type="hidden" name="_token_quote" value="<?= qp_h($token) ?>" />
              <input type="hidden" name="_action" value="reject" />
              <button type="button" class="qp-btn qp-btn-reject" id="rejectToggle">Rechazar</button>
              <div id="rejectDetails" style="display:none;margin-top:10px;">
                <div class="qp-reject-field">
                  <textarea name="rejection_reason" placeholder="Motivo del rechazo (opcional)"></textarea>
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;">
                  <button type="submit" class="qp-btn qp-btn-reject" style="padding:8px 20px;font-size:13px;">Confirmar rechazo</button>
                  <button type="button" class="qp-btn" id="rejectCancel" style="padding:8px 20px;font-size:13px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">Cancelar</button>
                </div>
              </div>
            </form>
          </div>
        <?php elseif ($quote['status'] === 'accepted' || $quote['status'] === 'converted'): ?>
          <div class="qp-status-banner" style="background:#D1FAE5;color:#065F46;">
            <strong>Presupuesto aceptado</strong>
            <?php if (!empty($quote['accepted_at'])): ?>
              el <?= qp_date(substr($quote['accepted_at'], 0, 10)) ?>
            <?php endif; ?>
          </div>
        <?php elseif ($quote['status'] === 'rejected'): ?>
          <div class="qp-status-banner" style="background:#FEE2E2;color:#991B1B;">
            <strong>Presupuesto rechazado</strong>
            <?php if (!empty($quote['rejected_at'])): ?>
              el <?= qp_date(substr($quote['rejected_at'], 0, 10)) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="qp-footer">
        Presupuesto compartido por <?= qp_h($brandName) ?> · Gestionado con <?= qp_h(Config::get('app_name', 'Moni')) ?>
      </div>
    </div>
  </main>

  <script>
  (function(){
    var toggle = document.getElementById('rejectToggle');
    var details = document.getElementById('rejectDetails');
    var cancel = document.getElementById('rejectCancel');
    if (toggle && details) {
      toggle.addEventListener('click', function(){ details.style.display='block'; toggle.style.display='none'; });
    }
    if (cancel && details && toggle) {
      cancel.addEventListener('click', function(){ details.style.display='none'; toggle.style.display='inline-block'; });
    }
  })();
  </script>
</body>
</html>
