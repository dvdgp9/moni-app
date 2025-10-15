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
$aplicarDdj = isset($_GET['ddj']) && $_GET['ddj'] === '1';
$baseParaDdj = max($ingresos01 - $gastosManuales, 0.0);
$ddj = $aplicarDdj ? min(round($baseParaDdj * 0.05, 2), 2000.0) : 0.0;
$gastos02 = round($gastosManuales + $ddj, 2);
$rendimiento03 = $ingresos01 - $gastos02;
$cuota04 = $rendimiento03 > 0 ? round($rendimiento03 * 0.20, 2) : 0.00;
$casilla5_prev = isset($_GET['prev_payments']) ? (float)str_replace(',', '.', (string)$_GET['prev_payments']) : 0.0;
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
      <div class="grid-2">
        <div><strong>Base imponible (ventas)</strong><br /><?= number_format($base, 2) ?> €</div>
        <div><strong>IVA devengado (27)</strong><br /><?= number_format($devengado27, 2) ?> €</div>
        <div><strong>IVA deducible (45)</strong><br /><span title="Pendiente de implementar">0,00 €</span></div>
        <div><strong>Resultado (46)</strong><br /><?= number_format($resultado46, 2) ?> €</div>
      </div>
      <?php if (!empty($byVat)): ?>
        <div style="margin-top:10px">
          <table class="table">
            <thead>
              <tr><th>Tipo IVA</th><th>Base</th><th>Cuota</th></tr>
            </thead>
            <tbody>
              <?php foreach ($byVat as $rate => $t): ?>
                <tr>
                  <td><?= htmlspecialchars($rate) ?>%</td>
                  <td><?= number_format($t['base'], 2) ?> €</td>
                  <td><?= number_format($t['iva'], 2) ?> €</td>
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
      <div class="grid-2">
        <div><strong>Ingresos (01)</strong><br /><?= number_format($ingresos01, 2) ?> €</div>
        <div>
          <form method="get" style="display:flex;flex-direction:column;gap:6px">
            <input type="hidden" name="page" value="declaraciones" />
            <input type="hidden" name="year" value="<?= (int)$y ?>" />
            <input type="hidden" name="quarter" value="<?= (int)$q ?>" />
            <label style="font-weight:600">Gastos (02)</label>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input type="text" name="gastos_ytd" value="<?= htmlspecialchars((string)($gastosManuales)) ?>" placeholder="0,00" style="width:140px" />
              <label style="display:flex;align-items:center;gap:6px;font-size:0.9rem;color:var(--gray-700)">
                <input type="checkbox" name="ddj" value="1" <?= $aplicarDdj?'checked':'' ?> /> Añadir 5% gastos de difícil justificación (máx. 2.000 €/año)
              </label>
              <?php if ($aplicarDdj): ?>
                <span style="color:var(--gray-600);font-size:0.9rem">Añadidos: <?= number_format($ddj, 2) ?> €</span>
              <?php endif; ?>
            </div>
            <div class="grid-2">
              <div>
                <label style="font-weight:600">Pagos previos (5)</label>
                <input type="text" name="prev_payments" value="<?= htmlspecialchars((string)$casilla5_prev) ?>" placeholder="0,00" />
              </div>
              <div>
                <label style="font-weight:600">Retenciones acumuladas (6)</label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                  <input type="text" name="retenciones" value="<?= htmlspecialchars((string)$casilla6_ret) ?>" placeholder="0,00" />
                  <span style="color:var(--gray-600);font-size:0.9rem">Auto: <?= number_format($autoRetenciones, 2) ?> €</span>
                </div>
              </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end">
              <button type="submit" class="btn btn-sm">Recalcular</button>
            </div>
          </form>
        </div>
        <div><strong>Rendimiento (03)</strong><br /><?= number_format($rendimiento03, 2) ?> €</div>
        <div><strong>20% (04)</strong><br /><?= number_format($cuota04, 2) ?> €</div>
        <div><strong>Pago fraccionado (7)</strong><br /><?= number_format($casilla7, 2) ?> €</div>
      </div>
    </div>
  </div>
</section>
