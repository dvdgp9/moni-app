<?php
use Moni\Repositories\SettingsRepository;
use Moni\Repositories\TaxDeclarationsRepository;
use Moni\Repositories\UsersRepository;
use Moni\Services\AuthService;
use Moni\Services\TaxDeclarationService;
use Moni\Services\TaxQuarterService;
use Moni\Support\Csrf;
use Moni\Support\Flash;
use Moni\Support\TaxAmount;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$money = static fn(float $value): string => TaxAmount::format($value) . ' €';
$allModels = ['303'=>'IVA trimestral','130'=>'Pago fraccionado de IRPF','111'=>'Retenciones de profesionales y nóminas','115'=>'Retenciones por alquiler','390'=>'Resumen anual de IVA'];
[$defaultYear, $defaultQuarter] = TaxDeclarationService::defaultPeriod();
$year = max(2000, min(2100, (int)($_GET['year'] ?? $defaultYear)));
$quarter = max(1, min(4, (int)($_GET['quarter'] ?? $defaultQuarter)));
$model = in_array((string)($_GET['model'] ?? '303'), ['303','130'], true) ? (string)($_GET['model'] ?? '303') : '303';
$url = static fn(array $params = []): string => route_path('declaraciones', array_merge(['year'=>$year,'quarter'=>$quarter,'model'=>$model],$params));
$instructions = [
    '303'=>'https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html',
    '130'=>'https://sede.agenciatributaria.gob.es/Sede/impuestos-tasas/impuesto-sobre-renta-personas-fisicas/modelo-130-irpf______esionales-estimacion-directa-fraccionado_/instrucciones.html',
];
$aeat = [
    '303'=>'https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G414.shtml',
    '130'=>'https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G601.shtml',
];
$storedModels = json_decode((string)SettingsRepository::get('tax_models'), true);
$storedModels = is_array($storedModels) ? array_map('strval',$storedModels) : ['303','130','390'];
$profile = json_decode((string)SettingsRepository::get('tax_profile'), true);
$profile = is_array($profile) ? $profile : [];
$history = TaxDeclarationsRepository::history();
$draft = TaxDeclarationsRepository::draft($model,$year,$quarter);
$input = $draft['input'] ?? [];
if ($model === '130' && !array_key_exists('previous_net',$input)) {
    $annualNet = SettingsRepository::get('tax_previous_net_' . $year);
    if ($annualNet !== null && $annualNet !== '') { $input['previous_net'] = (float)$annualNet; }
}
$sales = $model === '130' ? TaxQuarterService::summarizeSalesYTD($year,$quarter) : TaxQuarterService::summarizeSales($year,$quarter);
$expenses = $model === '130' ? TaxQuarterService::summarizeExpensesYTD($year,$quarter) : TaxQuarterService::summarizeExpenses($year,$quarter);
$range = TaxQuarterService::quarterRange($year,$quarter);
$checklist = TaxQuarterService::quarterChecklist($year,$quarter);
$pendingExpenses = (int)$checklist['pending_expenses'];
if ($model === '130') {
    $pendingExpenses = 0;
    for ($k = 1; $k <= $quarter; $k++) { $pendingExpenses += (int)TaxQuarterService::quarterChecklist($year,$k)['pending_expenses']; }
}
$user = UsersRepository::find((int)AuthService::userId()) ?? [];
$error = null;
$rawBoxes = [];
$action = (string)($_POST['tax_action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate($_POST['_token'] ?? null)) { throw new InvalidArgumentException('La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.'); }
        if ($action === 'setup') {
            $selected = $_POST['tax_models'] ?? [];
            $selected = is_array($selected) ? array_values(array_intersect(array_keys($allModels),array_map('strval',$selected))) : [];
            SettingsRepository::set('tax_models',json_encode($selected,JSON_THROW_ON_ERROR));
            $profile['activity_mode'] = ($_POST['activity_mode'] ?? '') === 'business' ? 'business' : 'professional';
            foreach (['issues_invoices_with_irpf','has_rent_withholdings','has_payroll_or_professional_withholdings'] as $flag) { $profile[$flag] = isset($_POST[$flag]); }
            SettingsRepository::set('tax_profile',json_encode($profile,JSON_THROW_ON_ERROR));
            Flash::add('success','Configuración fiscal guardada.');
            moni_redirect($url(), 303);
        }
        if ($action === 'import_history') {
            $importModel = (string)($_POST['history_model'] ?? '');
            if (!in_array($importModel,['130','303'],true)) { throw new InvalidArgumentException('Selecciona un modelo válido.'); }
            $importYear = filter_var($_POST['history_year'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>2000,'max_range'=>2100]]);
            $importQuarter = filter_var($_POST['history_quarter'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4]]);
            if ($importYear === false || $importQuarter === false) { throw new InvalidArgumentException('Revisa el año y el trimestre del historial.'); }
            $receipt = trim((string)($_POST['history_receipt'] ?? ''));
            if (!preg_match('/^' . $importModel . '\d{10}$/D',$receipt)) { throw new InvalidArgumentException('El justificante debe tener 13 dígitos y empezar por el número del modelo.'); }
            $filedDate = trim((string)($_POST['history_date'] ?? ''));
            $date = DateTimeImmutable::createFromFormat('!Y-m-d',$filedDate);
            if (!$date || $date->format('Y-m-d') !== $filedDate || $filedDate > date('Y-m-d')) { throw new InvalidArgumentException('Introduce una fecha de presentación válida.'); }
            $boxes = [];
            foreach ($importModel === '130' ? ['07','15','16','19'] : ['71','87','72'] as $box) {
                $boxes[$box] = TaxAmount::parse((string)($_POST['history_boxes'][$importModel][$box] ?? ''));
                if (in_array($box,['15','16','87','72'],true) && $boxes[$box] < 0) { throw new InvalidArgumentException("La casilla $box del historial debe ser positiva o cero."); }
            }
            if ($importModel === '303' && $boxes['72'] > max(0,-$boxes['71'])) { throw new InvalidArgumentException('El importe a compensar no puede superar el resultado negativo.'); }
            foreach ($history as $row) { if ($row['receipt'] === $receipt) { throw new InvalidArgumentException('Ese justificante ya está registrado.'); } }
            TaxDeclarationsRepository::addSubmission(['model'=>$importModel,'year'=>$importYear,'quarter'=>$importQuarter,'boxes'=>$boxes,'result'=>$boxes[$importModel === '130' ? '19' : '71'],'receipt'=>$receipt,'filed_date'=>$filedDate,'origin'=>'manual','outcome'=>'Registrada desde la AEAT']);
            Flash::add('success','Declaración anterior registrada. Ya se puede usar para calcular los saldos siguientes.');
            moni_redirect($url() . '#historial', 303);
        }
        if ($action === 'save') {
            $input = TaxDeclarationService::input($_POST,$model);
            $draft = ['input'=>$input,'saved_at'=>date('Y-m-d H:i:s')];
            TaxDeclarationsRepository::saveDraft($model,$year,$quarter,$draft);
            if ($model === '130' && $input['previous_net'] !== null) { SettingsRepository::set('tax_previous_net_' . $year,(string)$input['previous_net']); }
            Flash::add('success','Borrador guardado y cálculo actualizado.');
            moni_redirect($url() . '#casillas', 303);
        }
        if ($action === 'review' || $action === 'presented') {
            $calculation = TaxDeclarationService::calculate($model,$year,$quarter,$sales,$expenses,$input,$history);
            $signature = TaxDeclarationService::signature($calculation,$input);
            if (!$calculation['ready']) { throw new InvalidArgumentException('Completa los datos y la revisión antes de guardar este estado.'); }
            if (!hash_equals($signature,(string)($_POST['signature'] ?? ''))) { throw new InvalidArgumentException('Los datos han cambiado. Revisa y guarda el cálculo actualizado.'); }
            if ($action === 'review') {
                $draft['reviewed_signature'] = $signature;
                $draft['reviewed_at'] = date('Y-m-d H:i:s');
                TaxDeclarationsRepository::saveDraft($model,$year,$quarter,$draft);
                Flash::add('success','Declaración marcada como revisada. Puedes usar la guía para rellenar la AEAT.');
                moni_redirect($url() . '#casillas', 303);
            }
            if (!hash_equals($signature,(string)($draft['reviewed_signature'] ?? ''))) { throw new InvalidArgumentException('Marca primero la declaración como revisada.'); }
            $receipt = trim((string)($_POST['receipt'] ?? ''));
            if (!preg_match('/^' . $model . '\d{10}$/D',$receipt) || empty($_POST['confirm_presented'])) { throw new InvalidArgumentException('Indica el justificante y confirma que ya la has presentado en la AEAT.'); }
            $filedDate = (string)($_POST['filed_date'] ?? '');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d',$filedDate);
            if (!$date || $date->format('Y-m-d') !== $filedDate || $filedDate > date('Y-m-d')) { throw new InvalidArgumentException('Introduce una fecha de presentación válida.'); }
            $samePeriod = false;
            foreach ($history as $row) {
                if ($row['receipt'] === $receipt) { throw new InvalidArgumentException('Ese justificante ya está registrado.'); }
                if ($row['model'] === $model && (int)$row['year'] === $year && (int)$row['quarter'] === $quarter) { $samePeriod = true; }
            }
            if ($samePeriod && empty($input['complementary'])) { throw new InvalidArgumentException('Este período ya está presentado. Para registrar una nueva presentación indica que es complementaria o rectificativa.'); }
            $boxes = array_map(static fn(array $f):float => (float)$f['value'],$calculation['fields']);
            if ($model === '303') {
                $boxes['72'] = $calculation['outcome'] === 'A compensar' ? max(0,-$calculation['result']) : 0.0;
                $boxes['73'] = $calculation['outcome'] === 'A devolver' ? max(0,-$calculation['result']) : 0.0;
            }
            TaxDeclarationsRepository::addSubmission(['model'=>$model,'year'=>$year,'quarter'=>$quarter,'boxes'=>$boxes,'result'=>$calculation['result'],'receipt'=>$receipt,'filed_date'=>$filedDate,'origin'=>'assistant','correction'=>!empty($input['complementary']),'previous_receipt'=>$input['previous_receipt'] ?? '', 'signature'=>$signature,'outcome'=>$calculation['outcome']]);
            Flash::add('success','Presentación registrada. Los importes quedan guardados en el historial.');
            moni_redirect($url() . '#historial', 303);
        }
        throw new InvalidArgumentException('Acción no válida.');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        if ($action === 'save') { $rawBoxes = is_array($_POST['boxes'] ?? null) ? $_POST['boxes'] : []; }
    } catch (Throwable $e) {
        error_log('[declaraciones] ' . $e->getMessage());
        $error = 'No se han podido guardar los cambios. Inténtalo de nuevo.';
    }
}
$calculation = TaxDeclarationService::calculate($model,$year,$quarter,$sales,$expenses,$input,$history);
$fields = $calculation['fields'];
$signature = TaxDeclarationService::signature($calculation,$input);
$reviewed = $calculation['ready'] && hash_equals($signature,(string)($draft['reviewed_signature'] ?? ''));
$currentSubmission = null;
foreach ($history as $row) { if ($row['model'] === $model && (int)$row['year'] === $year && (int)$row['quarter'] === $quarter) { $currentSubmission = $row; break; } }
$status = $currentSubmission ? 'Presentado' : ($reviewed ? 'Revisado' : ($calculation['ready'] ? 'Pendiente de revisión' : 'Faltan datos'));
$flashAll = Flash::getAll();
$periodStart = $model === '130' ? "$year-01-01" : $range['start'];
$periodLabel = (new DateTimeImmutable($periodStart))->format('d/m/Y') . ' — ' . (new DateTimeImmutable($range['end']))->format('d/m/Y');
$deadline = ['1'=>'Abril','2'=>'Julio','3'=>'Octubre','4'=>'Enero'][$quarter];
$deadlineYear = $quarter === 4 ? $year + 1 : $year;
$formInput = $input;
if ($error && $action === 'save') {
    foreach (['review_records','review_special','no_previous','agricultural','housing','complementary','additional_rates_reviewed'] as $flag) { $formInput[$flag] = isset($_POST[$flag]); }
    $formInput['previous_receipt'] = (string)($_POST['previous_receipt'] ?? '');
}
$previousNetText = $error && $action === 'save' ? (string)($_POST['previous_net'] ?? '') : (($input['previous_net'] ?? null) !== null ? TaxAmount::copy((float)$input['previous_net']) : '');
$negativeBalanceText = $error && $action === 'save' ? (string)($_POST['negative_balance'] ?? '') : (($input['negative_balance'] ?? null) !== null ? TaxAmount::copy((float)$input['negative_balance']) : '');
$historyFormData = $error && $action === 'import_history' ? $_POST : [];
$historyFormModel = in_array((string)($historyFormData['history_model'] ?? $model), ['303','130'], true) ? (string)($historyFormData['history_model'] ?? $model) : $model;
$common = $model === '130' ? ['01','02','05','06','13'] : ['07','09','28','29','110'];
$renderInput = static function (string $box) use ($fields,$input,$rawBoxes,$esc,$money): void {
    $field = $fields[$box];
    $manual = array_key_exists($box,$input['overrides'] ?? []);
    $value = $rawBoxes[$box] ?? ($manual ? TaxAmount::copy((float)$input['overrides'][$box]) : '');
    $origin = TaxDeclarationService::SOURCES[$field['source']];
    ?>
    <div class="tax-field">
      <label for="box-<?= $esc($box) ?>"><span class="tax-box-number"><?= $esc($box) ?></span> <?= $esc($field['label']) ?></label>
      <input id="box-<?= $esc($box) ?>" name="boxes[<?= $esc($box) ?>]" type="text" inputmode="decimal" autocomplete="off" value="<?= $esc($value) ?>" placeholder="<?= $field['value'] === null ? 'Introduce el importe' : $esc(TaxAmount::copy($field['value'])) ?>" aria-describedby="hint-<?= $esc($box) ?>" />
      <p id="hint-<?= $esc($box) ?>" class="tax-hint"><?= $esc($origin) ?><?= $field['value'] !== null ? ' · ' . $esc($field['unit'] === '%' ? TaxAmount::format($field['value']) . ' %' : $money($field['value'])) : ' · Dato necesario' ?>. Deja vacío para usar el cálculo automático.</p>
    </div>
    <?php
};
$groups = [];
foreach ($fields as $box=>$field) { $groups[$field['group']][$box] = $field; }
?>
<link rel="stylesheet" href="/assets/css/declarations.css?v=1" />
<section class="tax-center" data-tax-center data-model="<?= $esc($model) ?>" data-year="<?= $year ?>" data-quarter="<?= $quarter ?>" data-unsaved="<?= $error && $action === 'save' ? '1' : '0' ?>">
  <header class="tax-heading">
    <div><p class="tax-eyebrow">FISCAL / DECLARACIONES</p><h1>Prepara tu declaración</h1><p>Revisa tus datos y copia cada importe en su casilla de Hacienda.</p></div>
    <a href="#historial" class="btn tax-btn-quiet">Ver historial</a>
  </header>
  <?php foreach ($flashAll as $type=>$messages): foreach ($messages as $message): ?>
    <div role="status" class="alert <?= $type === 'error' ? 'error' : '' ?>"><?= $esc($message) ?></div>
  <?php endforeach; endforeach; ?>
  <?php if ($error): ?><div role="alert" class="alert error"><?= $esc($error) ?></div><?php endif; ?>

  <form method="get" action="<?= $esc(route_path('declaraciones')) ?>" class="tax-period">
    <input type="hidden" name="model" value="<?= $esc($model) ?>" />
    <div><label for="tax-year">Ejercicio</label><input id="tax-year" name="year" type="number" min="2000" max="2100" value="<?= $year ?>" required /></div>
    <div><label for="tax-quarter">Trimestre</label><select id="tax-quarter" name="quarter"><?php foreach ([1=>'1T · Enero — marzo',2=>'2T · Abril — junio',3=>'3T · Julio — septiembre',4=>'4T · Octubre — diciembre'] as $q=>$label): ?><option value="<?= $q ?>" <?= $quarter === $q ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="btn tax-btn-quiet">Ver período</button>
    <p class="tax-period-context">Presentación en <?= $esc(mb_strtolower($deadline)) ?> de <?= $deadlineYear ?><br><a href="<?= $esc($aeat[$model]) ?>" target="_blank" rel="noopener noreferrer">Consultar plazo y trámite en la AEAT</a></p>
  </form>
  <nav class="tax-model-tabs" aria-label="Modelo de declaración">
    <?php foreach (['303'=>'IVA trimestral','130'=>'IRPF acumulado'] as $code=>$label): ?><a href="<?= $esc($url(['model'=>$code])) ?>" class="tax-model-tab <?= $model === (string)$code ? 'is-active' : '' ?>" <?= $model === (string)$code ? 'aria-current="page"' : '' ?>><span>Modelo <?= $esc($code) ?></span><small><?= $esc($label) ?></small></a><?php endforeach; ?>
  </nav>
  <nav class="tax-steps" aria-label="Preparación de la declaración"><a href="#revisar"><span>1</span> Revisar datos</a><a href="#completar"><span>2</span> Completar ajustes</a><a href="#casillas"><span>3</span> Copiar en la AEAT</a></nav>

  <div class="tax-workspace">
    <form id="tax-draft-form" method="post" action="<?= $esc($url()) ?>" class="tax-editor" data-tax-draft>
      <input type="hidden" name="_token" value="<?= $esc(Csrf::token()) ?>" /><input type="hidden" name="tax_action" value="save" />
      <section id="revisar" class="tax-section">
        <div class="tax-section-heading"><div><p class="tax-eyebrow">PASO 1</p><h2>Revisa tus datos</h2></div><span class="tax-source">De tus registros</span></div>
        <p><?= $model === '130' ? 'Acumulado desde el 1 de enero hasta el cierre del trimestre.' : 'Solo las operaciones del trimestre seleccionado.' ?> <strong><?= $esc($periodLabel) ?></strong></p>
        <div class="tax-records">
          <?php if ($model === '130'): ?>
            <div><span>Ingresos registrados</span><strong><?= $esc($money((float)$sales['base_total_ytd'])) ?></strong></div><div><span>Gastos registrados</span><strong><?= $esc($money((float)$expenses['base_total_ytd'])) ?></strong></div><div><span>Retenciones registradas</span><strong><?= $esc($money((float)$sales['irpf_total_ytd'])) ?></strong></div>
          <?php else: ?>
            <div><span>Base de ventas</span><strong><?= $esc($money((float)$sales['base_total'])) ?></strong></div><div><span>IVA de ventas</span><strong><?= $esc($money((float)$sales['iva_total'])) ?></strong></div><div><span>IVA de gastos</span><strong><?= $esc($money((float)$expenses['vat_total'])) ?></strong></div>
          <?php endif; ?>
        </div>
        <?php if ($pendingExpenses > 0 || $checklist['draft_invoices'] > 0): ?><p class="tax-notice"><?= $pendingExpenses ?> gastos por revisar<?= $model === '130' ? ' en el acumulado anual' : '' ?> y <?= (int)$checklist['draft_invoices'] ?> facturas en borrador en el trimestre. Los gastos registrados están incluidos; los borradores de facturas están excluidos.</p><?php endif; ?>
        <?php if (abs((float)($sales['base_total_ytd'] ?? $sales['base_total'])) < .001 && abs((float)($expenses['base_total_ytd'] ?? $expenses['base_total'])) < .001): ?><p class="tax-notice">No hay movimientos registrados para este período. Comprueba las fechas y añade las operaciones que falten antes de revisar una declaración a cero.</p><?php endif; ?>
        <div class="tax-inline-actions"><a class="btn tax-btn-quiet" href="<?= $esc(route_path('invoices',['year'=>$year])) ?>" target="_blank" rel="noopener">Ver facturas</a><a class="btn tax-btn-quiet" href="<?= $esc(route_path('expenses',['year'=>$year])) ?>" target="_blank" rel="noopener">Ver gastos</a></div>
        <label class="tax-check"><input type="checkbox" name="review_records" <?= !empty($formInput['review_records']) ? 'checked' : '' ?> /><span><?= $model === '130' ? 'He revisado los ingresos, las retenciones y los gastos fiscalmente deducibles, incluidos los ajustes que correspondan.' : 'He revisado las ventas y el IVA deducible de los gastos. Las casillas 28 y 29 solo contienen operaciones interiores corrientes; he separado las demás en los ajustes.' ?></span></label>
      </section>
      <section id="completar" class="tax-section">
        <div class="tax-section-heading"><div><p class="tax-eyebrow">PASO 2</p><h2>Completa lo que falta</h2></div></div>
        <p>Solo introduce importes si falta información o necesitas corregir los registros. Los ajustes se guardan para este modelo y trimestre.</p>
        <?php if ($model === '130'): ?>
          <div class="tax-field tax-year-net"><label for="previous-net">Rendimiento neto de <?= $year - 1 ?></label><input id="previous-net" type="text" inputmode="decimal" name="previous_net" value="<?= $esc($previousNetText) ?>" placeholder="Introduce el rendimiento neto anual" /><p class="tax-hint">Permite calcular la casilla 13. Introduce 0 si no ejerciste actividad económica ese año. Se recuerda para los próximos trimestres de <?= $year ?>.</p></div>
        <?php endif; ?>
        <div class="tax-fields-grid"><?php foreach ($common as $box): $renderInput($box); endforeach; ?></div>
        <?php if ($model === '130'): ?>
          <div class="tax-field"><label for="negative-balance">Resultados negativos anteriores pendientes de deducir</label><input id="negative-balance" name="negative_balance" type="text" inputmode="decimal" value="<?= $esc($negativeBalanceText) ?>" placeholder="<?= $calculation['previous']['complete'] ? $esc(TaxAmount::copy($calculation['previous']['negative'])) : 'Introduce el saldo pendiente' ?>" /><p class="tax-hint">Saldo positivo aún no utilizado del mismo ejercicio. Moni aplica en la casilla 15 solo lo que permite el resultado actual.</p></div>
        <?php endif; ?>
        <label class="tax-check"><input type="checkbox" name="no_previous" <?= !empty($formInput['no_previous']) ? 'checked' : '' ?> /><span><?= $model === '130' ? 'No tengo importes ni resultados negativos de trimestres anteriores de este ejercicio (por ejemplo, inicio de actividad).' : 'No tengo IVA pendiente de compensar de períodos anteriores, incluido el año anterior.' ?></span></label>
        <p class="tax-hint">También puedes <a href="#registrar-anterior">registrar declaraciones anteriores</a> para obtener estos importes del historial.</p>
        <details class="tax-details">
          <summary>Operaciones especiales y otros ajustes</summary>
          <?php if ($model === '130'): ?>
            <label class="tax-check"><input type="checkbox" name="agricultural" <?= !empty($formInput['agricultural']) ? 'checked' : '' ?> /><span>Tengo actividades agrícolas, ganaderas, forestales o pesqueras.</span></label><p class="tax-hint">Estas actividades usan ingresos y retenciones del trimestre, separados del acumulado de las casillas 01 y 06.</p>
            <label class="tax-check"><input type="checkbox" name="housing" <?= !empty($formInput['housing']) ? 'checked' : '' ?> /><span>Me corresponde la deducción por vivienda habitual.</span></label><p class="tax-hint">Comprueba los requisitos, límites y exclusiones en las instrucciones de la AEAT antes de introducir la casilla 16.</p>
          <?php else: ?><p class="tax-hint">Las compras intracomunitarias, importaciones, bienes de inversión y operaciones exentas necesitan su clasificación. Corrige también las casillas 28 y 29 para no duplicar gastos. El asistente calcula el régimen general con devengo y el 100 % atribuible al Estado; otros regímenes requieren revisión fuera de este cálculo.</p><?php endif; ?>
          <label class="tax-check"><input type="checkbox" name="complementary" data-tax-correction <?= !empty($formInput['complementary']) ? 'checked' : '' ?> /><span><?= $model === '130' ? 'Esta declaración es complementaria.' : 'Esta autoliquidación es rectificativa.' ?></span></label>
          <div class="tax-field"><label for="previous-receipt">Justificante de la declaración que corriges</label><input id="previous-receipt" name="previous_receipt" type="text" inputmode="numeric" maxlength="13" pattern="[0-9]{13}" value="<?= $esc($formInput['previous_receipt'] ?? '') ?>" placeholder="13 dígitos" /></div>
          <?php foreach ($groups as $group=>$groupFields): $editable = array_filter($groupFields,static fn(array $f):bool => $f['editable'] && !in_array($f['box'],$common,true)); if (!$editable) { continue; } ?>
            <h3 class="tax-adjustment-title"><?= $esc($group) ?></h3><div class="tax-fields-grid"><?php foreach ($editable as $field): $renderInput($field['box']); endforeach; ?></div>
          <?php endforeach; ?>
        </details>
        <?php if ($model === '303' && $quarter === 4): ?><div class="tax-field"><label for="refund-choice">Si el resultado es negativo</label><select id="refund-choice" name="refund_choice"><option value="compensate" <?= ($input['refund_choice'] ?? '') !== 'refund' ? 'selected' : '' ?>>Dejar a compensar en períodos siguientes</option><option value="refund" <?= ($input['refund_choice'] ?? '') === 'refund' ? 'selected' : '' ?>>Solicitar devolución</option></select></div><?php endif; ?>
        <?php if ($model === '303' && array_diff(array_keys($sales['by_vat']), ['0.00','4.00','10.00','21.00'])): ?><label class="tax-check"><input type="checkbox" name="additional_rates_reviewed" <?= !empty($formInput['additional_rates_reviewed']) ? 'checked' : '' ?> /><span>He trasladado las ventas de otros tipos de IVA a sus casillas adicionales y he comprobado sus cuotas.</span></label><?php endif; ?>
        <label class="tax-check"><input type="checkbox" name="review_special" <?= !empty($formInput['review_special']) ? 'checked' : '' ?> /><span><?= $model === '130' ? 'He comprobado las circunstancias especiales, el porcentaje aplicable y las deducciones. He introducido los ajustes que me corresponden.' : 'Tributo en régimen general, con devengo y al 100 % en territorio común. He comprobado las operaciones especiales y su clasificación.' ?></span></label>
        <div class="tax-save-row"><button type="submit" class="btn">Guardar y ver casillas</button><span class="tax-hint"><?= isset($draft['saved_at']) ? 'Guardado: ' . $esc($draft['saved_at']) : 'Todavía no has guardado este borrador.' ?></span></div>
      </section>
    </form>
    <aside class="tax-summary" aria-label="Resumen de la declaración">
      <div class="tax-summary-card">
        <div class="tax-summary-heading"><span>Modelo <?= $esc($model) ?> · <?= $quarter ?>T <?= $year ?></span><span class="tax-status <?= $reviewed || $currentSubmission ? 'is-reviewed' : '' ?>"><?= $esc($status) ?></span></div>
        <p><?= $calculation['ready'] ? $esc($calculation['outcome']) : 'Resultado provisional' ?></p><strong class="tax-result"><?= $calculation['result'] === null ? 'Pendiente' : $esc($money($calculation['result'])) ?></strong><p class="tax-hint">Casilla <?= $esc($calculation['result_box']) ?> · <?= $model === '130' ? 'Acumulado anual' : 'Trimestre seleccionado' ?></p>
        <?php if ($currentSubmission): ?><p class="tax-notice">Presentado: <?= $esc($money((float)$currentSubmission['result'])) ?><br>Justificante <?= $esc($currentSubmission['receipt']) ?>. El borrador actual se calcula con tus registros actuales; el historial conserva lo presentado.</p><?php endif; ?>
        <?php if ($calculation['issues']): ?><ul class="tax-pending-list"><?php foreach ($calculation['issues'] as $issue): ?><li><?= $esc($issue) ?></li><?php endforeach; ?></ul><?php else: ?><p class="tax-hint">Datos completos. <?= $reviewed ? 'Revisión guardada.' : 'Comprueba las casillas y marca la revisión.' ?></p><?php endif; ?>
        <a href="#casillas" class="btn tax-btn-quiet">Ver guía de casillas</a><p class="tax-hint"><a href="<?= $esc($instructions[$model]) ?>" target="_blank" rel="noopener noreferrer">Instrucciones de la AEAT</a></p>
      </div>
      <div class="tax-identity"><h3>Datos del declarante</h3><p><?= $esc($user['name'] ?? '') ?></p><p><?= $esc(!empty($user['nif']) ? $user['nif'] : 'NIF pendiente en tu perfil') ?></p><a href="<?= $esc(route_path('profile')) ?>">Revisar perfil</a></div>
    </aside>
  </div>

  <section id="casillas" class="tax-section tax-guide">
    <div class="tax-section-heading"><div><p class="tax-eyebrow">PASO 3</p><h2>Copia en el modelo <?= $esc($model) ?></h2><p>En el mismo orden que Hacienda. Las casillas calculadas se muestran para que puedas comprobarlas.</p></div><button type="button" class="btn tax-btn-quiet" data-tax-export>Descargar guía CSV</button></div>
    <p class="tax-dirty-notice" data-tax-dirty hidden role="status">Tienes cambios sin guardar. Guarda y recalcula para actualizar los importes de esta guía.</p>
    <div class="tax-guide-tools"><label class="tax-check"><input type="checkbox" data-tax-show-zero /><span>Mostrar también casillas sin importe</span></label><span class="tax-hint" data-tax-copy-status role="status" aria-live="polite"></span></div>
    <?php foreach ($groups as $group=>$groupFields): ?>
      <div class="tax-guide-group" data-tax-group><h3><?= $esc($group) ?></h3>
        <div class="tax-box-list">
        <?php foreach ($groupFields as $box=>$field):
            $box = (string)$box;
            $isZero = $field['value'] !== null && abs($field['value']) < .001
                && !in_array($box,$common,true) && $box !== $calculation['result_box'];
            $rateBases = ['151'=>'150','166'=>'165','02'=>'01','154'=>'153','05'=>'04','08'=>'07','157'=>'156','169'=>'168','17'=>'16','20'=>'19','23'=>'22','65'=>'64'];
            $isUnusedRate = $model === '303' && $field['unit'] === '%'
                && abs($fields[$rateBases[$box] ?? $box]['value'] ?? 0) < .001;
            $isUnknownOutcome = $model === '303' && $calculation['result'] === null && in_array($box,['72','73'],true);
            $isHidden = $isZero || $isUnusedRate || $isUnknownOutcome;
        ?>
          <div class="tax-box-row <?= (string)$box === $calculation['result_box'] ? 'is-result' : '' ?>" data-tax-row data-zero="<?= $isHidden ? '1' : '0' ?>" data-box="<?= $esc($box) ?>" data-label="<?= $esc($field['label']) ?>" data-value="<?= $field['value'] === null ? '' : $esc(TaxAmount::copy($field['value'])) ?>" <?= $isHidden ? 'hidden' : '' ?>>
            <span class="tax-box-number"><?= $esc($box) ?></span><div class="tax-box-description"><strong><?= $esc($field['label']) ?></strong><span><?= $esc(TaxDeclarationService::SOURCES[$field['source']]) ?><?= $field['aeat_calculated'] && $field['source'] === 'calculated' ? ' · Hacienda la calcula' : ($field['source'] === 'calculated' ? ' · Preparado por Moni' : '') ?></span></div><strong class="tax-box-value"><?= $field['value'] === null ? 'Pendiente' : $esc(TaxAmount::format($field['value']) . ' ' . $field['unit']) ?></strong>
            <button type="button" class="btn tax-copy-button" data-tax-copy <?= $field['value'] === null ? 'disabled' : '' ?> aria-label="Copiar casilla <?= $esc($box) ?>">Copiar</button>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if ($model === '303' && $fields['27']['value'] == 0 && $fields['45']['value'] == 0): ?><p class="tax-hint">Revisa el apartado «Sin actividad» de la AEAT: se marca cuando no se han devengado ni soportado cuotas durante el período.</p><?php endif; ?>
    <?php if ($model === '303' && $calculation['result'] !== null && $calculation['result'] < 0): ?><p class="tax-notice"><?= $calculation['outcome'] === 'A devolver' ? 'Devolución · casilla 73' : 'Compensación · casilla 72' ?>: <strong><?= $esc($money(abs($calculation['result']))) ?></strong>. Selecciona esta opción al presentar en la AEAT.</p><?php endif; ?>
    <?php if (!empty($input['complementary'])): ?><p class="tax-notice"><?= $model === '130' ? 'Marca «Autoliquidación complementaria»' : 'Marca «Autoliquidación rectificativa» e indica el motivo de la rectificación' ?>. Justificante anterior: <strong><?= $esc($input['previous_receipt'] ?? '') ?></strong>.</p><?php endif; ?>
    <div class="tax-guide-footer"><form method="post" action="<?= $esc($url()) ?>" data-tax-state-form><input type="hidden" name="_token" value="<?= $esc(Csrf::token()) ?>" /><input type="hidden" name="tax_action" value="review" /><input type="hidden" name="signature" value="<?= $esc($signature) ?>" /><button type="submit" class="btn" <?= !$calculation['ready'] || $reviewed ? 'disabled' : '' ?>><?= $reviewed ? 'Revisión guardada' : 'Marcar como revisado' ?></button></form><a class="btn tax-btn-quiet" href="<?= $esc($aeat[$model]) ?>" target="_blank" rel="noopener noreferrer">Abrir modelo <?= $esc($model) ?> en la AEAT</a></div>
    <details class="tax-details" <?= $error && $action === 'presented' ? 'open' : '' ?>><summary>Ya la he presentado: guardar justificante</summary><p>Registra la presentación que has realizado en Hacienda. Moni conservará una copia de estos importes para los próximos trimestres.</p><form method="post" action="<?= $esc($url()) ?>" data-tax-state-form><input type="hidden" name="_token" value="<?= $esc(Csrf::token()) ?>" /><input type="hidden" name="tax_action" value="presented" /><input type="hidden" name="signature" value="<?= $esc($signature) ?>" /><div class="tax-fields-grid"><div class="tax-field"><label for="receipt">Número de justificante</label><input id="receipt" name="receipt" value="<?= $error && $action === 'presented' ? $esc($_POST['receipt'] ?? '') : '' ?>" type="text" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" placeholder="13 dígitos" required /></div><div class="tax-field"><label for="filed-date">Fecha de presentación</label><input id="filed-date" name="filed_date" type="date" value="<?= $error && $action === 'presented' ? $esc($_POST['filed_date'] ?? '') : date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required /></div></div><label class="tax-check"><input name="confirm_presented" type="checkbox" required /><span>He presentado en la AEAT esta declaración con estos importes.</span></label><button type="submit" class="btn" <?= !$reviewed ? 'disabled' : '' ?>>Guardar presentación</button><?php if (!$reviewed): ?><p class="tax-hint">Completa los datos y marca la revisión para registrar la presentación.</p><?php endif; ?></form></details>
  </section>

  <section id="historial" class="tax-section">
    <div class="tax-section-heading"><div><h2>Historial de declaraciones</h2><p>Importes reales presentados, conservados aunque cambien tus facturas o gastos.</p></div></div>
    <?php if (!$history): ?><div class="tax-empty"><h3>Todavía no hay declaraciones registradas</h3><p>Añade los importes de las anteriores para que Moni pueda recuperar pagos y compensaciones.</p><a href="#registrar-anterior" class="btn tax-btn-quiet">Registrar una declaración anterior</a></div><?php else: ?>
      <div class="tax-history-list"><?php foreach ($history as $record): ?><details class="tax-history-entry"><summary><span><strong>Modelo <?= $esc($record['model']) ?> · <?= (int)$record['quarter'] ?>T <?= (int)$record['year'] ?></strong><small>Presentado el <?= $esc((new DateTimeImmutable($record['filed_date']))->format('d/m/Y')) ?> · <?= $esc($record['receipt']) ?></small></span><strong><?= $esc($money((float)$record['result'])) ?></strong></summary><p><?= $esc($record['outcome'] ?? '') ?></p><div class="tax-history-boxes"><?php foreach ($record['boxes'] as $box=>$value): ?><span><strong><?= $esc($box) ?></strong> <?= $esc($money((float)$value)) ?></span><?php endforeach; ?></div><a href="<?= $esc($url(['model'=>$record['model'],'year'=>$record['year'],'quarter'=>$record['quarter']])) ?>">Abrir período</a></details><?php endforeach; ?></div>
    <?php endif; ?>
    <details id="registrar-anterior" class="tax-details" <?= $error && $action === 'import_history' ? 'open' : '' ?>><summary>Registrar una declaración ya presentada</summary><p>Copia estos datos de tu justificante. Introduce 0 cuando la casilla esté vacía. Si es una corrección, registra los importes de la última declaración válida.</p><form method="post" action="<?= $esc($url()) ?>" data-tax-history-form><input type="hidden" name="_token" value="<?= $esc(Csrf::token()) ?>" /><input type="hidden" name="tax_action" value="import_history" /><div class="tax-fields-grid"><div class="tax-field"><label for="history-model">Modelo</label><select id="history-model" name="history_model" data-tax-history-model><option value="130" <?= $historyFormModel === '130' ? 'selected' : '' ?>>130 · IRPF</option><option value="303" <?= $historyFormModel === '303' ? 'selected' : '' ?>>303 · IVA</option></select></div><div class="tax-field"><label for="history-year">Ejercicio</label><input id="history-year" name="history_year" type="number" value="<?= $esc($historyFormData['history_year'] ?? $year) ?>" min="2000" max="2100" required /></div><div class="tax-field"><label for="history-quarter">Trimestre</label><select id="history-quarter" name="history_quarter"><?php for ($k=1;$k<=4;$k++): ?><option value="<?= $k ?>" <?= $k === (int)($historyFormData['history_quarter'] ?? max(1,$quarter-1)) ? 'selected' : '' ?>><?= $k ?>T</option><?php endfor; ?></select></div><div class="tax-field"><label for="history-receipt">Justificante</label><input id="history-receipt" name="history_receipt" value="<?= $esc($historyFormData['history_receipt'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" required /></div><div class="tax-field"><label for="history-date">Fecha de presentación</label><input id="history-date" name="history_date" value="<?= $esc($historyFormData['history_date'] ?? '') ?>" type="date" max="<?= date('Y-m-d') ?>" required /></div></div>
      <?php foreach (['130'=>['07'=>'Pago fraccionado previo','15'=>'Negativos anteriores aplicados','16'=>'Deducción vivienda','19'=>'Resultado final'],'303'=>['71'=>'Resultado final','87'=>'Saldo anterior pendiente para próximos períodos','72'=>'Importe a compensar generado en este período (0 si pediste devolución)']] as $historyModel=>$historyBoxes): ?><fieldset data-tax-history-fields="<?= $esc($historyModel) ?>" <?= (string)$historyModel !== $historyFormModel ? 'hidden disabled' : '' ?>><legend>Casillas del modelo <?= $esc($historyModel) ?></legend><div class="tax-fields-grid"><?php foreach ($historyBoxes as $box=>$label): ?><div class="tax-field"><label for="history-<?= $esc($historyModel) ?>-<?= $esc($box) ?>"><?= $esc($box) ?> · <?= $esc($label) ?></label><input id="history-<?= $esc($historyModel) ?>-<?= $esc($box) ?>" name="history_boxes[<?= $esc($historyModel) ?>][<?= $esc($box) ?>]" value="<?= $esc($historyFormData['history_boxes'][$historyModel][$box] ?? '') ?>" type="text" inputmode="decimal" placeholder="0,00" required /></div><?php endforeach; ?></div></fieldset><?php endforeach; ?><button type="submit" class="btn">Guardar en el historial</button></form></details>
  </section>

  <details class="tax-section tax-settings"><summary>Configuración fiscal y otros modelos</summary><form method="post" action="<?= $esc($url()) ?>"><input type="hidden" name="_token" value="<?= $esc(Csrf::token()) ?>" /><input type="hidden" name="tax_action" value="setup" /><div class="tax-field"><label for="activity-mode">Tipo de actividad</label><select id="activity-mode" name="activity_mode"><option value="professional">Profesional / freelance</option><option value="business" <?= ($profile['activity_mode'] ?? '') === 'business' ? 'selected' : '' ?>>Actividad empresarial</option></select></div><div class="tax-model-options"><?php foreach ($allModels as $code=>$label): ?><label class="tax-check"><input type="checkbox" name="tax_models[]" value="<?= $esc($code) ?>" <?= in_array((string)$code,$storedModels,true) ? 'checked' : '' ?> /><span><strong>Modelo <?= $esc($code) ?></strong> · <?= $esc($label) ?></span></label><?php endforeach; ?></div><?php foreach (['issues_invoices_with_irpf'=>'Mis facturas suelen llevar retención de IRPF','has_rent_withholdings'=>'Pago alquiler con retención','has_payroll_or_professional_withholdings'=>'Pago profesionales o nóminas con retención'] as $flag=>$label): ?><label class="tax-check"><input type="checkbox" name="<?= $esc($flag) ?>" <?= !empty($profile[$flag]) ? 'checked' : '' ?> /><span><?= $esc($label) ?></span></label><?php endforeach; ?><button type="submit" class="btn tax-btn-quiet">Guardar configuración</button></form>
    <?php if (in_array('390',$storedModels,true)): $annual = TaxQuarterService::annualVatSummary($year); ?><h3>Modelo 390 · Resumen anual de <?= $year ?></h3><div class="tax-records"><div><span>IVA de ventas</span><strong><?= $esc($money($annual['sales_vat'])) ?></strong></div><div><span>IVA de gastos</span><strong><?= $esc($money($annual['expenses_vat'])) ?></strong></div><div><span>Diferencia registrada</span><strong><?= $esc($money($annual['result'])) ?></strong></div></div><?php endif; ?>
    <?php foreach (['111','115'] as $extra): if (in_array($extra,$storedModels,true)): ?><p><strong>Modelo <?= $extra ?>:</strong> <?= $esc($allModels[$extra]) ?>. Revisa las retenciones de estos pagos; el cálculo automático de este modelo todavía no está disponible.</p><?php endif; endforeach; ?><a href="<?= $esc(route_path('reminders')) ?>">Configurar avisos de presentación</a>
  </details>
</section>
<script src="/assets/js/declarations.js?v=1" defer></script>
