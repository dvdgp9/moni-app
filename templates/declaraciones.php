<?php
use Moni\Services\TaxQuarterService;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

$y = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$m = (int)date('n');
$defaultQ = (int)ceil($m / 3);
$q = isset($_GET['quarter']) ? (int)$_GET['quarter'] : $defaultQ;
$q = max(1, min(4, $q));

$summary = TaxQuarterService::summarizeSales($y, $q);
$base = $summary['base_total'];
$iva = $summary['iva_total'];
$irpf = $summary['irpf_total']; // informativo
$byVat = $summary['by_vat'];
$range = $summary['range'];
$rangeStartEs = (new DateTime($range['start']))->format('d/m/Y');
$rangeEndEs = (new DateTime($range['end']))->format('d/m/Y');

// Modelo 303 (MVP)
$devengado27 = $iva; // total cuota devengada
$deducible45 = 0.00; // manual en futuras iteraciones
$resultado46 = $devengado27 - $deducible45;

// Modelo 130 (YTD acumulado)
$ytd = TaxQuarterService::summarizeSalesYTD($y, $q);
$ingresos01 = $ytd['base_total_ytd'];
$gastosManuales = isset($_GET['gastos_ytd']) ? (float)str_replace(',', '.', (string)$_GET['gastos_ytd']) : 0.0;
$gastos02 = round($gastosManuales, 2);
$rendimiento03 = $ingresos01 - $gastos02;
$cuota04 = $rendimiento03 > 0 ? round($rendimiento03 * 0.20, 2) : 0.00;
$autoPrev = 0.0;
if ($q > 1) {
  $paidSoFar = 0.0;
  for ($k = 1; $k <= $q-1; $k++) {
    $ytdK = TaxQuarterService::summarizeSalesYTD($y, $k);
    $ingK = (float)$ytdK['base_total_ytd'];
    $retK = (float)$ytdK['irpf_total_ytd'];
    $rendK = max($ingK, 0.0);
    $c4K = round($rendK * 0.20, 2);
    // Casilla 7 del trimestre k: 04(k) - pagos previos (hasta k-1) - retenciones YTD(k)
    $c7K = round(max(0.0, $c4K - $paidSoFar - $retK), 2);
    $paidSoFar += $c7K;
  }
  $autoPrev = round($paidSoFar, 2);
}
$casilla5_prev = (isset($_GET['prev_payments']) && $_GET['prev_payments'] !== '')
  ? (float)str_replace(',', '.', (string)$_GET['prev_payments'])
  : $autoPrev;
$autoRetenciones = (float)$ytd['irpf_total_ytd'];
$casilla6_ret = isset($_GET['retenciones']) && $_GET['retenciones'] !== ''
  ? (float)str_replace(',', '.', (string)$_GET['retenciones'])
  : $autoRetenciones;
$casilla7 = round($cuota04 - $casilla5_prev - $casilla6_ret, 2);
?>
<section>
  <h1>Declaraciones</h1>
  <p style="margin-top:-6px;margin-bottom:14px;color:var(--gray-600)">
    Resumen del trimestre para IVA (Modelo 303) y pagos fraccionados IRPF (Modelo 130).
    Se incluyen facturas <strong>Emitidas</strong> y <strong>Pagadas</strong> según su <strong>fecha de factura</strong>.
  </p>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="get" class="card">
    <input type="hidden" name="page" value="declaraciones" />
    <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
      <div>
        <label for="year">Año</label>
        <input id="year" type="number" name="year" value="<?= (int)$y ?>" min="2000" max="2100" style="width:120px" />
      </div>
      <div>
        <label for="quarter">Trimestre</label>
        <select id="quarter" name="quarter" style="min-width:180px">
          <option value="1" <?= $q===1?'selected':'' ?>>1T (01–03)</option>
          <option value="2" <?= $q===2?'selected':'' ?>>2T (04–06)</option>
          <option value="3" <?= $q===3?'selected':'' ?>>3T (07–09)</option>
          <option value="4" <?= $q===4?'selected':'' ?>>4T (10–12)</option>
        </select>
      </div>
      <div style="margin-left:auto;display:flex;gap:12px;align-items:center">
        <span id="rangeLabel" style="color:var(--gray-600);white-space:nowrap">Periodo seleccionado: <?= htmlspecialchars($rangeStartEs) ?> — <?= htmlspecialchars($rangeEndEs) ?></span>
        <button type="submit" class="btn">Calcular</button>
      </div>
    </div>
  </form>

  <script>
  (function(){
    const yearEl = document.getElementById('year');
    const qEl = document.getElementById('quarter');
    const label = document.getElementById('rangeLabel');
    function fmt(d){
      const dd = String(d.getDate()).padStart(2,'0');
      const mm = String(d.getMonth()+1).padStart(2,'0');
      const yy = d.getFullYear();
      return dd+'/'+mm+'/'+yy;
    }
    function update(){
      const y = parseInt(yearEl.value,10)||new Date().getFullYear();
      const q = parseInt(qEl.value,10)||1;
      let start, end;
      if (q===1){ start=new Date(y,0,1); end=new Date(y,2,31); }
      else if (q===2){ start=new Date(y,3,1); end=new Date(y,5,30); }
      else if (q===3){ start=new Date(y,6,1); end=new Date(y,8,30); }
      else { start=new Date(y,9,1); end=new Date(y,11,31); }
      if (label) label.textContent = 'Periodo seleccionado: ' + fmt(start) + ' — ' + fmt(end);
    }
    yearEl && yearEl.addEventListener('input', update);
    qEl && qEl.addEventListener('change', update);
  })();
  </script>

  <div class="grid-2">
    <div class="card">
      <h3>Modelo 303 — IVA</h3>
      <p style="color:var(--gray-600);margin-top:-6px">MVP: solo devengado por ventas registradas en Moni.</p>
      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:10px">
        <div class="stat">
          <div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Base imponible (ventas)</div>
          <div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($base, 2) ?> €</div>
        </div>
        <div class="stat">
          <div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">IVA devengado (27)</div>
          <div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($devengado27, 2) ?> €</div>
        </div>
        <div class="stat">
          <div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">IVA deducible (45)</div>
          <div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;">0,00 €</div>
        </div>
        <div class="stat">
          <div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Resultado (46)</div>
          <div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($resultado46, 2) ?> €</div>
        </div>
      </div>
      <?php if (!empty($byVat)): ?>
        <div style="margin-top:14px">
          <table class="table">
            <thead>
              <tr>
                <th style="background:var(--gray-50);color:var(--gray-700);font-weight:600">Tipo IVA</th>
                <th style="background:var(--gray-50);color:var(--gray-700);font-weight:600;text-align:right">Base</th>
                <th style="background:var(--gray-50);color:var(--gray-700);font-weight:600;text-align:right">Cuota</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($byVat as $rate => $t): ?>
                <tr>
                  <td><?= htmlspecialchars($rate) ?>%</td>
                  <td style="text-align:right;white-space:nowrap;"><?= number_format($t['base'], 2) ?> €</td>
                  <td style="text-align:right;white-space:nowrap;"><?= number_format($t['iva'], 2) ?> €</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Modelo 130 — IRPF</h3>
      <p style="color:var(--gray-600);margin-top:-6px">Acumulado desde el 1 de enero hasta el fin del trimestre seleccionado.</p>
      <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px;margin-top:10px">
        <div class="stat"><div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Ingresos (01)</div><div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($ingresos01, 2) ?> €</div></div>
        <div class="stat"><div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Gastos (02)</div><div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($gastos02, 2) ?> €</div></div>
        <div class="stat"><div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Rendimiento (03)</div><div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($rendimiento03, 2) ?> €</div></div>
        <div class="stat"><div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">20% (04)</div><div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($cuota04, 2) ?> €</div></div>
        <div class="stat"><div class="stat-label" style="font-size:0.85rem;color:var(--gray-600);">Pago fraccionado (7)</div><div class="stat-value" style="font-size:1.15rem;font-weight:700;white-space:nowrap;"><?= number_format($casilla7, 2) ?> €</div></div>
      </div>
      <div style="height:1px;background:#EEF2F7;margin:12px 0"></div>
      <form method="get" style="padding:12px;background:var(--gray-50);border-radius:8px">
        <input type="hidden" name="page" value="declaraciones" />
        <input type="hidden" name="year" value="<?= (int)$y ?>" />
        <input type="hidden" name="quarter" value="<?= (int)$q ?>" />
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:end">
          <div>
            <label style="font-weight:600;font-size:0.9rem">Gastos (02)</label>
            <input type="text" name="gastos_ytd" value="<?= htmlspecialchars((string)($gastosManuales)) ?>" placeholder="0,00" />
            <div style="font-size:0.85rem;color:var(--gray-600);margin-top:4px">Introduce el total acumulado del año.</div>
          </div>
          <div>
            <label style="font-weight:600;font-size:0.9rem">Pagos previos (5)</label>
            <input type="text" name="prev_payments" value="<?= htmlspecialchars((string)$casilla5_prev) ?>" placeholder="0,00" />
            <div style="font-size:0.85rem;color:var(--gray-600);margin-top:4px">Auto: <?= number_format($autoPrev, 2) ?> €</div>
          </div>
          <div>
            <label style="font-weight:600;font-size:0.9rem">Retenciones acumuladas (6)</label>
            <input type="text" name="retenciones" value="<?= htmlspecialchars((string)$casilla6_ret) ?>" placeholder="0,00" />
            <div style="font-size:0.85rem;color:var(--gray-600);margin-top:4px">Auto: <?= number_format($autoRetenciones, 2) ?> €</div>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px">
          <button type="submit" class="btn btn-sm">Recalcular</button>
        </div>
      </form>
    </div>
  </div>
</section>
