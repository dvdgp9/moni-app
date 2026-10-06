<?php
declare(strict_types=1);

namespace Moni\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Moni\Support\TaxAmount;

/** Pure calculations for quarterly declarations. Drafts never count as filed declarations. */
final class TaxDeclarationService
{
    public const SOURCES = [
        'records' => 'De tus registros', 'manual' => 'Introducido por ti',
        'history' => 'Del historial', 'calculated' => 'Calculado',
        'pending' => 'Pendiente', 'default' => 'Valor por defecto',
    ];

    public static function defaultPeriod(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        // During a filing month open the quarter just ended, including the previous year in January.
        $month = (int)$now->format('n');
        $year = (int)$now->format('Y');
        return $month === 1 ? [$year - 1, 4] : [$year, (int)ceil(($month - 1) / 3)];
    }

    public static function catalog(string $model): array
    {
        if ($model === '130') {
            $labels = [
                '01' => ['Ingresos computables acumulados', 'Actividad', true],
                '02' => ['Gastos fiscalmente deducibles acumulados', 'Actividad', true],
                '03' => ['Rendimiento neto', 'Actividad', false],
                '04' => ['Pago sobre el rendimiento positivo', 'Actividad', true],
                '05' => ['Importe deducible de trimestres anteriores', 'Actividad', true],
                '06' => ['Retenciones e ingresos a cuenta acumulados', 'Actividad', true],
                '07' => ['Pago fraccionado previo del trimestre', 'Actividad', false],
                '08' => ['Ingresos del trimestre de actividades agrícolas y similares', 'Actividades agrícolas', true],
                '09' => ['Pago sobre los ingresos de actividades agrícolas', 'Actividades agrícolas', true],
                '10' => ['Retenciones del trimestre de actividades agrícolas', 'Actividades agrícolas', true],
                '11' => ['Pago fraccionado previo de actividades agrícolas', 'Actividades agrícolas', false],
                '12' => ['Suma de pagos fraccionados previos', 'Liquidación', false],
                '13' => ['Minoración por rendimientos del ejercicio anterior', 'Liquidación', true],
                '14' => ['Diferencia tras la minoración', 'Liquidación', false],
                '15' => ['Resultados negativos anteriores aplicados', 'Liquidación', false],
                '16' => ['Deducción por vivienda habitual', 'Liquidación', true],
                '17' => ['Total tras deducciones', 'Liquidación', false],
                '18' => ['Ingresos anteriores del mismo trimestre (complementaria)', 'Liquidación', true],
                '19' => ['Resultado de la autoliquidación', 'Liquidación', false],
            ];
        } elseif ($model === '303') {
            $labels = [];
            foreach ([['150','151','152','0'], ['165','166','167','tipo adicional'], ['01','02','03','4'], ['153','154','155','tipo adicional'], ['04','05','06','10'], ['07','08','09','21']] as [$base,$rate,$quota,$name]) {
                $labels[$base] = ["Base imponible al $name %", 'IVA devengado', true];
                $labels[$rate] = ["Tipo de IVA ($name %)", 'IVA devengado', in_array($rate, ['166','154'], true), '%'];
                $labels[$quota] = ["Cuota de IVA al $name %", 'IVA devengado', true];
            }
            foreach (['10'=>'Base de adquisiciones intracomunitarias', '11'=>'IVA de adquisiciones intracomunitarias', '12'=>'Base de otras operaciones con inversión del sujeto pasivo', '13'=>'IVA de otras operaciones con inversión del sujeto pasivo', '14'=>'Modificación de bases', '15'=>'Modificación de cuotas', '156'=>'Base de recargo al 1,75 %', '157'=>'Tipo de recargo (1,75 %)', '158'=>'Recargo al 1,75 %', '168'=>'Base de recargo al 0,50 %', '169'=>'Tipo de recargo (0,50 %)', '170'=>'Recargo al 0,50 %', '16'=>'Base de recargo de otro tipo', '17'=>'Otro tipo de recargo', '18'=>'Cuota de recargo de otro tipo', '19'=>'Base de recargo al 1,40 %', '20'=>'Tipo de recargo (1,40 %)', '21'=>'Recargo al 1,40 %', '22'=>'Base de recargo al 5,20 %', '23'=>'Tipo de recargo (5,20 %)', '24'=>'Recargo al 5,20 %', '25'=>'Modificación de bases del recargo', '26'=>'Modificación de cuotas del recargo'] as $box=>$label) {
                $labels[$box] = [$label, 'IVA devengado', true, in_array((string)$box, ['157','169','17','20','23'], true) ? '%' : '€'];
            }
            $labels['27'] = ['Total cuota devengada', 'IVA devengado', false];
            foreach (['28'=>'Base de operaciones interiores corrientes', '29'=>'IVA deducible de operaciones interiores corrientes', '30'=>'Base de bienes de inversión interiores', '31'=>'IVA deducible de bienes de inversión interiores', '32'=>'Base de importaciones de bienes corrientes', '33'=>'IVA deducible de importaciones de bienes corrientes', '34'=>'Base de importaciones de bienes de inversión', '35'=>'IVA deducible de importaciones de bienes de inversión', '36'=>'Base de adquisiciones intracomunitarias corrientes', '37'=>'IVA deducible de adquisiciones intracomunitarias corrientes', '38'=>'Base de adquisiciones intracomunitarias de inversión', '39'=>'IVA deducible de adquisiciones intracomunitarias de inversión', '40'=>'Base de rectificación de deducciones', '41'=>'Cuota de rectificación de deducciones', '42'=>'Compensaciones del régimen agrícola', '43'=>'Regularización de bienes de inversión', '44'=>'Regularización por prorrata definitiva'] as $box=>$label) {
                $labels[$box] = [$label, 'IVA deducible', true];
            }
            $labels['45'] = ['Total a deducir', 'IVA deducible', false];
            $labels['46'] = ['Resultado del régimen general', 'IVA deducible', false];
            foreach (['59'=>'Entregas intracomunitarias de bienes y servicios', '60'=>'Exportaciones y operaciones asimiladas', '120'=>'Operaciones no sujetas por localización', '122'=>'Operaciones sujetas con inversión del sujeto pasivo', '123'=>'Operaciones no sujetas acogidas a ventanilla única', '124'=>'Operaciones sujetas acogidas a ventanilla única', '62'=>'Base de ventas en criterio de caja según devengo general', '63'=>'Cuota de ventas en criterio de caja según devengo general', '74'=>'Base de compras en criterio de caja', '75'=>'Cuota de compras en criterio de caja'] as $box=>$label) {
                $labels[$box] = [$label, 'Información adicional', true];
            }
            foreach (['76'=>['Regularización de cuotas del art. 80.Cinco.5ª',true], '64'=>['Suma de resultados',false], '65'=>['Porcentaje atribuible a la Administración del Estado',false,'%'], '66'=>['Resultado atribuible a la Administración del Estado',false], '77'=>['IVA a la importación pendiente de ingreso',true], '110'=>['Cuotas a compensar pendientes de períodos anteriores',true], '78'=>['Compensación de períodos anteriores aplicada',false], '87'=>['Compensación anterior pendiente para próximos períodos',false], '68'=>['Regularización anual',true], '108'=>['Otros ajustes por discrepancia de criterio administrativo',true], '69'=>['Resultado de la autoliquidación',false], '70'=>['Resultado a ingresar de la declaración que se rectifica',true], '109'=>['Devoluciones acordadas del mismo período',true], '112'=>['Pago a cuenta de entregas de carburantes',true], '71'=>['Resultado de la liquidación',false]] as $box=>$entry) {
                $labels[$box] = [$entry[0], 'Resultado', $entry[1], $entry[2] ?? '€'];
            }
            $labels['72'] = ['Importe a compensar', 'Presentación', false];
            $labels['73'] = ['Importe a devolver', 'Presentación', false];
        } else {
            throw new InvalidArgumentException('Modelo no disponible para el asistente.');
        }
        $catalog = [];
        $aeatCalculated = $model === '130' ? ['03','04','07','09','11','12','14','17','19'] : ['27','45','46','64','66','87','69','71'];
        foreach ($labels as $box=>$entry) {
            $catalog[(string)$box] = ['box'=>(string)$box, 'label'=>$entry[0], 'group'=>$entry[1], 'editable'=>$entry[2], 'unit'=>$entry[3] ?? '€', 'aeat_calculated'=>in_array((string)$box,$aeatCalculated,true)];
        }
        return $catalog;
    }

    public static function input(array $post, string $model): array
    {
        $overrides = [];
        foreach (self::catalog($model) as $box=>$field) {
            $raw = $post['boxes'][$box] ?? '';
            if (!$field['editable'] || !is_string($raw) || trim($raw) === '') { continue; }
            try { $overrides[$box] = TaxAmount::parse($raw); }
            catch (InvalidArgumentException $e) { throw new InvalidArgumentException("Casilla $box: " . $e->getMessage()); }
        }
        $input = ['overrides'=>$overrides];
        foreach (['review_records','review_special','no_previous','agricultural','housing','complementary','additional_rates_reviewed'] as $flag) {
            $input[$flag] = isset($post[$flag]);
        }
        $input['previous_net'] = trim((string)($post['previous_net'] ?? '')) === '' ? null : TaxAmount::parse((string)$post['previous_net']);
        $input['negative_balance'] = trim((string)($post['negative_balance'] ?? '')) === '' ? null : TaxAmount::parse((string)$post['negative_balance']);
        if ($input['negative_balance'] !== null && $input['negative_balance'] < 0) { throw new InvalidArgumentException('El saldo pendiente se introduce como importe positivo.'); }
        $input['previous_receipt'] = trim((string)($post['previous_receipt'] ?? ''));
        if ($input['complementary'] && !preg_match('/^\d{13}$/D', $input['previous_receipt'])) {
            throw new InvalidArgumentException('Indica el justificante de 13 dígitos de la declaración anterior.');
        }
        $input['refund_choice'] = in_array(($post['refund_choice'] ?? ''), ['compensate','refund'], true) ? $post['refund_choice'] : 'compensate';
        return $input;
    }

    public static function calculate(string $model, int $year, int $quarter, array $sales, array $expenses, array $input = [], array $history = []): array
    {
        $catalog = self::catalog($model);
        $fields = [];
        foreach ($catalog as $box=>$field) { $fields[$box] = $field + ['value'=>0.0, 'source'=>'default']; }
        $overrides = $input['overrides'] ?? [];
        $set = static function (string $box, ?float $value, string $source = 'calculated') use (&$fields): void {
            $fields[$box]['value'] = $value === null ? null : round($value, 2);
            $fields[$box]['source'] = $value === null ? 'pending' : $source;
        };
        $read = static function (string $box, ?float $fallback = 0.0, string $source = 'default') use ($overrides, $set): ?float {
            $value = array_key_exists($box, $overrides) ? (float)$overrides[$box] : $fallback;
            $set($box, $value, array_key_exists($box, $overrides) ? 'manual' : $source);
            return $value;
        };
        $sum = static function (array $boxes) use (&$fields): ?float {
            $sum = 0.0;
            foreach ($boxes as $box) { if ($fields[$box]['value'] === null) { return null; } $sum += $fields[$box]['value']; }
            return round($sum, 2);
        };
        $issues = [];
        if ($model === '303' && $year !== 2026) { $issues[] = 'Las casillas de esta guía del 303 están verificadas para 2026. Consulta el formulario del ejercicio seleccionado antes de registrar una presentación.'; }
        $previous = self::previous($model, $year, $quarter, $history);
        $noPrevious = !empty($input['no_previous']) || ($model === '130' && $quarter === 1);
        if ($model === '130') {
            $income = $read('01', (float)($sales['base_total_ytd'] ?? 0), 'records');
            $cost = $read('02', (float)($expenses['base_total_ytd'] ?? 0), 'records');
            $net = round($income - $cost, 2);
            $set('03', $net);
            $tax = $read('04', max(0, round($net * .20, 2)), 'calculated');
            $prior = $read('05', $noPrevious ? 0.0 : ($previous['complete'] ? $previous['payment'] : null), $noPrevious ? 'default' : 'history');
            $ret = $read('06', (float)($sales['irpf_total_ytd'] ?? 0), 'records');
            $c7 = $prior === null ? null : round($tax - $prior - $ret, 2);
            $set('07', $c7);
            $agricultural = !empty($input['agricultural']);
            $agIncome = $read('08', $agricultural ? null : 0.0);
            $agTax = $read('09', $agIncome === null ? null : round($agIncome * .02, 2), 'calculated');
            $agRet = $read('10', $agricultural ? null : 0.0);
            $c11 = $agTax === null || $agRet === null ? null : round($agTax - $agRet, 2);
            $set('11', $c11);
            $total = $c7 === null || $c11 === null ? null : max(0.0, round($c7 + $c11, 2));
            $set('12', $total);
            $previousNet = $input['previous_net'] ?? null;
            $minor = $previousNet === null ? null : ($previousNet <= 9000 ? 100.0 : ($previousNet <= 10000 ? 75.0 : ($previousNet <= 11000 ? 50.0 : ($previousNet <= 12000 ? 25.0 : 0.0))));
            $minor = $read('13', $minor, 'calculated');
            if ($minor === null) { $issues[] = 'Indica el rendimiento neto del año anterior o el importe de la casilla 13.'; }
            if ($minor !== null && ($minor < 0 || $minor > 100)) { $issues[] = 'La minoración de la casilla 13 debe estar entre 0 y 100 €.'; }
            $c14 = $total === null || $minor === null ? null : round($total - $minor, 2);
            $set('14', $c14);
            $balance = $input['negative_balance'] ?? ($noPrevious ? 0.0 : ($previous['complete'] ? $previous['negative'] : null));
            $set('15', $c14 === null ? null : ($c14 <= 0 ? 0.0 : ($balance === null ? null : min($c14, $balance))), isset($input['negative_balance']) ? 'manual' : ($noPrevious ? 'default' : 'history'));
            $housing = $read('16', empty($input['housing']) ? 0.0 : null);
            $ded = $fields['15']['value'];
            if ($housing === null) { $issues[] = 'Introduce la deducción por vivienda después de comprobar sus requisitos.'; }
            if ($housing !== null && ($housing < 0 || ($c14 !== null && $ded !== null && $housing > max(0, $c14 - $ded)))) { $issues[] = 'La casilla 16 no puede superar el saldo positivo tras la casilla 15.'; }
            if ($housing !== null && $housing > 0 && empty($input['housing'])) { $issues[] = 'Confirma que te corresponde la deducción por vivienda antes de usar la casilla 16.'; }
            if ($housing !== null && $housing > 0 && $agricultural && $income > 0) { $issues[] = 'La deducción por vivienda no es aplicable al ejercicio simultáneo de actividades de ambos apartados.'; }
            if ($housing !== null && $housing > 0 && !$agricultural && $housing > min(660.14, max(0,round($net * .02,2)))) { $issues[] = 'La casilla 16 supera el 2 % del rendimiento o el límite trimestral de 660,14 €.'; }
            $c17 = $c14 === null || $ded === null || $housing === null ? null : round($c14 - $ded - $housing, 2);
            $set('17', $c17);
            $paid = $read('18', empty($input['complementary']) ? 0.0 : null);
            if (empty($input['complementary']) && $paid != 0) { $issues[] = 'La casilla 18 solo se aplica a declaraciones complementarias.'; }
            $set('19', $c17 === null || $paid === null ? null : round($c17 - $paid, 2));
            $resultBox = '19';
            foreach (['01','02','04','05','06','08','09','10','18'] as $box) {
                if ($fields[$box]['value'] !== null && $fields[$box]['value'] < 0) { $issues[] = "Comprueba el importe de la casilla $box: debe ser positivo o cero."; }
            }
        } else {
            $known = ['0.00'=>['150','151','152',0], '4.00'=>['01','02','03',4], '10.00'=>['04','05','06',10], '21.00'=>['07','08','09',21]];
            foreach ($known as $rate=>[$base,$rateBox,$quota,$percent]) {
                $data = $sales['by_vat'][$rate] ?? [];
                $baseAmount = $read($base, (float)($data['base'] ?? 0), 'records');
                $set($rateBox, (float)$percent, 'default');
                $read($quota, array_key_exists($base,$overrides) ? round($baseAmount * $percent / 100,2) : (float)($data['iva'] ?? 0), array_key_exists($base,$overrides) ? 'calculated' : 'records');
                if ($rate === '0.00' && abs((float)($data['base'] ?? 0)) > .001 && !array_key_exists($base,$overrides)) {
                    $issues[] = 'Clasifica las ventas sin IVA: confirma manualmente la casilla 150 para tipo cero, o ponla a cero y asigna las operaciones exentas/no sujetas a su casilla cuando corresponda.';
                }
            }
            foreach (($sales['by_vat'] ?? []) as $rate=>$data) {
                if (!isset($known[$rate]) && abs((float)$data['base']) > .001 && empty($input['additional_rates_reviewed'])) { $issues[] = "Hay ventas al $rate %: completa y confirma las casillas adicionales correspondientes."; }
            }
            $deductibleBase = 0.0;
            foreach (($expenses['by_vat'] ?? []) as $data) { if (abs((float)($data['vat'] ?? 0)) > .001) { $deductibleBase += (float)($data['base'] ?? 0); } }
            $read('28', round($deductibleBase,2), 'records');
            $read('29', (float)($expenses['vat_total'] ?? 0), 'records');
            foreach ($catalog as $box=>$field) {
                if ($field['editable'] && !in_array((string)$box,['150','152','01','03','04','06','07','09','28','29','110'],true)) { $read((string)$box); }
            }
            foreach ([['165','166','167'],['153','154','155'],['156','157','158'],['168','169','170'],['16','17','18'],['19','20','21'],['22','23','24']] as [$base,$rate,$quota]) {
                $fixed = ['157'=>1.75,'169'=>.5,'20'=>1.4,'23'=>5.2];
                if (isset($fixed[$rate]) && !array_key_exists($rate,$overrides)) { $set($rate,$fixed[$rate],'default'); }
                if (!array_key_exists($quota,$overrides)) { $set($quota,round($fields[$base]['value'] * $fields[$rate]['value'] / 100,2)); }
                if ($fields[$base]['value'] != 0 && $fields[$rate]['value'] <= 0) { $issues[] = "Indica el tipo aplicable a la base de la casilla $base."; }
            }
            $set('27', $sum(['152','167','03','155','06','09','11','13','15','158','170','18','21','24','26']));
            $set('45', $sum(['29','31','33','35','37','39','41','42','43','44']));
            $c46 = round($fields['27']['value'] - $fields['45']['value'],2);
            $set('46', $c46);
            $set('64', round($c46 + $fields['76']['value'],2));
            $set('65', 100.0, 'default');
            $set('66', $fields['64']['value']);
            $carry = $read('110', $noPrevious ? 0.0 : ($previous['complete'] ? $previous['vat_balance'] : null), $noPrevious ? 'default' : 'history');
            $applied = $carry === null ? null : min(max(0.0, $fields['66']['value'] + $fields['77']['value']),max(0.0,$carry));
            $set('78', $applied);
            $set('87', $carry === null ? null : round($carry - $applied,2));
            $c69 = $applied === null ? null : round($fields['66']['value'] + $fields['77']['value'] - $applied + $fields['68']['value'] + $fields['108']['value'],2);
            $set('69', $c69);
            $set('71', $c69 === null ? null : round($c69 - $fields['70']['value'] + $fields['109']['value'] - $fields['112']['value'],2));
            if ($carry !== null && $carry < 0) { $issues[] = 'La casilla 110 debe ser positiva o cero.'; }
            if (empty($input['complementary']) && ($fields['70']['value'] != 0 || $fields['109']['value'] != 0 || $fields['108']['value'] != 0)) { $issues[] = 'Las casillas 70, 109 y 108 requieren una autoliquidación rectificativa.'; }
            if ($quarter !== 4 && $fields['44']['value'] != 0) { $issues[] = 'Revisa la casilla 44: se usa en el último período o en el cese de actividad.'; }
            foreach ($fields as $box=>$field) {
                if ($field['unit'] === '%' && ($field['value'] < 0 || $field['value'] > 100)) { $issues[] = "El porcentaje de la casilla $box debe estar entre 0 y 100."; }
            }
            $resultBox = '71';
        }
        if (empty($input['review_records'])) { $issues[] = $model === '130' ? 'Revisa ingresos, gastos deducibles y retenciones del acumulado anual.' : 'Revisa las ventas y confirma qué IVA de tus gastos es deducible y su clasificación.'; }
        if (empty($input['review_special'])) { $issues[] = $model === '130' ? 'Revisa si te aplican actividades agrícolas, vivienda, porcentajes especiales o una complementaria.' : 'Confirma que tributas en régimen general, con devengo y al 100 % en territorio común; revisa las operaciones especiales.'; }
        $pending = array_filter($fields, static fn(array $f):bool => $f['value'] === null);
        if ($pending) { $issues[] = 'Faltan datos para las casillas ' . implode(', ',array_keys($pending)) . '.'; }
        $result = $fields[$resultBox]['value'];
        $outcome = $result === null ? 'Faltan datos' : ($result > 0 ? 'A ingresar' : ($result < 0 ? ($model === '130' ? ($quarter === 4 ? 'Resultado negativo' : 'A deducir en próximos trimestres') : (($quarter === 4 && ($input['refund_choice'] ?? '') === 'refund') ? 'A devolver' : 'A compensar')) : 'Resultado cero'));
        if ($model === '303') {
            $set('72', $result === null ? null : ($outcome === 'A compensar' ? abs($result) : 0.0));
            $set('73', $result === null ? null : ($outcome === 'A devolver' ? abs($result) : 0.0));
            if ($outcome === 'A compensar' && $fields['78']['value'] > 0) { $issues[] = 'Revisa los ajustes: el resultado a compensar no es compatible con la aplicación de saldo anterior en la casilla 78.'; }
        }
        if ($model === '303' && $quarter !== 4 && ($input['refund_choice'] ?? '') === 'refund') { $issues[] = 'En este asistente trimestral la devolución se contempla en el 4T. Revisa tu régimen si necesitas otra opción.'; }
        return ['model'=>$model,'year'=>$year,'quarter'=>$quarter,'fields'=>$fields,'result'=>$result,'result_box'=>$resultBox,'outcome'=>$outcome,'issues'=>array_values(array_unique($issues)),'ready'=>!$issues,'previous'=>$previous];
    }

    public static function previous(string $model, int $year, int $quarter, array $history): array
    {
        if ($model === '303') {
            $priorYear = $quarter === 1 ? $year - 1 : $year;
            $priorQuarter = $quarter === 1 ? 4 : $quarter - 1;
            $last = null;
            foreach ($history as $record) {
                if (($record['model'] ?? '') === '303' && (int)($record['year'] ?? 0) === $priorYear && (int)($record['quarter'] ?? 0) === $priorQuarter && ($record['status'] ?? '') === 'presented' && ($last === null || self::revisionOrder($record, $last) > 0)) { $last = $record; }
            }
            return ['complete'=>$last !== null,'payment'=>0.0,'negative'=>0.0,'vat_balance'=>$last === null ? 0.0 : round((float)($last['boxes']['87'] ?? 0) + (float)($last['boxes']['72'] ?? 0),2),'quarters'=>$last === null ? [] : [$priorQuarter]];
        }
        $rows = [];
        foreach ($history as $record) {
            if (($record['model'] ?? '') === $model && (int)($record['year'] ?? 0) === $year && (int)($record['quarter'] ?? 0) < $quarter && ($record['status'] ?? '') === 'presented') {
                $q = (int)$record['quarter'];
                if (!isset($rows[$q]) || self::revisionOrder($record, $rows[$q]) > 0) { $rows[$q] = $record; }
            }
        }
        ksort($rows);
        $payment = $negative = 0.0;
        foreach ($rows as $row) {
            $b = $row['boxes'];
            if ($model === '130') {
                $payment += max(0.0,(float)($b['07'] ?? 0)) - (float)($b['16'] ?? 0);
                $negative = max(0.0,$negative - (float)($b['15'] ?? 0)) + max(0.0,-(float)($b['19'] ?? 0));
            }
        }
        $last = $rows ? end($rows) : null;
        $vatBalance = $last === null ? 0.0 : (float)($last['boxes']['87'] ?? 0) + (float)($last['boxes']['72'] ?? 0);
        // For VAT a known immediately previous period contains the whole remaining balance.
        $complete = $model === '303' ? isset($rows[$quarter - 1]) : count($rows) === $quarter - 1;
        return ['complete'=>$complete, 'payment'=>round($payment,2), 'negative'=>round($negative,2), 'vat_balance'=>round($vatBalance,2), 'quarters'=>array_keys($rows)];
    }

    public static function signature(array $declaration, array $input): string
    {
        return hash('sha256', json_encode([$declaration['model'],$declaration['year'],$declaration['quarter'],$declaration['fields'],$declaration['issues'],$input], JSON_THROW_ON_ERROR));
    }

    public static function revisionOrder(array $a, array $b): int
    {
        return strcmp(($a['filed_date'] ?? '') . ' ' . ($a['saved_at'] ?? ''), ($b['filed_date'] ?? '') . ' ' . ($b['saved_at'] ?? ''));
    }
}
