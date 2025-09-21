<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use Moni\Repositories\InvoicesRepository;
use Moni\Repositories\InvoiceItemsRepository;
use Moni\Repositories\ClientsRepository;
use Moni\Services\InvoiceService;
use Moni\Repositories\UsersRepository;

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

// Emitter data (logged user) and brand colors (fallbacks)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$emitter = !empty($_SESSION['user_id']) ? UsersRepository::find((int)$_SESSION['user_id']) : null;
$primary = $emitter['color_primary'] ?? '#8B5CF6';
$accent  = $emitter['color_accent'] ?? '#F59E0B';

// Pick contrasting text color for table headers based on accent background
function pick_contrast_color(string $hex): string {
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
    $r = hexdec(substr($h,0,2));
    $g = hexdec(substr($h,2,2));
    $b = hexdec(substr($h,4,2));
    // Relative luminance (sRGB)
    $rf = $r/255; $gf = $g/255; $bf = $b/255;
    $map = function($c){ return $c <= 0.03928 ? $c/12.92 : pow(($c+0.055)/1.055, 2.4); };
    $L = 0.2126*$map($rf) + 0.7152*$map($gf) + 0.0722*$map($bf);
    return ($L < 0.5) ? '#FFFFFF' : '#0F172A';
}
$thText = pick_contrast_color((string)$accent);

// Helper to safely embed logo
$root = dirname(__DIR__);
function embed_logo_src(string $logoUrl, string $root): ?string {
    $logoUrl = trim($logoUrl);
    if ($logoUrl === '') return null;
    // Local upload like /uploads/logos/...
    if (str_starts_with($logoUrl, '/')) {
        $file = $root . '/public' . $logoUrl;
        if (is_file($file) && is_readable($file)) {
            $f = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$f->file($file);
            $data = @file_get_contents($file);
            if ($data !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
        return null;
    }
    // Remote URL http(s) — Dompdf will fetch if isRemoteEnabled
    if (preg_match('~^https?://~i', $logoUrl)) {
        return $logoUrl;
    }
    return null;
}

$html = '<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
  body{font-family:DejaVu Sans, Arial, sans-serif; font-size:12px; color:#0F172A}
  .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid ' . h($primary) . ';padding-bottom:8px;margin-bottom:12px}
  .brand h1{margin:0;font-size:20px;color:' . h($primary) . '}
  .meta{font-size:12px;text-align:right}
  .grid{display:grid;grid-template-columns:1fr;gap:10px}
  .box{border:1px solid ' . h($primary) . ';border-radius:8px;padding:8px}
  .box + .box{margin-top:10px} /* fallback for Dompdf when grid-gap is ignored */
  .row{margin:2px 0}
  .icon{display:inline-block;width:12px;height:12px;vertical-align:middle;margin-right:6px}
  .row span{vertical-align:middle}
  table{width:100%;border-collapse:separate;border-spacing:0;margin-top:10px}
  th,td{padding:8px;border-bottom:1px solid #E2E8F0;text-align:left}
  thead th{background:' . h($accent) . '; color:' . h($thText) . '}
  thead th:first-child{border-top-left-radius:8px}
  thead th:last-child{border-top-right-radius:8px}
  .totals{margin-top:12px;display:grid;grid-template-columns:1fr 280px;gap:12px;align-items:flex-start}
  .totals .card{border:1px solid #E2E8F0;border-radius:8px;padding:10px}
  .totals .card .after-irpf{margin-bottom:10px}
  .totals .grand{font-size:16px;font-weight:700;background:#F8FAFC;border:1px solid ' . h($primary) . ';border-radius:8px;padding:12px;text-align:right}
  .right{text-align:right}
</style></head><body>';

$logo = $emitter['logo_url'] ?? '';
$logoSrc = embed_logo_src((string)$logo, $root);

$html .= '<div class="header">'
  . '<div class="brand"><h1>Factura ' . h($number) . '</h1></div>'
  . '<div class="meta">'
    . ($logoSrc ? ('<div><img src=\'' . h($logoSrc) . '\' style=\'max-height:60px\' /></div>') : '')
    . '<div><strong>Fecha:</strong> ' . h($issue) . ' &nbsp;·&nbsp; <strong>Vencimiento:</strong> ' . h($due) . '</div>'
  . '</div>'
  . '</div>';

$emitterName = ($emitter['company_name'] ?? '') ?: ($emitter['name'] ?? '');
$emitterNif = $emitter['nif'] ?? '';
$emitterAddress = $emitter['address'] ?? '';
$emitterPhone = $emitter['phone'] ?? '';
$emitterEmail = $emitter['billing_email'] ?? ($emitter['email'] ?? '');
$emitterIban = $emitter['iban'] ?? '';

// Helper: build data URI for SVG strings (works well in Dompdf)
function svg_data_uri(string $xml): string {
    return 'data:image/svg+xml;base64,' . base64_encode($xml);
}
// Tiny SVG XML strings (UntitledUI-like simple strokes)
$xmlUser = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>';
$xmlId = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="12" y2="13"/></svg>';
$xmlLocation = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
$xmlContact = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.84 19.84 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.84 19.84 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.12.86.31 1.7.57 2.5a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.58-1.58a2 2 0 0 1 2.11-.45c.8.26 1.64.45 2.5.57A2 2 0 0 1 22 16.92Z"/></svg>';
$icoUser = svg_data_uri($xmlUser);
$icoId = svg_data_uri($xmlId);
$icoLocation = svg_data_uri($xmlLocation);
$icoContact = svg_data_uri($xmlContact);

$html .= '<div class="grid">';

// Emitter box (first)
$html .= '<div class="box">';
$html .= '<strong>Emisor</strong>'
    . '<div class="row"><img class="icon" src="' . h($icoUser) . '" alt="" />' . '<span>' . h($emitterName) . '</span></div>'
    . ($emitterNif ? ('<div class="row"><img class="icon" src="' . h($icoId) . '" alt="" /><span>NIF: ' . h($emitterNif) . '</span></div>') : '')
    . ($emitterAddress !== '' ? ('<div class="row"><img class="icon" src="' . h($icoLocation) . '" alt="" /><span>' . h($emitterAddress) . '</span></div>') : '')
    . (($emitterEmail || $emitterPhone) ? ('<div class="row"><img class="icon" src="' . h($icoContact) . '" alt="" /><span>' . h($emitterEmail) . ($emitterPhone ? ' ' . h($emitterPhone) : '') . '</span></div>') : '')
    . ($emitterIban ? ('<div class="row"><img class="icon" src="' . h($icoId) . '" alt="" /><span>IBAN: ' . h($emitterIban) . '</span></div>') : '')
    . '</div>';

// Client box (second)
$html .= '<div class="box">'
    . '<strong>Cliente</strong>'
    . '<div class="row"><img class="icon" src="' . h($icoUser) . '" alt="" />' . '<span>' . h($clientName) . '</span></div>'
    . ($clientNif !== '' ? ('<div class="row"><img class="icon" src="' . h($icoId) . '" alt="" /><span>NIF: ' . h($clientNif) . '</span></div>') : '')
    . ($clientAddress !== '' ? ('<div class="row"><img class="icon" src="' . h($icoLocation) . '" alt="" /><span>' . h($clientAddress) . '</span></div>') : '')
    . (($clientEmail || $clientPhone) ? ('<div class="row"><img class="icon" src="' . h($icoContact) . '" alt="" /><span>' . h($clientEmail) . ($clientPhone ? ' ' . h($clientPhone) : '') . '</span></div>') : '')
    . '</div>';

$html .= '</div>';

$html .= '<table><thead><tr>
  <th>Descripción</th><th>Cant.</th><th>Precio</th><th>IVA %</th><th>IRPF %</th><th class="right">Importe</th>
</tr></thead><tbody>';
foreach ($items as $it) {
    $qty = (float)$it['quantity'];
    $price = (float)$it['unit_price'];
    $lineBase = $qty * $price;
    $vatRate = $it['vat_rate'] === '' || !isset($it['vat_rate']) ? 21.0 : (float)$it['vat_rate'];
    $irpfRate = $it['irpf_rate'] === '' || !isset($it['irpf_rate']) ? 15.0 : (float)$it['irpf_rate'];
    $lineIva = $lineBase * ($vatRate/100.0);
    $lineIrpf = $lineBase * ($irpfRate/100.0);
    $lineTotal = $lineBase + $lineIva - $lineIrpf;
    $html .= '<tr>'
        . '<td>' . h($it['description']) . '</td>'
        . '<td>' . h($qty) . '</td>'
        . '<td>' . mny($price) . ' €</td>'
        . '<td>' . h(number_format($vatRate, 2)) . '</td>'
        . '<td>' . h(number_format($irpfRate, 2)) . '</td>'
        . '<td class="right">' . mny($lineTotal) . ' €</td>'
        . '</tr>';
}
$html .= '</tbody></table>';

$html .= '<div class="totals">
  <div></div>
  <div class="card">
    <div class="right"><strong>Base:</strong> ' . mny($totals['base']) . ' €</div>
    <div class="right"><strong>IVA:</strong> ' . mny($totals['iva']) . ' €</div>
    <div class="right after-irpf"><strong>IRPF:</strong> ' . mny($totals['irpf']) . ' €</div>
    <div class="grand">Total factura: ' . mny($totals['total']) . ' €</div>
  </div>
</div>';

$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Factura-' . ($inv['invoice_number'] ?: 'borrador-' . $id) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
