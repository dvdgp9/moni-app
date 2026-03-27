<?php
use Moni\Repositories\QuotesRepository;
use Moni\Repositories\QuoteItemsRepository;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Support\Config;
use Moni\Services\QuoteNumberingService;
use Moni\Services\InvoiceService;
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;

$flashAll = Flash::getAll();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$statusFilter = isset($_GET['status']) && is_array($_GET['status']) ? $_GET['status'] : [];

$allowedSort = ['quote_number', 'client_name', 'status', 'issue_date', 'valid_until', 'amount'];
$sortByReq = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : '';
$sortByReq = in_array($sortByReq, $allowedSort, true) ? $sortByReq : '';
$sortDirReq = isset($_GET['sort_dir']) ? strtolower(trim((string)$_GET['sort_dir'])) : 'asc';
$sortDirReq = $sortDirReq === 'desc' ? 'desc' : 'asc';

$effectiveSortBy = $sortByReq !== '' ? $sortByReq : 'issue_date';
$effectiveSortDir = $sortByReq !== '' ? $sortDirReq : 'desc';

// Actions: send, delete, convert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    moni_redirect(route_path('quotes'));
  }
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['_action'] ?? '';
  if ($id > 0 && $action) {
    try {
      if ($action === 'send') {
        $qt = QuotesRepository::find($id);
        if ($qt && $qt['status'] === 'draft') {
          $items = QuoteItemsRepository::byQuote($id);
          if (empty($items)) {
            Flash::add('error', 'No puedes enviar un presupuesto sin líneas.');
          } else {
            $num = QuoteNumberingService::issue($id, $qt['issue_date']);
            // Try to send email
            $client = \Moni\Repositories\ClientsRepository::find((int)$qt['client_id']);
            if ($client && !empty($client['email'])) {
              $totals = InvoiceService::computeTotals($items);
              $appUrl = rtrim((string)Config::get('app_url', ''), '/');
              $savedQuote = QuotesRepository::find($id);
              $publicUrl = $appUrl . '/presupuesto/' . ($savedQuote['token'] ?? '');
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
                  'validUntil' => $qt['valid_until'] ? date('d/m/Y', strtotime($qt['valid_until'])) : '',
                  'publicUrl' => $publicUrl,
                  'senderName' => $senderName,
                  'senderEmail' => $senderEmail,
                  'platformName' => Config::get('app_name', 'Moni'),
                ]);
                Flash::add('success', 'Presupuesto ' . $num . ' enviado a ' . $client['email']);
              } catch (Throwable $mailErr) {
                error_log('[quotes_list] Email failed: ' . $mailErr->getMessage());
                Flash::add('success', 'Presupuesto ' . $num . ' marcado como enviado.');
                Flash::add('error', 'No se pudo enviar el email.');
              }
            } else {
              Flash::add('success', 'Presupuesto ' . $num . ' marcado como enviado (cliente sin email).');
            }
          }
        }
      } elseif ($action === 'convert') {
        $qt = QuotesRepository::find($id);
        if ($qt && $qt['status'] === 'accepted') {
          $qItems = QuoteItemsRepository::byQuote($id);
          // Create draft invoice
          $invId = InvoicesRepository::createDraft([
            'client_id' => (int)$qt['client_id'],
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'notes' => $qt['notes'] ?? '',
          ]);
          // Copy items
          if (!empty($qItems)) {
            InvoiceItemsRepository::insertMany($invId, $qItems);
          }
          // Mark converted
          QuotesRepository::markConverted($id, $invId);
          Flash::add('success', 'Factura borrador creada a partir del presupuesto. Revísala y emítela.');
          moni_redirect(route_path('invoice_form', ['id' => $invId]));
        } else {
          Flash::add('error', 'Solo se pueden convertir presupuestos aceptados.');
        }
      } elseif ($action === 'delete') {
        QuoteItemsRepository::deleteByQuote($id);
        QuotesRepository::delete($id);
        Flash::add('success', 'Presupuesto eliminado.');
      }
    } catch (Throwable $e) {
      Flash::add('error', 'Acción fallida: ' . $e->getMessage());
    }
    moni_redirect(route_path('quotes'));
  }
}

function q_fmt_date(?string $date): string {
  if (!$date) return '—';
  $ts = strtotime($date);
  return $ts ? date('d/m/Y', $ts) : $date;
}

function q_status_label(string $s): string {
  return match ($s) {
    'draft' => 'Borrador',
    'sent' => 'Enviado',
    'accepted' => 'Aceptado',
    'rejected' => 'Rechazado',
    'expired' => 'Expirado',
    'converted' => 'Convertido',
    default => $s,
  };
}

function q_status_class(string $s): string {
  return match ($s) {
    'draft' => 'status-draft',
    'sent' => 'status-issued',
    'accepted' => 'status-paid',
    'rejected' => 'status-cancelled',
    'expired' => 'status-cancelled',
    'converted' => 'status-converted',
    default => '',
  };
}

$quotes = QuotesRepository::all($q, $statusFilter, $effectiveSortBy, $effectiveSortDir);

$today = date('Y-m-d');
$summary = [
  'count' => count($quotes),
  'total' => 0.0,
  'sent' => 0,
  'accepted' => 0,
];
foreach ($quotes as &$row) {
  $summary['total'] += (float)($row['total_amount'] ?? 0);
  if ($row['status'] === 'sent') $summary['sent']++;
  if ($row['status'] === 'accepted') $summary['accepted']++;
  // Auto-detect expired
  $row['is_expired'] = $row['status'] === 'sent' && !empty($row['valid_until']) && $row['valid_until'] < $today;
}
unset($row);

$filtersActive = $q !== '' || !empty($statusFilter);
$appUrl = rtrim((string)Config::get('app_url', ''), '/');
?>

<section>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <div>
      <h1 style="margin-bottom:8px">Presupuestos</h1>
      <p style="margin:0;color:var(--gray-600);max-width:760px">
        Crea, envía y gestiona presupuestos. Cuando el cliente acepte, conviértelo en factura con un clic.
      </p>
    </div>
    <a href="<?= route_path('quote_form') ?>" class="btn">+ Nuevo presupuesto</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <form method="get" class="invoices-filter-grid invoices-filter-grid-compact">
      <div class="invoices-filter-block invoices-filter-search">
        <label for="qq">Buscar</label>
        <input id="qq" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Número o cliente" />
      </div>
      <div class="invoices-filter-block">
        <label>Estado</label>
        <div class="check-pills">
          <?php foreach (['draft'=>'Borrador','sent'=>'Enviado','accepted'=>'Aceptado','rejected'=>'Rechazado','converted'=>'Convertido'] as $sv=>$sl): ?>
            <label class="check-pill">
              <input type="checkbox" name="status[]" value="<?= $sv ?>" <?= in_array($sv, $statusFilter, true) ? 'checked' : '' ?> />
              <span><?= $sl ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="invoices-filter-actions">
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        <?php if ($filtersActive): ?>
          <a href="<?= route_path('quotes') ?>" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card invoices-summary-card">
    <div class="invoices-summary-grid">
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gray-800)"><?= $summary['count'] ?></div>
        <div class="stat-label">Presupuestos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--primary)"><?= number_format($summary['total'], 2, ',', '.') ?>€</div>
        <div class="stat-label">Importe total</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#D97706"><?= $summary['sent'] ?></div>
        <div class="stat-label">Pendientes</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#059669"><?= $summary['accepted'] ?></div>
        <div class="stat-label">Aceptados</div>
      </div>
    </div>
  </div>

  <?php if (empty($quotes)): ?>
    <div class="card" style="text-align:center;padding:48px">
      <p style="color:var(--gray-600);margin:0">No hay presupuestos con los filtros actuales.</p>
      <div style="margin-top:16px">
        <a href="<?= route_path('quotes') ?>" class="btn btn-secondary">Ver todos</a>
        <a href="<?= route_path('quote_form') ?>" class="btn">Crear presupuesto</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <div class="invoices-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Nº</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Válido hasta</th>
            <th style="text-align:right">Importe</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($quotes as $row): ?>
            <tr class="<?= $row['is_expired'] ? 'invoice-row-overdue' : '' ?>">
              <td>
                <div style="font-weight:700;color:var(--gray-800)"><?= htmlspecialchars($row['quote_number'] ?: '—') ?></div>
              </td>
              <td>
                <div style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($row['client_name'] ?? 'Sin cliente') ?></div>
              </td>
              <td>
                <span class="status-badge <?= q_status_class($row['status']) ?>"><?= htmlspecialchars(q_status_label($row['status'])) ?></span>
                <?php if ($row['is_expired']): ?>
                  <div class="invoice-meta-warning">Expirado</div>
                <?php endif; ?>
              </td>
              <td><?= q_fmt_date($row['issue_date']) ?></td>
              <td>
                <?php if (!empty($row['valid_until'])): ?>
                  <span class="<?= $row['is_expired'] ? 'is-danger' : '' ?>"><?= q_fmt_date($row['valid_until']) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <div style="font-weight:700;color:var(--gray-800)"><?= number_format((float)$row['total_amount'], 2, ',', '.') ?> €</div>
              </td>
              <td class="table-actions">
                <a class="btn btn-secondary" href="<?= route_path('quote_form', ['id' => (int)$row['id']]) ?>">Editar</a>
                <a class="btn btn-secondary" href="<?= route_path('quote_pdf', ['id' => (int)$row['id']]) ?>" target="_blank" rel="noopener">PDF</a>

                <?php if ($row['status'] === 'draft'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="send" />
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                    <button type="submit" class="btn" onclick="return confirm('Se asignará número y se enviará al cliente por email. ¿Continuar?')">Enviar</button>
                  </form>
                <?php endif; ?>

                <?php if ($row['status'] === 'sent' && !empty($row['token'])): ?>
                  <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($appUrl . '/presupuesto/' . $row['token']) ?>').then(function(){alert('Enlace copiado')})">Copiar enlace</button>
                <?php endif; ?>

                <?php if ($row['status'] === 'accepted'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                    <input type="hidden" name="_action" value="convert" />
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                    <button type="submit" class="btn" onclick="return confirm('Se creará una factura borrador con los datos de este presupuesto. ¿Continuar?')">Convertir a factura</button>
                  </form>
                <?php endif; ?>

                <?php if ($row['status'] === 'converted' && !empty($row['converted_invoice_id'])): ?>
                  <a class="btn btn-secondary" href="<?= route_path('invoice_form', ['id' => (int)$row['converted_invoice_id']]) ?>">Ver factura</a>
                <?php endif; ?>

                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar el presupuesto?');">
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                  <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
</section>
