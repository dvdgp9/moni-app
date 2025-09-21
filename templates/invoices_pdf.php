<?php
use Dompdf\Dompdf;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Repositories\ClientsRepository;
use Moni\Services\InvoiceService;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Falta el parámetro id';
    return;
}

$inv = InvoicesRepository::find($id);
if (!$inv) {
    http_response_code(404);
    echo 'Factura no encontrada';
    return;
}
$client = ClientsRepository::find((int)$inv['client_id']);
$items = InvoiceItemsRepository::byInvoice($id);
$totals = InvoiceService::computeTotals($items);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function mny($n){ return number_format((float)$n, 2, ',', '.'); }

$number = $inv['invoice_number'] ?: '—';
$issue = $inv['issue_date'] ?: '';
$due = $inv['due_date'] ?: '';
$clientName = $client['name'] ?? '';
$clientNif = $client['nif'] ?? '';
$clientEmail = $client['email'] ?? '';
$clientPhone = $client['phone'] ?? '';
$clientAddress = $client['address'] ?? '';
$notes = $inv['notes'] ?? '';

$html = '<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
  body{font-family:DejaVu Sans, Arial, sans-serif; font-size:12px; color:#0F172A}
  .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #E2E8F0;padding-bottom:8px;margin-bottom:12px}
  .brand h1{margin:0;font-size:20px}
  .meta{font-size:12px;text-align:right}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .box{border:1px solid #E2E8F0;border-radius:8px;padding:8px}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{padding:8px;border-bottom:1px solid #E2E8F0;text-align:left}
  th{background:#F1F5F9}
  .totals{margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .right{text-align:right}
</style></head><body>';

$html .= '<div class="header">
  <div class="brand"><h1>Factura ' . h($number) . '</h1></div>
  <div class="meta">
    <div><strong>Fecha:</strong> ' . h($issue) . '</div>
    <div><strong>Vencimiento:</strong> ' . h($due) . '</div>
  </div>
</div>';

$html .= '<div class="grid">
  <div class="box">
    <strong>Cliente</strong><br>
    ' . h($clientName) . '<br>
    NIF: ' . h($clientNif) . '<br>
    ' . nl2br(h($clientAddress)) . '<br>
    ' . h($clientEmail) . ' ' . h($clientPhone) . '
  </div>
  <div class="box">
    <strong>Notas</strong><br>
    ' . nl2br(h($notes)) . '
  </div>
</div>';

$html .= '<table><thead><tr>
  <th>Descripción</th><th>Cant.</th><th>Precio</th><th>IVA %</th><th>IRPF %</th><th class="right">Importe</th>
</tr></thead><tbody>';
foreach ($items as $it) {
    $qty = (float)$it['quantity'];
    $price = (float)$it['unit_price'];
    $lineBase = $qty * $price;
    $html .= '<tr>'
        . '<td>' . h($it['description']) . '</td>'
        . '<td>' . h($qty) . '</td>'
        . '<td>' . mny($price) . ' €</td>'
        . '<td>' . h($it['vat_rate']) . '</td>'
        . '<td>' . h($it['irpf_rate']) . '</td>'
        . '<td class="right">' . mny($lineBase) . ' €</td>'
        . '</tr>';
}
$html .= '</tbody></table>';

$html .= '<div class="totals">
  <div></div>
  <div>
    <div><strong>Base:</strong> ' . mny($totals['base']) . ' €</div>
    <div><strong>IVA:</strong> ' . mny($totals['iva']) . ' €</div>
    <div><strong>IRPF:</strong> ' . mny($totals['irpf']) . ' €</div>
    <div><strong>Total:</strong> ' . mny($totals['total']) . ' €</div>
  </div>
</div>';

$html .= '</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Factura-' . ($inv['invoice_number'] ?: 'borrador-' . $id) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
