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

// Modelo 130 (MVP)
$ingresos01 = $base; // sin IVA
$gastos02 = 0.00; // manual futuro
$rendimiento03 = $ingresos01 - $gastos02;
$cuota04 = $rendimiento03 > 0 ? round($rendimiento03 * 0.20, 2) : 0.00;
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
      <p style="color:var(--gray-600);margin-top:-6px">MVP: ingresos desde facturas; gastos y compensaciones manuales (próximo paso).</p>
      <div class="grid-2">
        <div><strong>Ingresos (01)</strong><br /><?= number_format($ingresos01, 2) ?> €</div>
        <div><strong>Gastos (02)</strong><br /><span title="Pendiente de implementar">0,00 €</span></div>
        <div><strong>Rendimiento (03)</strong><br /><?= number_format($rendimiento03, 2) ?> €</div>
        <div><strong>20% (04)</strong><br /><?= number_format($cuota04, 2) ?> €</div>
      </div>
    </div>
  </div>
</section>
