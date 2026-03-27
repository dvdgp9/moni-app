<?php
use Moni\Repositories\SettingsRepository;
use Moni\Services\TaxQuarterService;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

$allModels = [
    '303' => ['label' => 'Modelo 303', 'description' => 'IVA trimestral'],
    '130' => ['label' => 'Modelo 130', 'description' => 'Pago fraccionado de IRPF'],
    '111' => ['label' => 'Modelo 111', 'description' => 'Retenciones de profesionales y nóminas'],
    '115' => ['label' => 'Modelo 115', 'description' => 'Retenciones por alquiler'],
    '390' => ['label' => 'Modelo 390', 'description' => 'Resumen anual de IVA'],
];
$modelCodes = array_map('strval', array_keys($allModels));

$activityModes = [
    'professional' => 'Profesional / freelance',
    'business' => 'Actividad empresarial',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tax_setup'])) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'CSRF inválido.');
    } else {
        try {
            $selectedModels = $_POST['tax_models'] ?? [];
            $selectedModels = is_array($selectedModels)
                ? array_values(array_intersect($modelCodes, array_map('strval', $selectedModels)))
                : [];
            if (empty($selectedModels)) {
                $selectedModels = ['303', '130'];
            }

            $taxProfile = [
                'activity_mode' => in_array(($_POST['activity_mode'] ?? 'professional'), array_keys($activityModes), true)
                    ? (string)$_POST['activity_mode']
                    : 'professional',
                'issues_invoices_with_irpf' => isset($_POST['issues_invoices_with_irpf']),
                'has_rent_withholdings' => isset($_POST['has_rent_withholdings']),
                'has_payroll_or_professional_withholdings' => isset($_POST['has_payroll_or_professional_withholdings']),
            ];

            if ($taxProfile['has_rent_withholdings'] && !in_array('115', $selectedModels, true)) {
                $selectedModels[] = '115';
            }
            if ($taxProfile['has_payroll_or_professional_withholdings'] && !in_array('111', $selectedModels, true)) {
                $selectedModels[] = '111';
            }
            if (!in_array('390', $selectedModels, true)) {
                $selectedModels[] = '390';
            }

            sort($selectedModels);
            SettingsRepository::set('tax_models', json_encode($selectedModels));
            SettingsRepository::set('tax_profile', json_encode($taxProfile));
            Flash::add('success', 'Centro fiscal actualizado.');
        } catch (Throwable $e) {
            Flash::add('error', 'No se ha podido guardar la configuración fiscal. Inténtalo de nuevo.');
        }
    }
    header('Location: ' . route_path('declaraciones'));
    exit;
}

$storedModels = json_decode((string)(SettingsRepository::get('tax_models') ?? '[]'), true);
$storedModels = is_array($storedModels)
    ? array_values(array_intersect($modelCodes, array_map('strval', $storedModels)))
    : [];
if (empty($storedModels)) {
    $storedModels = ['303', '130', '390'];
}

$storedProfile = json_decode((string)(SettingsRepository::get('tax_profile') ?? '{}'), true);
$storedProfile = is_array($storedProfile) ? $storedProfile : [];
$taxProfile = array_merge([
    'activity_mode' => 'professional',
    'issues_invoices_with_irpf' => true,
    'has_rent_withholdings' => false,
    'has_payroll_or_professional_withholdings' => false,
], $storedProfile);

$y = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$m = (int)date('n');
$defaultQ = (int)ceil($m / 3);
$q = isset($_GET['quarter']) ? (int)$_GET['quarter'] : $defaultQ;
$q = max(1, min(4, $q));

$summary = TaxQuarterService::summarizeSales($y, $q);
$base = $summary['base_total'];
$iva = $summary['iva_total'];
$irpf = $summary['irpf_total'];
$byVat = $summary['by_vat'];
$range = $summary['range'];
$rangeStartEs = (new DateTime($range['start']))->format('d/m/Y');
$rangeEndEs = (new DateTime($range['end']))->format('d/m/Y');

$expensesSummary = TaxQuarterService::summarizeExpenses($y, $q);
$expensesBase = $expensesSummary['base_total'];
$expensesVat = $expensesSummary['vat_total'];
$expensesByVat = $expensesSummary['by_vat'];

$devengado27 = $iva;
$deducible45 = $expensesVat;
$resultado46 = $devengado27 - $deducible45;

$ytd = TaxQuarterService::summarizeSalesYTD($y, $q);
$expensesYtd = TaxQuarterService::summarizeExpensesYTD($y, $q);
$ingresos01 = $ytd['base_total_ytd'];
$gastosRegistrados = $expensesYtd['base_total_ytd'];
$gastosManuales = isset($_GET['gastos_ytd']) && $_GET['gastos_ytd'] !== ''
    ? (float)str_replace(',', '.', (string)$_GET['gastos_ytd'])
    : $gastosRegistrados;
$gastos02 = round($gastosManuales, 2);
$rendimiento03 = $ingresos01 - $gastos02;
$cuota04 = $rendimiento03 > 0 ? round($rendimiento03 * 0.20, 2) : 0.00;

$autoPrev = 0.0;
if ($q > 1) {
    $paidSoFar = 0.0;
    for ($k = 1; $k <= $q - 1; $k++) {
        $ytdK = TaxQuarterService::summarizeSalesYTD($y, $k);
        $ingK = (float)$ytdK['base_total_ytd'];
        $retK = (float)$ytdK['irpf_total_ytd'];
        $rendK = max($ingK, 0.0);
        $c4K = round($rendK * 0.20, 2);
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

$checklist = TaxQuarterService::quarterChecklist($y, $q);
$annualVat = TaxQuarterService::annualVatSummary($y);

$activeModels = array_values(array_filter($storedModels, static fn(string $code): bool => isset($allModels[$code])));
if (empty($activeModels)) {
    $activeModels = ['303', '130', '390'];
}
$show303 = in_array('303', $activeModels, true);
$show130 = in_array('130', $activeModels, true);
$show111 = in_array('111', $activeModels, true);
$show115 = in_array('115', $activeModels, true);
$show390 = in_array('390', $activeModels, true);
$hasFiscalData = abs($base) > 0.0001
    || abs($iva) > 0.0001
    || abs($expensesVat) > 0.0001
    || abs($ingresos01) > 0.0001
    || abs($gastos02) > 0.0001;
$recommendedModels = ['303', '130', '390'];
if (!empty($taxProfile['has_payroll_or_professional_withholdings'])) {
    $recommendedModels[] = '111';
}
if (!empty($taxProfile['has_rent_withholdings'])) {
    $recommendedModels[] = '115';
}
$recommendedModels = array_values(array_unique($recommendedModels));
$missingRecommendedModels = array_values(array_diff($recommendedModels, $activeModels));
$quarterDeadlines = [
    1 => ['window' => '1-20 abril', 'label' => 'Presentacion del 1T'],
    2 => ['window' => '1-20 julio', 'label' => 'Presentacion del 2T'],
    3 => ['window' => '1-20 octubre', 'label' => 'Presentacion del 3T'],
    4 => ['window' => '1-30 enero', 'label' => 'Presentacion del 4T y cierre anual'],
];
$selectedDeadline = $quarterDeadlines[$q];
$selectedDeadlineModels = array_values(array_filter(['303', '130', '111', '115'], static fn(string $code): bool => in_array($code, $activeModels, true)));
if ($q === 4 && in_array('390', $activeModels, true)) {
    $selectedDeadlineModels[] = '390';
}
$selectedDeadlineModels = array_values(array_unique($selectedDeadlineModels));
$currentMonth = (int)date('n');
if ($currentMonth === 1) {
    $nextQuarter = 4;
} elseif ($currentMonth <= 4) {
    $nextQuarter = 1;
} elseif ($currentMonth <= 7) {
    $nextQuarter = 2;
} elseif ($currentMonth <= 10) {
    $nextQuarter = 3;
} else {
    $nextQuarter = 4;
}
$nextDeadline = $quarterDeadlines[$nextQuarter];
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div>
      <h1 style="margin-bottom:6px">Centro fiscal</h1>
      <p style="color:var(--gray-600);max-width:70ch">
        Configura los modelos que te aplican y revisa el trimestre con una vista pensada para saber qué presentar y qué te falta cerrar.
      </p>
    </div>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card declarations-setup-card" style="margin-bottom:16px">
    <div class="section-header">
      <h3 class="section-title">Configuración fiscal</h3>
    </div>
    <form method="post">
      <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
      <input type="hidden" name="save_tax_setup" value="1" />

      <div class="declarations-setup-grid">
        <div class="declarations-setup-activity">
          <label for="activity_mode">Tipo de actividad</label>
          <select id="activity_mode" name="activity_mode">
            <?php foreach ($activityModes as $key => $label): ?>
              <option value="<?= $key ?>" <?= $taxProfile['activity_mode'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="declarations-flags-grid">
          <label class="declarations-flag"><input type="checkbox" name="issues_invoices_with_irpf" value="1" <?= !empty($taxProfile['issues_invoices_with_irpf']) ? 'checked' : '' ?> /> Tus facturas suelen llevar retención de IRPF</label>
          <label class="declarations-flag"><input type="checkbox" name="has_rent_withholdings" value="1" <?= !empty($taxProfile['has_rent_withholdings']) ? 'checked' : '' ?> /> Pagas alquiler con retención</label>
          <label class="declarations-flag"><input type="checkbox" name="has_payroll_or_professional_withholdings" value="1" <?= !empty($taxProfile['has_payroll_or_professional_withholdings']) ? 'checked' : '' ?> /> Pagas profesionales o nóminas con retención</label>
        </div>
      </div>

      <div style="margin-top:12px">
        <label>Modelos que te aplican</label>
        <div class="declarations-models-grid declarations-models-grid-wide">
          <?php foreach ($allModels as $code => $model): ?>
            <label class="declarations-model-option">
              <input type="checkbox" name="tax_models[]" value="<?= htmlspecialchars((string)$code) ?>" <?= in_array((string)$code, $storedModels, true) ? 'checked' : '' ?> />
              <span>
                <strong><?= htmlspecialchars($model['label']) ?></strong>
                <small><?= htmlspecialchars($model['description']) ?></small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-top:12px">
        <button type="submit" class="btn">Guardar configuración</button>
      </div>
    </form>
  </div>

  <form method="get" class="card" style="margin-bottom:16px">
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
      <div style="margin-left:auto;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span id="rangeLabel" style="color:var(--gray-600);white-space:nowrap">Periodo seleccionado: <?= htmlspecialchars($rangeStartEs) ?> — <?= htmlspecialchars($rangeEndEs) ?></span>
        <button type="submit" class="btn">Calcular</button>
      </div>
    </div>
  </form>

  <div class="declarations-guide-grid" style="margin-bottom:16px">
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Guia fiscal</h3>
      </div>
      <div class="declarations-guide-list">
        <div class="declarations-guide-item">
          <strong>Modelos recomendados segun tu perfil</strong>
          <div class="declarations-active-models" style="margin-top:8px">
            <?php foreach ($recommendedModels as $code): ?>
              <span class="declarations-active-chip"><?= htmlspecialchars($allModels[$code]['label']) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="declarations-guide-item">
          <strong>Modelos que presentarias para este trimestre</strong>
          <p style="margin:8px 0 0;color:var(--gray-600)">
            <?= htmlspecialchars($selectedDeadline['label']) ?>: <?= htmlspecialchars($selectedDeadline['window']) ?>.
          </p>
          <div class="declarations-active-models" style="margin-top:8px">
            <?php foreach ($selectedDeadlineModels as $code): ?>
              <span class="declarations-range-pill"><?= htmlspecialchars($allModels[$code]['label']) ?></span>
            <?php endforeach; ?>
            <?php if (empty($selectedDeadlineModels)): ?>
              <span class="declarations-range-pill">Sin modelos activos para este trimestre</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Proximo hito</h3>
      </div>
      <div class="declarations-deadline-card">
        <div class="declarations-deadline-main">
          <span class="declarations-model-badge"><?= htmlspecialchars($nextDeadline['label']) ?></span>
          <strong style="display:block;font-size:1.15rem;margin-top:10px"><?= htmlspecialchars($nextDeadline['window']) ?></strong>
          <p style="color:var(--gray-600);margin:8px 0 0">
            Puedes dejar avisos activos en notificaciones para no llegar justo al cierre.
          </p>
        </div>
        <?php if (!empty($missingRecommendedModels)): ?>
          <div class="alert" style="margin:12px 0 0;background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.18);color:#8a5a00">
            Te faltan por activar: <?= htmlspecialchars(implode(', ', array_map(static fn(string $code): string => $allModels[$code]['label'], $missingRecommendedModels))) ?>.
          </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <a href="<?= route_path('reminders') ?>" class="btn btn-secondary btn-sm">Configurar avisos</a>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$hasFiscalData): ?>
    <div class="alert" style="margin-bottom:16px;background:rgba(15,163,177,0.08);border-color:rgba(15,163,177,0.18)">
      No hay movimientos fiscales en este periodo con estado emitida/pagada para facturas. Revisa el trimestre o el estado de tus documentos.
    </div>
  <?php endif; ?>

  <div class="declarations-results-grid" style="margin-bottom:16px">
    <?php if ($show303): ?>
      <div class="card declarations-model-card declarations-model-primary">
        <div class="declarations-model-head">
          <div>
            <h3>Modelo 303</h3>
            <p>IVA trimestral a partir de ventas y gastos registrados.</p>
          </div>
          <span class="declarations-model-badge">Activo</span>
        </div>
        <div class="declarations-kpi-grid declarations-kpi-grid-4">
          <div class="declarations-kpi-card"><div class="kpi-label">Base ventas</div><div class="kpi-value"><?= number_format($base, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">IVA devengado</div><div class="kpi-value"><?= number_format($devengado27, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">IVA deducible</div><div class="kpi-value"><?= number_format($deducible45, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">Resultado</div><div class="kpi-value" style="<?= $resultado46 < 0 ? 'color:var(--success)' : '' ?>"><?= number_format($resultado46, 2) ?> €</div></div>
        </div>

        <?php if (!empty($byVat) || !empty($expensesByVat)): ?>
          <div class="declarations-breakdown-grid">
            <?php if (!empty($byVat)): ?>
              <div>
                <h4>IVA repercutido</h4>
                <table class="table" style="font-size:0.9rem">
                  <thead><tr><th>Tipo</th><th style="text-align:right">Base</th><th style="text-align:right">Cuota</th></tr></thead>
                  <tbody>
                    <?php foreach ($byVat as $rate => $t): ?>
                      <tr>
                        <td><?= htmlspecialchars($rate) ?>%</td>
                        <td style="text-align:right"><?= number_format($t['base'], 2) ?> €</td>
                        <td style="text-align:right"><?= number_format($t['iva'], 2) ?> €</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            <?php if (!empty($expensesByVat)): ?>
              <div>
                <h4>IVA soportado</h4>
                <table class="table" style="font-size:0.9rem">
                  <thead><tr><th>Tipo</th><th style="text-align:right">Base</th><th style="text-align:right">Cuota</th></tr></thead>
                  <tbody>
                    <?php foreach ($expensesByVat as $rate => $t): ?>
                      <tr>
                        <td><?= htmlspecialchars($rate) ?>%</td>
                        <td style="text-align:right"><?= number_format($t['base'], 2) ?> €</td>
                        <td style="text-align:right"><?= number_format($t['vat'], 2) ?> €</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($show130): ?>
      <div class="card declarations-model-card">
        <div class="declarations-model-head">
          <div>
            <h3>Modelo 130</h3>
            <p>Estimación acumulada desde el 1 de enero hasta el cierre del trimestre.</p>
          </div>
          <span class="declarations-model-badge">Activo</span>
        </div>
        <div class="declarations-kpi-grid declarations-kpi-grid-5">
          <div class="declarations-kpi-card"><div class="kpi-label">Ingresos</div><div class="kpi-value"><?= number_format($ingresos01, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">Gastos</div><div class="kpi-value"><?= number_format($gastos02, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">Rendimiento</div><div class="kpi-value"><?= number_format($rendimiento03, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">20%</div><div class="kpi-value"><?= number_format($cuota04, 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">A ingresar</div><div class="kpi-value"><?= number_format($casilla7, 2) ?> €</div></div>
        </div>
        <div class="sep"></div>
        <form method="get" class="form-band">
          <input type="hidden" name="year" value="<?= (int)$y ?>" />
          <input type="hidden" name="quarter" value="<?= (int)$q ?>" />
          <div class="form-grid-3">
            <div>
              <label style="font-weight:600;font-size:0.9rem">Gastos acumulados</label>
              <input type="text" name="gastos_ytd" value="<?= htmlspecialchars((string)$gastosManuales) ?>" placeholder="0,00" />
              <div class="hint">Auto: <?= number_format($gastosRegistrados, 2) ?> €</div>
            </div>
            <div>
              <label style="font-weight:600;font-size:0.9rem">Pagos previos</label>
              <input type="text" name="prev_payments" value="<?= htmlspecialchars((string)$casilla5_prev) ?>" placeholder="0,00" />
              <div class="hint">Auto: <?= number_format($autoPrev, 2) ?> €</div>
            </div>
            <div>
              <label style="font-weight:600;font-size:0.9rem">Retenciones acumuladas</label>
              <input type="text" name="retenciones" value="<?= htmlspecialchars((string)$casilla6_ret) ?>" placeholder="0,00" />
              <div class="hint">Auto: <?= number_format($autoRetenciones, 2) ?> €</div>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:12px">
            <button type="submit" class="btn btn-sm">Recalcular 130</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid-2" style="margin-top:16px">
    <?php if ($show111): ?>
      <div class="card">
        <h3>Modelo 111</h3>
        <p style="color:var(--gray-600)">Lo hemos dejado preparado en el centro fiscal, pero el cálculo automático todavía no está integrado en esta fase.</p>
        <div class="form-band">
          <strong>Qué conviene revisar</strong>
          <ul style="margin:8px 0 0 18px;color:var(--gray-700)">
            <li>Pagos a profesionales con retención</li>
            <li>Nóminas o servicios sujetos a retención</li>
            <li>Importes retenidos durante el trimestre</li>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($show115): ?>
      <div class="card">
        <h3>Modelo 115</h3>
        <p style="color:var(--gray-600)">Visible porque has indicado que gestionas alquiler con retención. Lo dejamos preparado como recordatorio centralizado.</p>
        <div class="form-band">
          <strong>Qué conviene revisar</strong>
          <ul style="margin:8px 0 0 18px;color:var(--gray-700)">
            <li>Importe del alquiler del trimestre</li>
            <li>Retención practicada</li>
            <li>Datos del arrendador</li>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($show390): ?>
      <div class="card">
        <h3>Modelo 390</h3>
        <p style="color:var(--gray-600)">Resumen anual de IVA para tener visibilidad del ejercicio completo.</p>
        <div class="declarations-kpi-grid declarations-kpi-grid-4">
          <div class="declarations-kpi-card"><div class="kpi-label">Base ventas año</div><div class="kpi-value"><?= number_format($annualVat['sales_base'], 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">IVA ventas año</div><div class="kpi-value"><?= number_format($annualVat['sales_vat'], 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">IVA gastos año</div><div class="kpi-value"><?= number_format($annualVat['expenses_vat'], 2) ?> €</div></div>
          <div class="declarations-kpi-card"><div class="kpi-label">Resultado anual</div><div class="kpi-value"><?= number_format($annualVat['result'], 2) ?> €</div></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>Checklist de cierre</h3>
      <p style="color:var(--gray-600)">Una guía rápida para saber qué conviene revisar antes de dar por cerrado el trimestre.</p>
      <ul class="kv">
        <li><span>Borradores pendientes:</span> <span><?= (int)$checklist['draft_invoices'] ?></span></li>
        <li><span>Gastos por revisar:</span> <span><?= (int)$checklist['pending_expenses'] ?></span></li>
        <li><span>Gastos sin proveedor:</span> <span><?= (int)$checklist['unlinked_suppliers'] ?></span></li>
        <li><span>Gastos en “otros”:</span> <span><?= (int)$checklist['uncategorized_expenses'] ?></span></li>
        <li><span>Facturas emitidas sin cobrar:</span> <span><?= (int)$checklist['unpaid_issued'] ?></span></li>
      </ul>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <a href="<?= route_path('invoices') ?>" class="btn btn-sm btn-secondary">Ver facturas</a>
        <a href="<?= route_path('expenses') ?>" class="btn btn-sm btn-secondary">Ver gastos</a>
      </div>
    </div>
  </div>
</section>

<style>
.declarations-setup-grid {
  display: grid;
  grid-template-columns: minmax(240px, 360px) 1fr;
  gap: 12px;
  align-items: start;
}
.declarations-setup-activity select {
  margin-bottom: 0;
}
.declarations-models-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.declarations-models-grid-wide {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.declarations-model-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0.72rem 0.85rem;
  border-radius: 14px;
  border: 1px solid rgba(15,35,31,0.08);
  background: rgba(255,255,255,0.9);
  min-height: 60px;
  cursor: pointer;
}
.declarations-model-option input[type="checkbox"] {
  width: auto;
  margin: 0;
  flex: 0 0 auto;
}
.declarations-model-option span {
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.2;
}
.declarations-model-option strong {
  font-size: 0.98rem;
}
.declarations-model-option small {
  color: var(--gray-600);
  font-size: 0.84rem;
}
.declarations-flags-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.declarations-flag {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0.72rem 0.85rem;
  border-radius: 14px;
  background: rgba(15,163,177,0.06);
  border: 1px solid rgba(15,163,177,0.1);
  font-size: 0.94rem;
  cursor: pointer;
}
.declarations-flag input[type="checkbox"] {
  width: auto;
  margin: 0;
}
.declarations-active-models {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
}
.declarations-active-chip,
.declarations-range-pill,
.declarations-model-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.45rem 0.8rem;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
}
.declarations-active-chip {
  background: rgba(15,163,177,0.1);
  color: var(--primary-dark);
}
.declarations-range-pill {
  background: rgba(15,35,31,0.06);
  color: var(--gray-700);
}
.declarations-model-badge {
  background: rgba(132,204,22,0.16);
  color: #447407;
}
.declarations-guide-grid {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 14px;
}
.declarations-guide-list {
  display: grid;
  gap: 12px;
}
.declarations-guide-item {
  padding: 12px 14px;
  background: #f7f9fc;
  border: 1px solid #e6ebf1;
  border-radius: 12px;
}
.declarations-deadline-card {
  min-height: 100%;
  display: flex;
  flex-direction: column;
}
.declarations-deadline-main {
  padding: 12px 14px;
  background: linear-gradient(180deg, rgba(15, 163, 177, 0.08), rgba(247,249,252,0.9));
  border: 1px solid #d9edf0;
  border-radius: 12px;
}
.declarations-results-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.declarations-kpi-grid {
  display: grid;
  gap: 10px;
  margin-top: 2px;
}
.declarations-kpi-grid-4 {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.declarations-kpi-grid-5 {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.declarations-kpi-card {
  background: #f7f9fc;
  border: 1px solid #e6ebf1;
  border-radius: 10px;
  padding: 10px 10px 12px;
  min-height: 72px;
}
.declarations-kpi-card .kpi-label {
  margin-bottom: 6px;
}
.declarations-kpi-card .kpi-value {
  font-size: 1.08rem;
}
.declarations-model-card h3 {
  margin-bottom: 4px;
}
.declarations-model-card p {
  color: var(--gray-600);
  margin-bottom: 0;
}
.declarations-model-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 14px;
}
.declarations-model-primary {
  border: 1px solid rgba(15,163,177,0.16);
  box-shadow: 0 16px 36px rgba(15, 163, 177, 0.08);
}
.declarations-breakdown-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 14px;
}
.declarations-breakdown-grid h4 {
  font-size: 0.9rem;
  margin: 0 0 8px;
  color: var(--gray-700);
}
.declarations-breakdown-grid .table {
  background: #f7f9fc;
  border: 1px solid #e6ebf1;
  border-radius: 10px;
  overflow: hidden;
}
@media (max-width: 980px) {
  .declarations-setup-grid,
  .declarations-models-grid,
  .declarations-flags-grid,
  .declarations-models-grid-wide,
  .declarations-guide-grid,
  .declarations-results-grid,
  .declarations-breakdown-grid,
  .declarations-kpi-grid-4,
  .declarations-kpi-grid-5 {
    grid-template-columns: 1fr;
  }
}
</style>

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
